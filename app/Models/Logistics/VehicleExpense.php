<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class VehicleExpense extends Model
{
    use SoftDeletes;
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
    public function engineer()
    {
        return $this->belongsTo(AutomobileEngineer::class);
    }
    public function expenseDetails()
    {
        return $this->hasMany(VehicleExpenseDetail::class);
    }
}
