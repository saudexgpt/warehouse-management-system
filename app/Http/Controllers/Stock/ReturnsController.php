<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Stock\ReturnedProduct;
use App\Models\Stock\StockReturn;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReturnsController extends Controller
{

    public function index(Request $request)
    {
        //
        $warehouse_id = $request->warehouse_id;
        // $returned_products = ReturnedProduct::with(['warehouse', 'stockReturn', 'item', 'stocker', 'confirmer'])
        //     ->where('warehouse_id', $warehouse_id)
        //     ->whereRaw('quantity > quantity_approved')
        //     ->orderBy('id', 'DESC')
        //     ->paginate($request->limit);
        $returned_products = StockReturn::with([
            'products' => function ($q) {
                $q->whereRaw('quantity > quantity_approved');
            },
            'products.item',
            'products.confirmer',
            'auditor',
            'customer',
            'stocker'
        ])
            ->where('warehouse_id', $warehouse_id)
            ->whereHas('products', function ($query) {
                $query->whereRaw('quantity > quantity_approved');
            })
            // ->whereRaw('quantity > quantity_approved')
            ->orderBy('id', 'DESC')
            ->paginate($request->limit);
        return response()->json(compact('returned_products'));
    }
    // public function index(Request $request)
    // {
    //     //
    //     $warehouse_id = $request->warehouse_id;
    //     $returned_products = ReturnedProduct::with(['warehouse', 'stockReturn', 'item', 'stocker', 'confirmer'])
    //     ->where('warehouse_id', $warehouse_id)
    //     ->whereRaw('quantity > quantity_approved')
    //     ->orderBy('id', 'DESC')
    //     ->get();
    //     return response()->json(compact('returned_products'));
    // }
    public function approvedReturnedProducts(Request $request)
    {
        //
        $warehouse_id = $request->warehouse_id;
        $is_download = 'no';
        if (isset($request->is_download)) {
            $is_download = $request->is_download;
        }
        if ($is_download == 'yes') {
            $date_from = Carbon::now()->startOfMonth();
            $date_to = Carbon::now()->endOfMonth();
            $panel = 'month';
            if (isset($request->from, $request->to)) {
                $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
                $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
                $panel = $request->panel;
            }
            // $returned_products = StockReturn::with([
            //     'products' => function ($q) {
            //         $q->whereRaw('quantity = quantity_approved');
            //     },
            //     'products.item',
            //     'products.confirmer',
            //     'customer',
            //     'stocker'
            // ])
            //     ->where('warehouse_id', $warehouse_id)
            //     ->whereHas('products', function ($query) {
            //         $query->whereRaw('quantity = quantity_approved');
            //     })
            //     // ->whereRaw('quantity > quantity_approved')
            //     ->orderBy('id', 'DESC')
            //     ->get();
            $returned_products = ReturnedProduct::with(['warehouse', 'stockReturn', 'item', 'stocker', 'confirmer', 'auditor'])
                ->where('warehouse_id', $warehouse_id)
                ->whereRaw('quantity = quantity_approved')
                ->where('created_at', '>=', $date_from)
                ->where('created_at', '<=', $date_to)
                ->orderBy('id', 'DESC')
                ->get();
        } else {
            $returned_products = StockReturn::with([
                'products' => function ($q) {
                    $q->whereRaw('quantity = quantity_approved');
                },
                'products.item',
                'products.confirmer',
                'products.auditor',
                'customer',
                'stocker'
            ])
                ->where('warehouse_id', $warehouse_id)
                ->whereHas('products', function ($query) {
                    $query->whereRaw('quantity = quantity_approved');
                })
                // ->whereRaw('quantity > quantity_approved')
                ->orderBy('id', 'DESC')
                ->paginate($request->limit);
            // $returned_products = ReturnedProduct::with(['warehouse', 'stockReturn', 'item', 'stocker', 'confirmer'])
            //     ->where('warehouse_id', $warehouse_id)
            //     ->whereRaw('quantity = quantity_approved')
            //     ->orderBy('id', 'DESC')
            //     ->paginate($request->limit);
        }
        // $items_in_stock = ItemStock::with(['warehouse', 'item'])->groupBy('item_id')->having('warehouse_id', $warehouse_id)
        // ->select('*',\DB::raw('SUM(quantity) as total_quantity'))->get();
        return response()->json(compact('returned_products'));
    }
    public function fetchProductBatches(Request $request)
    {
        $item_id = $request->item_id;
        $batches = ItemStockSubBatch::groupBy('batch_no')
            ->where('warehouse_id', '!=', 7)
            ->where('item_id', $item_id)
            ->whereRaw('balance < quantity')
            ->whereRaw('confirmed_by IS NOT NULL')
            ->get();

        return response()->json(compact('batches'), 200);
    }
    public function store(Request $request)
    {
        $user = $this->getUser();
        $stock_return = new StockReturn();
        $stock_return->warehouse_id = $request->warehouse_id;
        $stock_return->customer_id = $request->customer_id;
        $stock_return->customer_name = $request->customer_name;
        $stock_return->returns_no = 'RTN-' . $request->customer_id . randomcode();
        $stock_return->date_returned = $request->date_returned;
        $stock_return->stocked_by = $user->id;
        $stock_return->save();

        $returns_items = json_decode(json_encode($request->returns_items));

        foreach ($returns_items as $returns_item) {
            $returned_product = new ReturnedProduct();
            $returned_product->warehouse_id = $request->warehouse_id;
            $returned_product->return_id = $stock_return->id;
            $returned_product->item_id = $returns_item->item_id;
            $returned_product->price = $returns_item->price;
            $returned_product->batch_no = $returns_item->batch_no;
            $returned_product->customer_id = $request->customer_id;
            $returned_product->customer_name = $request->customer_name;
            $returned_product->expiry_date = $returns_item->expiry_date;
            $returned_product->date_returned = $request->date_returned;
            $returned_product->quantity = (int) $returns_item->quantity;
            $returned_product->reason = $returns_item->reason;
            if ($returns_item->reason == 'Others' && $returns_item->other_reason != null) {
                $returned_product->reason = $returns_item->other_reason;
            }

            $returned_product->stocked_by = $user->id;
            $returned_product->save();
        }
        // $returned_product = new ReturnedProduct();
        // $returned_product->warehouse_id = $request->warehouse_id;
        // $returned_product->item_id = $request->item_id;
        // $returned_product->batch_no = $request->batch_no;
        // $returned_product->customer_id = $request->customer_id;
        // $returned_product->customer_name = $request->customer_name;
        // $returned_product->expiry_date = $request->expiry_date;
        // $returned_product->date_returned = $request->date_returned;
        // $returned_product->quantity = $request->quantity;
        // $returned_product->reason = $request->reason;
        // if ($request->reason == 'Others' && $request->other_reason != null) {
        //     $returned_product->reason = $request->other_reason;
        // }

        // $returned_product->stocked_by = $user->id;
        // $returned_product->save();

        // $title = "Products returned";
        // $description = "Products were returned to " . $returned_product->warehouse->name . " by  $returned_product->customer_name. Details created by $user->name ($user->email)";
        // //log this activity
        // $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
        // $this->logUserActivity($title, $description, $roles);
        // return $this->show($returned_product);
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
        $returned_product->item_id = $request->item_id;
        $returned_product->price = $request->price;
        $returned_product->batch_no = $request->batch_no;
        $returned_product->customer_name = $request->customer_name;
        $returned_product->expiry_date = $request->expiry_date;
        $returned_product->date_returned = $request->date_returned;
        $returned_product->quantity = $request->quantity;
        $returned_product->reason = $request->reason;
        if ($request->reason == 'Others' && $request->other_reason != null) {
            $returned_product->reason = $request->other_reason;
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

    public function approveReturnedProducts(Request $request)
    {
        // Virtual Warehouse ID is 7
        $user = $this->getUser();
        $warehouse_id = 7;
        $approved_quantity = $request->approved_quantity;
        $details = json_decode(json_encode($request->product_details));
        // update the approved quantity
        $returned_prod = ReturnedProduct::find($details->id);
        $returned_prod->quantity_approved += $approved_quantity;
        if ($returned_prod->quantity >= $returned_prod->quantity_approved) {
            $returned_prod->save();
        }
        $item_stock_sub_batch = new ItemStockSubBatch();
        $item_stock_sub_batch->stocked_by = $user->id;
        $item_stock_sub_batch->warehouse_id = $warehouse_id;
        $item_stock_sub_batch->item_id = $details->item_id;
        $item_stock_sub_batch->price = $details->price;
        $item_stock_sub_batch->batch_no = $details->batch_no;
        $item_stock_sub_batch->sub_batch_no = $details->batch_no;
        $item_stock_sub_batch->quantity = $approved_quantity;
        $item_stock_sub_batch->reserved_for_supply = 0;
        $item_stock_sub_batch->in_transit = 0; // initial values set to zero
        $item_stock_sub_batch->supplied = 0;
        $item_stock_sub_batch->balance = $approved_quantity;
        $item_stock_sub_batch->comments = "Returned by $returned_prod->customer_name on $returned_prod->date_returned. Reason: $returned_prod->reason. Approved By: $user->name";
        // $item_stock_sub_batch->goods_received_note = ($details->grn) ? $details->grn : $details->batch_no;
        $item_stock_sub_batch->expiry_date = date('Y-m-d', strtotime($details->expiry_date));
        $item_stock_sub_batch->save();




        $title = "Returned products approval";
        $description = "Product returned with entry batch no: " . $returned_prod->batch_no . " was approved by $user->name ($user->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($returned_prod);
    }

    public function auditorCommentOnReturnedProducts(Request $request, StockReturn $stockReturn)
    {
        // This method is for the auditor to comment on returned products
        $user = $this->getUser();
        $stockReturn->auditor_comment = $request->comment;
        $stockReturn->audited_by = $user->id;
        $stockReturn->save();

        $title = "Auditor's comment on returned products";
        $description = "Auditor commented on returned product with number: " . $stockReturn->returns_no . " by $user->name ($user->email)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['message' => 'Comment submitted successfully'], 200);
    }
}
