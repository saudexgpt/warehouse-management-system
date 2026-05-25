<?php

namespace App\Models\Invoice;

use App\Models\Stock\Item;
use App\Models\Warehouse\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaybillItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'invoice_id',
        'waybill_id',
        'item_id',
        'invoice_item_id',
        'quantity',
        'type',
        'remitted'
    ];
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
    public function dispatchProducts()
    {
        return $this->hasMany(DispatchedProduct::class);
    }
    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }


}
