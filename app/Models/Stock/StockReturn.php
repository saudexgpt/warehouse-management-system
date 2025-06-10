<?php

namespace App\Models\Stock;

use App\Customer;
use App\Laravue\Models\User;
use App\Models\Warehouse\Warehouse;
use Illuminate\Database\Eloquent\Model;

class StockReturn extends Model
{
    protected $table = 'stock_returns';
    //
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function products()
    {
        return $this->hasMany(ReturnedProduct::class, 'return_id', 'id');
    }
    public function stocker()
    {
        return $this->belongsTo(User::class, 'stocked_by', 'id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
