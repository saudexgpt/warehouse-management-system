<?php

namespace App\Models;

use App\Laravue\Models\User;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\Waybill;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Warehouse\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [

        'warehouse_id',
        'item_id',
        'item_stock_sub_batch_id',

        'invoice_id',
        'invoice_item_id',

        'waybill_id',
        'waybill_item_id',

        'movement_type',

        'quantity',

        'balance_before',
        'balance_after',

        'batch_no',
        'expiry_date',

        'remarks',

        'created_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function subBatch()
    {
        return $this->belongsTo(
            ItemStockSubBatch::class,
            'item_stock_sub_batch_id'
        );
    }

    public function waybill()
    {
        return $this->belongsTo(Waybill::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
