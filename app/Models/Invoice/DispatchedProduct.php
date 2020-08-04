<?php

namespace App\Models\Invoice;

use App\Models\Stock\ItemStock;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Warehouse\Warehouse;
use Illuminate\Database\Eloquent\Model;

class DispatchedProduct extends Model
{
    //
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function waybill()
    {
        return $this->belongsTo(Waybill::class);
    }
    public function itemStock()
    {
        return $this->belongsTo(ItemStockSubBatch::class, 'item_stock_sub_batch_id', 'id');
    }
}
