<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InvoiceStoreRequest;
use App\Http\Resources\SalesOrderResource;
use App\Http\Requests\Api\SalesOrderStoreRequest;
use App\Http\Requests\Api\SalesOrderUpdateRequest;
use App\Models\SalesOrder;
use App\Models\StockProductUnit;
use App\Models\UserDiscount;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SalesOrderController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:sales_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales_order_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales_order_create', ['only' => 'store']);
        $this->middleware('permission:sales_order_edit', ['only' => 'update']);
        $this->middleware('permission:sales_order_delete', ['only' => 'destroy']);
        $this->middleware('permission:sales_order_print', ['only' => 'print']);
        $this->middleware('permission:sales_order_export_xml', ['only' => 'exportXml']);
    }

    public function index()
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_access'), 403);
        // $query = SalesOrder::withCount('details')->whereHas('details', function ($q) {
        //     $q->doesntHave('deliveryOrderDetail');
        // });
        $query = SalesOrder::tenanted()->withCount('details');

        $salesOrders = QueryBuilder::for($query)
            ->allowedFilters([
                'invoice_no', 'is_invoice',
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('reseller_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::scope('has_delivery_order', 'detailsHasDO'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'warehouse_id', 'created_at'])
            ->allowedIncludes(['details', 'warehouse', 'user', 'payments', \Spatie\QueryBuilder\AllowedInclude::callback('voucher', function ($q) {
                $q->with('category');
            }),])
            ->paginate($this->per_page);

        return SalesOrderResource::collection($salesOrders);
    }

    public function show(int $id)
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_access'), 403);
        $salesOrder = SalesOrder::findTenanted($id);
        return new SalesOrderResource($salesOrder->load(['voucher.category', 'payments', 'details' => fn ($q) => $q->with(['warehouse', 'packaging']), 'user'])->loadCount('details'));
    }

    public function store(SalesOrderStoreRequest $request)
    {
        $salesOrder = SalesOrderService::createOrder(SalesOrder::make(['raw_source' => $request->validated()]), (bool) $request->is_preview ?? false);
        return new SalesOrderResource($salesOrder);
    }

    public function invoice(InvoiceStoreRequest $request)
    {
        foreach ($request->items ?? [] as $item) {
            $stocks = \App\Models\Stock::whereAvailableStock()
                ->whereHas('stockProductUnit', fn ($q) => $q->where('product_unit_id', $item['product_unit_id'])->where('warehouse_id', $item['warehouse_id']))
                ->limit($item['qty'])
                ->get(['id']);

            if ($stocks->count() < $item['qty']) return $this->errorResponse(message: sprintf('Stok %s tidak tersedia', \Illuminate\Support\Facades\DB::table('product_units')->where('id', $item['product_unit_id'])->first()?->name ?? ''), code: \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $salesOrder = SalesOrderService::createOrder(SalesOrder::make(['raw_source' => $request->validated(), 'is_invoice' => true]), (bool) $request->is_preview ?? false, true);

        if ($salesOrder) {
            // create history
            $salesOrder->details->each(function ($salesOrderDetail) use ($salesOrder) {
                $stockProductUnit = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
                    ->where('product_unit_id', $salesOrderDetail->product_unit_id)
                    ->first(['id']);

                $salesOrderDetail->histories()->create([
                    'user_id' => $salesOrder->user_id,
                    'stock_product_unit_id' => $stockProductUnit->id,
                    'value' => $salesOrderDetail->qty,
                    'is_increment' => 0,
                    'description' => "Create SO invoice " . $salesOrder->invoice_no,
                    'ip' => request()->ip(),
                    'agent' => request()->header('user-agent'),
                ]);
            });
        }

        return new SalesOrderResource($salesOrder);
    }

    public function update(int $id, SalesOrderUpdateRequest $request)
    {
        $salesOrder = SalesOrder::findTenanted($id);
        if (!$salesOrder->details?->every(fn ($salesOrderDetail) => !$salesOrderDetail->deliveryOrderDetail))
            return response()->json(['message' => "DO harus dihapus terlebih dahulu sebelum mengedit SO"], 400);

        $salesOrder->raw_source = $request->validated();
        $salesOrder = SalesOrderService::updateOrder($salesOrder, (bool) $request->is_preview ?? false);
        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(int $id)
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_delete'), 403);
        $salesOrder = SalesOrder::findTenanted($id);
        if ($salesOrder->deliveryOrder?->is_done) return response()->json(['message' => "Can't update SO if DO is already done"], 400);

        // return stock if salesorder is invoice
        if ($salesOrder->is_invoice) {
            DB::beginTransaction();
            try {
                $salesOrder->details->each(function ($salesOrderDetail) use ($salesOrder) {
                    $stockProductUnit = StockProductUnit::where('warehouse_id', $salesOrderDetail->warehouse_id)
                        ->where('product_unit_id', $salesOrderDetail->product_unit_id)
                        ->first(['id']);

                    $salesOrderDetail->histories()->create([
                        'user_id' => $salesOrder->user_id,
                        'stock_product_unit_id' => $stockProductUnit->id,
                        'value' => $salesOrderDetail->qty,
                        'is_increment' => 1,
                        'description' => "Return stock from delete SO invoice " . $salesOrder->invoice_no,
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);
                });
                $salesOrder->forceDelete();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse(message: $e->getMessage(), code: $e->getCode() ?? 500);
            }
        }

        $salesOrder->delete();
        return $this->deletedResponse();
    }

    public function print(int $id, string $type)
    {
        // abort_if(!auth()->user()->tokenCan('sales_order_print'), 403);
        $salesOrder = SalesOrder::findTenanted($id);
        $salesOrder->load([
            'reseller',
            'details' => fn ($q) => $q->with('productUnit.product'),
        ])->loadSum('payments', 'amount');

        $salesOrderDetails = $salesOrder->details->chunk(10);

        $lastOrderDetailsKey = $salesOrderDetails->keys()->last();
        $maxProductsBlackSpace = 10;

        $spellTotalPrice = \NumberToWords\NumberToWords::transformNumber('en', $salesOrder->price);
        $bankTransferInfo = \App\Services\SettingService::bankTransferInfo();

        $view = $type == 'print' ? 'pdf.salesOrders.salesOrder' : 'pdf.salesOrders.salesOrderInvoice';
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setPaper('a4', 'landscape')->loadView($view, ['salesOrder' => $salesOrder, 'salesOrderDetails' => $salesOrderDetails, 'maxProductsBlackSpace' => $maxProductsBlackSpace, 'lastOrderDetailsKey' => $lastOrderDetailsKey, 'spellTotalPrice' => $spellTotalPrice, 'bankTransferInfo' => $bankTransferInfo]);

        return $pdf->download('sales-order-' . $salesOrder->invoice_no . '.pdf');
    }

    public function exportXml(int $id)
    {
        $salesOrder = SalesOrder::findTenanted($id);
        $salesOrder->load(['reseller', 'details' => fn ($q) => $q->with('packaging', 'productUnit')]);
        // abort_if(!auth()->user()->tokenCan('sales_order_export_xml'), 403);
        return response(view('xml.salesOrders.salesOrder')->with(compact('salesOrder')), 200, [
            'Content-Type' => 'application/xml',
            // use your required mime type
            'Content-Disposition' => 'attachment; filename="Sales Order ' . $salesOrder->invoice_no . '.xml"',
        ]);
    }

    public function productUnits(Request $request)
    {
        $userDiscounts = UserDiscount::select('product_brand_id', 'value', 'is_percentage')->where('user_id', $request->customer_id)->get();

        $query = StockProductUnit::select('id', 'warehouse_id', 'product_unit_id')
            ->withCount(['stocks' => fn ($q) => $q->whereAvailableStock()->whereNull('description')])
            ->with([
                'warehouse' => fn ($q) => $q->select('id', 'code'),
                'productUnit' => function ($q) {
                    $q->select('id', 'uom_id', 'product_id', 'packaging_id', 'name', 'price')
                        ->with([
                            'uom' => fn ($q) => $q->select('id', 'name'),
                            'product' => fn ($q) => $q->select('id', 'name', 'product_brand_id'),
                        ]);
                },
            ])
            ->having('stocks_count', '>', 0);

        $stockProductUnits = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::scope('product_unit'),
            ])
            ->paginate($this->per_page);

        $stockProductUnits->each(function ($stockProductUnit) use ($userDiscounts) {
            $productUnit = $stockProductUnit->productUnit;
            $productUnit->name = $productUnit->name . ' - ' . $stockProductUnit->warehouse?->code ?? '';
            $productUnit->price_discount = $productUnit->price;
            $productUnit->discount = 0;
            $productUnit->is_percentage = 1;

            $productBrandId = $productUnit?->product?->product_brand_id ?? null;
            if ($userDiscounts->contains('product_brand_id', $productBrandId)) {
                $discount = $userDiscounts->firstWhere('product_brand_id', $productBrandId);
                if ($discount->is_percentage) {
                    $totalDiscount = $productUnit->price * $discount->value;
                    $totalDiscount = $totalDiscount <= 0 ? 0 : ($totalDiscount / 100);
                    $totalPrice = $productUnit->price - $totalDiscount;
                } else {
                    $totalPrice = $productUnit->price - $discount->value;
                }

                $productUnit->price_discount = $totalPrice <= 0 ? 0 : $totalPrice;
                $productUnit->discount = $discount->value;
                $productUnit->is_percentage = $discount->is_percentage;
            }

            unset($stockProductUnit->warehouse_id);
            unset($stockProductUnit->product_unit_id);

            unset($productUnit->product);
            unset($productUnit->uom_id);
            unset($productUnit->product_id);
        });

        return response()->json($stockProductUnits);
    }
}
