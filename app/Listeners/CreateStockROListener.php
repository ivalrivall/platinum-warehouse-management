<?php

namespace App\Listeners;

use App\Events\VerifiedROEvent;
use App\Models\StockProductUnit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CreateStockROListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\VerifiedROEvent  $event
     * @return void
     */
    public function handle(VerifiedROEvent $event)
    {
        $receiveOrder = $event->receiveOrder->load('details');
        $folder = 'qrcode/';

        foreach ($receiveOrder->details as $receiveOrderDetail) {
            $qty = $receiveOrderDetail->adjust_qty > 0 ? $receiveOrderDetail->adjust_qty : $receiveOrderDetail->qty;
            $stockProductUnit = StockProductUnit::where('warehouse_id', $receiveOrder->warehouse_id)
                ->where('product_unit_id', $receiveOrderDetail->product_unit_id)
                ->first();

            if ($stockProductUnit) {
                for ($i = 0; $i < $qty ?? 0; $i++) {
                    $stock = $stockProductUnit->stocks()->create([
                        'receive_order_id' => $receiveOrderDetail->receive_order_id,
                        'receive_order_detail_id' => $receiveOrderDetail->id,
                    ]);

                    $logo = public_path('images/logo-platinum.png');

                    $data = QrCode::size(350)
                        ->format('png')
                        // ->merge($logo, absolute: true)
                        ->generate($stock->id);

                    $fileName = $receiveOrderDetail->id . '/' . $stock->id . '.png';
                    $fullPath = $folder .  $fileName;
                    Storage::put($fullPath, $data);

                    $stock->update(['qr_code' => $fullPath]);
                }

                // create history
                $receiveOrderDetail->histories()->create([
                    'user_id' => auth()->user()->id,
                    'stock_product_unit_id' => $stockProductUnit->id,
                    'value' => $qty,
                    'is_increment' => 1,
                    'description' => $receiveOrder->invoice_no,
                    'ip' => request()->ip(),
                    'agent' => request()->header('user-agent'),
                ]);
            }
        }
    }
}
