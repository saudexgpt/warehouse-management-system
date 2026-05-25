<?php

namespace App\Models\Invoice;

use App\Models\Stock\ItemStock;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Warehouse\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DispatchedProduct extends Model
{
    //
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'customer_id',
        'item_stock_sub_batch_id',
        'waybill_id',
        'waybill_item_id',
        'invoice_id',
        'invoice_item_id',
        'item_id',
        'quantity_supplied',
        'remitted',
        'status',
        'on transit'
    ];
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function waybill()
    {
        return $this->belongsTo(Waybill::class);
    }
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }
    public function waybillItem()
    {
        return $this->belongsTo(WaybillItem::class);
    }
    public function itemStock()
    {
        return $this->belongsTo(ItemStockSubBatch::class, 'item_stock_sub_batch_id', 'id');
    }
}
