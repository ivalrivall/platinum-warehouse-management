<?php
namespace App\Pipes\Order;

use App\Models\SalesOrder;

class FillOrderAttributes
{
    public function handle(SalesOrder $salesOrder, \Closure $next)
    {
        $rawSoruce = $salesOrder->raw_source;

        $salesOrder->expected_price = empty($rawSoruce['expected_price']) ? null : $rawSoruce['expected_price'];
        $salesOrder->reseller_id = $rawSoruce['reseller_id'];
        $salesOrder->warehouse_id = $rawSoruce['warehouse_id'];
        $salesOrder->invoice_no = isset($rawSoruce['invoice_no']) ? $rawSoruce['invoice_no'] : null;
        $salesOrder->transaction_date = isset($rawSoruce['transaction_date']) ? $rawSoruce['transaction_date'] : now();
        $salesOrder->shipment_estimation_datetime = isset($rawSoruce['shipment_estimation_datetime']) ? $rawSoruce['shipment_estimation_datetime'] : now();
        $salesOrder->shipment_fee = $rawSoruce['shipment_fee'];
        $salesOrder->additional_discount = $rawSoruce['additional_discount'];
        $salesOrder->description = isset($rawSoruce['description']) ? $rawSoruce['description'] : null;

        return $next($salesOrder);
    }
}
