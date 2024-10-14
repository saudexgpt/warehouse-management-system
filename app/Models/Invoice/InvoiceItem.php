<?php

namespace App\Models\Invoice;

use App\Models\Stock\Item;
use App\Models\Stock\ItemStock;
use App\Models\Warehouse\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    //
    protected $fillable = [
        'warehouse_id',
        'invoice_id',
        'supply_status'

    ];
    use SoftDeletes;
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function dispatchProducts()
    {
        return $this->hasMany(DispatchedProduct::class);
    }
    public function batches()
    {
        return $this->hasMany(InvoiceItemBatch::class);
    }
    public function waybillItems()
    {
        return $this->hasMany(WaybillItem::class);
    }
    public function firstWaybillItem()
    {
        return $this->hasOne(WaybillItem::class);
    }

    public function updateInvoiceItemsForWaybill($waybill_items, $status)
    {
        foreach ($waybill_items as $waybill_item) {
            $invoice_item = $waybill_item->invoiceItem;
            if ($status != 'delivered') {
                $total_quantity_supplied = $invoice_item->quantity_supplied; // + $waybill_item->quantity;
                $invoice_item->supply_status = 'Complete';
                if ($total_quantity_supplied < $invoice_item->quantity) {
                    $invoice_item->supply_status = 'Partial';
                }
                // $invoice_item->quantity_supplied = $total_quantity_supplied;
            }
            $invoice_item->delivery_status = $status;
            $invoice_item->save();
        }
    }
}
