<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockOpnameDetailStoreRequest;
use App\Http\Resources\StockOpnameDetailResource;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Models\StockProductUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class StockOpnameDetailController extends Controller
{
    public function index(StockOpname $stockOpname)
    {
        abort_if(!auth()->user()->tokenCan('stock_opname_details_access'), 403);

        $query = StockOpnameDetail::where('stock_opname_id', $stockOpname->id)
            ->with('stockProductUnit.productUnit')
            ->withCount([
                'stockOpnameItems',
                'stockOpnameItems as total_adjust_qty' => fn ($q) => $q->where('is_scanned', 1)
            ]);

        $stockOpnameDetails = QueryBuilder::for($query)
            // ->allowedFilters(['description'])
            ->allowedSorts(['id', 'created_at'])
            ->allowedIncludes('stockOpname')
            ->paginate();

        return StockOpnameDetailResource::collection($stockOpnameDetails);
    }

    public function show(StockOpname $stockOpname, $stockOpnameDetailId)
    {
        abort_if(!auth()->user()->tokenCan('stock_opname_detail_create'), 403);

        $stockOpnameDetail = $stockOpname->details()->where('id', $stockOpnameDetailId)
            ->with(['stockOpname', 'stockProductUnit.productUnit'])
            ->withCount([
                'stockOpnameItems',
                'stockOpnameItems as total_adjust_qty' => fn ($q) => $q->where('is_scanned', 1)
            ])
            ->firstOrFail();

        return new StockOpnameDetailResource($stockOpnameDetail);
    }

    public function store(StockOpname $stockOpname, StockOpnameDetailStoreRequest $request)
    {
        $stockProductUnit = StockProductUnit::select('id')
            ->where('warehouse_id', $stockOpname->warehouse_id)
            ->where('product_unit_id', $request->product_unit_id)
            ->firstOrFail();

        $stockOpnameDetail = $stockOpname->details()->create([
            'stock_product_unit_id' => $stockProductUnit->id,
            'qty' => $stockProductUnit->stocks->count() ?? 0,
        ]);

        return new StockOpnameDetailResource($stockOpnameDetail);
    }

    public function update(StockOpname $stockOpname, $stockOpnameDetailId, Request $request)
    {
        $request->validate([
            'stock_id' => 'required|exists:stocks,id'
        ]);

        $stockOpnameDetail = $stockOpname->details()->where('id', $stockOpnameDetailId)->firstOrFail();

        $stockOpnameItem = $stockOpnameDetail->stockOpnameItems()->where('stock_id', $request->stock_id)->firstOrFail();

        $isScanned = $request->is_scanned ?? 1;
        $stockOpnameItem->update(['is_scanned' => $isScanned]);

        return response()->json(['message' => 'Stock scanned successfully'], Response::HTTP_ACCEPTED);
    }

    // public function destroy(StockOpname $stockOpname, $stockOpnameDetailId)
    // {
    //     abort_if(!auth()->user()->tokenCan('stock_opname_detail_delete'), 403);
    //     $stockOpname->details()->where('id', $stockOpnameDetailId)->delete();
    //     return $this->deletedResponse();
    // }
}
