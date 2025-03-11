<?php

namespace App\Models\Invoice;

use App\Models\Stock\Item;
use App\Models\Warehouse\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaybillItem extends Model
{
    use SoftDeletes;
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function waybill()
    {
        return $this->belongsTo(Waybill::class);
    }
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function batches()
    {
        return $this->hasMany(InvoiceItemBatch::class);
    }
    public function dispatchProduct()
    {
        return $this->hasOne(DispatchedProduct::class);
    }
    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function createWaybillItems($waybill, $warehouse_id, $invoice_item, $batches)
    {
        // foreach ($invoice_items as $invoice_item) {
        $for_supply = 0;
        foreach ($batches as $batch) {

            $for_supply += $batch->supply_quantity;
        }
        if ($for_supply > 0) {

            $waybill_item = new WaybillItem();
            $waybill_item->warehouse_id = $warehouse_id;
            $waybill_item->invoice_id = $invoice_item->invoice_id;
            $waybill_item->waybill_id = $waybill->id;
            $waybill_item->item_id = $invoice_item->item_id;
            $waybill_item->invoice_item_id = $invoice_item->id;
            $waybill_item->quantity = $for_supply;
            $waybill_item->type = $invoice_item->type;
            $waybill_item->save();

            $waybill->invoices()->syncWithoutDetaching($invoice_item->invoice_id);
            return $waybill_item;
        }
        // }
    }
}
