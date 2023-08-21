<?php

namespace App\Models\Stock;

use App\Laravue\Models\User;
use App\Models\Warehouse\Warehouse;
// use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    // use HasFactory;
    protected $fillable = [
        'warehouse_id', 'item_id', 'date', 'stock_balance', 'count_quantity', 'batch_no', 'expiry_date', 'count_by'

    ];
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function counter()
    {
        return $this->belongsTo(User::class, 'count_by', 'id');
    }
}
