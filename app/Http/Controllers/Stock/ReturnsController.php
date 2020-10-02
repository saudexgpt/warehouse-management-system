<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\ReturnedProduct;
use Illuminate\Http\Request;

class ReturnsController extends Controller
{
    //
    public function index(Request $request)
    {
        //
        $warehouse_id = $request->warehouse_id;
        $returned_products = ReturnedProduct::with(['warehouse', 'item', 'stocker', 'confirmer'])->where('warehouse_id', $warehouse_id)->orderBy('id', 'DESC')->get();

        // $items_in_stock = ItemStock::with(['warehouse', 'item'])->groupBy('item_id')->having('warehouse_id', $warehouse_id)
        // ->select('*',\DB::raw('SUM(quantity) as total_quantity'))->get();
        return response()->json(compact('returned_products'));
    }
    public function store(Request $request)
    {
        //
        $user = $this->getUser();

        $returned_product = new ReturnedProduct();
        $returned_product->warehouse_id  = $request->warehouse_id;
        $returned_product->item_id       = $request->item_id;
        $returned_product->batch_no      = $request->batch_no;
        $returned_product->customer_name = $request->customer_name;
        $returned_product->expiry_date   = $request->expiry_date;
        $returned_product->date_returned   = $request->date_returned;
        $returned_product->quantity      = $request->quantity;
        $returned_product->reason        = $request->reason;
        if ($request->reason == 'Others' && $request->other_reason != null) {
            $returned_product->reason  = $request->other_reason;
        }

        $returned_product->stocked_by      = $user->id;
        $returned_product->save();

        $title = "Products returned";
        $description = "Products were returned to " . $returned_product->warehouse->name . " by  $returned_product->customer_name. Details created by $user->name ($user->email)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($returned_product);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Stock\ItemStock  $itemStock
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ReturnedProduct $returned_product)
    {
        //
        $returned_product->item_id       = $request->item_id;
        $returned_product->batch_no      = $request->batch_no;
        $returned_product->customer_name = $request->customer_name;
        $returned_product->expiry_date   = $request->expiry_date;
        $returned_product->date_returned = $request->date_returned;
        $returned_product->quantity      = $request->quantity;
        $returned_product->reason        = $request->reason;
        if ($request->reason == 'Others' && $request->other_reason != null) {
            $returned_product->reason  = $request->other_reason;
        }

        $returned_product->save();

        $user = $this->getUser();
        $title = "Returned products updated";
        $description = "Product returned with entry id: " . $returned_product->id . " was modified by $user->name ($user->email)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($returned_product);
    }
    public function show(ReturnedProduct $returned_product)
    {

        $returned_product = $returned_product->with(['warehouse', 'item', 'stocker', 'confirmer'])->find($returned_product->id);
        return response()->json(compact('returned_product'), 200);
    }
}
