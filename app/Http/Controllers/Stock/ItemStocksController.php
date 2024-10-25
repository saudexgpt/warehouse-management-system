<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Invoice\InvoiceItem;
use App\Models\Invoice\WaybillItem;
use App\Models\Stock\ExpiredProduct;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStock;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Stock\StockCount;
use App\Models\Warehouse\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ItemStocksController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // $date_from = Carbon::now()->startOfMonth();
        // $date_to = Carbon::now()->endOfMonth();
        // $panel = 'month';
        $itemsInStockQuery = ItemStockSubBatch::query();
        if (isset($request->from, $request->to) && $request->from != '' && $request->to != '') {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';

            $itemsInStockQuery->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to);
        }
        $date = date('Y-m-d', strtotime('now'));
        $warehouse_id = $request->warehouse_id;
        $items_in_stock = $itemsInStockQuery->with([
            'warehouse',
            'item' => function ($q) {
                $q->orderBy('name');
            },
            'stocker',
            'confirmer'
        ])->where('warehouse_id', $warehouse_id)->where(function ($q) {
            $q->where('balance', '>', '0');
            // $q->orWhere('in_transit', '>', '0');
        })->where('expiry_date', '>=', $date)
            ->orderBy('expiry_date')->get();

        $expired_products = ItemStockSubBatch::with([
            'warehouse',
            'item' => function ($q) {
                $q->orderBy('name');
            },
            'stocker'
        ])->where('warehouse_id', $warehouse_id)->whereRaw('balance > 0')->where('expiry_date', '<', $date)
            ->orderBy('expiry_date')->get();

        // $expired_products = ExpiredProduct::with(['item'])->groupBy(['batch_no'])->where('warehouse_id', $warehouse_id)->select('*', \DB::raw('SUM(quantity) as quantity'))->get();
        // $items_in_stock = ItemStockSubBatch::with(['warehouse', 'item' => function ($q) {
        //     $q->orderBy('name');
        // }, 'stocker', 'confirmer'])->where('warehouse_id', $warehouse_id)->where(function ($q) {
        //     $q->where('balance', '>', '0');
        //     // $q->orWhere('in_transit', '>', '0');
        // })->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->orderBy('expiry_date')->get();

        // $expired_products = ExpiredProduct::with(['item'])->groupBy(['batch_no'])->where('warehouse_id', $warehouse_id)->where('expiry_date', '>=', $date_from)->where('expiry_date', '<=', $date_to)->select('*', \DB::raw('SUM(quantity) as quantity'))->get();
        return response()->json(compact('items_in_stock', 'expired_products'));
    }
    public function productBatches(Request $request)
    {
        //
        $warehouse_id = $request->warehouse_id;
        $item_id = $request->item_id;
        // $batches_of_items_in_stock = ItemStockSubBatch::with(['confirmer', 'stocker'])->where(['warehouse_id' => $warehouse_id, 'item_id' => $item_id])->whereRaw('balance - reserved_for_supply > 0')->whereRaw('confirmed_by IS NOT NULL')->orderBy('expiry_date')->get();

        $total_balance = ItemStockSubBatch::groupBy('item_id')
            ->where('warehouse_id', '!=', 7)
            ->where('item_id', $item_id)
            ->whereRaw('confirmed_by IS NOT NULL')
            ->select(\DB::raw('(SUM(balance) -  SUM(reserved_for_supply)) as total_balance'))
            ->first();

        // this is the quantity of products reserved for supply at the point of waybill generation
        // $total_invoiced_quantity = ItemStockSubBatch::groupBy('item_id')->where(['warehouse_id' => $warehouse_id, 'item_id' => $item_id])->whereRaw('confirmed_by IS NOT NULL')->select(\DB::raw('SUM(reserved_for_supply) as total_invoiced'))->first();

        // $total_invoiced_quantity = WaybillItem::groupBy('item_id')->where(['warehouse_id' => $warehouse_id, 'item_id' => $item_id])->whereRaw('quantity - remitted - quantity_reversed > 0')->select(\DB::raw('SUM(quantity - remitted - quantity_reversed) as total_invoiced'))->first();

        return response()->json(compact(/*'batches_of_items_in_stock', */ 'total_balance', /*'total_invoiced_quantity'*/), 200);
    }


    public function productStockBalanceByExpiryDate(Request $request)
    {

        $warehouse_id = $request->warehouse_id;
        $item_id = $request->item_id;
        $batches_of_items_in_stock = ItemStockSubBatch::where(['warehouse_id' => $warehouse_id, 'item_id' => $item_id])
            ->whereRaw('balance - reserved_for_supply > 0')
            ->select('*', \DB::raw('(balance - reserved_for_supply) as balance'))
            ->orderBy('expiry_date')
            ->get();
        return response()->json(compact('batches_of_items_in_stock'));
    }



    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Stock\ItemStock  $item_in_stock
     * @return \Illuminate\Http\Response
     */
    public function show(ItemStockSubBatch $item_in_stock)
    {
        //
        $item_in_stock = $item_in_stock->with(['warehouse', 'item', 'stocker', 'confirmer'])->find($item_in_stock->id);
        return response()->json(compact('item_in_stock'), 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $sub_batches = $request->sub_batches;

        list($item_stock_sub_batches, $unsaved_products) = $this->createSubBatches($request, $sub_batches, 0);
        return response()->json(compact('item_stock_sub_batches', 'unsaved_products'), 200);
    }
    public function moveExpiredProducts(Request $request)
    {
        $user = $this->getUser();
        $id = $request->id;
        $quantity = $request->quantity;
        $item_in_stock = ItemStockSubBatch::find($id);
        $item_in_stock->reserved_for_supply = 0;
        $item_in_stock->balance = 0;
        $item_in_stock->expired = $quantity;
        $item_in_stock->save();
        $request->warehouse_id = 8; // This is expired warehouse
        $request->confirmed_by = $user->id;
        $data = [$request];
        list($item_stock_sub_batches, $unsaved_products) = $this->createSubBatches($request, $data, 1);
        return response()->json(compact('item_stock_sub_batches', 'unsaved_products'), 200);
    }
    public function uploadBulkProductsInStock(Request $request)
    {
        $user = $this->getUser();
        $bulk_data = json_decode(json_encode($request->bulk_data));
        //return $bulk_data;
        $warehouse_id = $request->warehouse_id;

        $unsaved_products = [];
        $items_stocked = [];
        foreach ($bulk_data as $data) {
            try {
                $product = trim($data->PRODUCT);
                $item = Item::where('name', $product)->first();
                if ($item) {
                    $item_id = $item->id;
                    // $goods_received_note =  $data->GRN;
                    $expiry_date = $data->EXPIRY_DATE;
                    $batch_no = trim($data->BATCH_NO);
                    $quantity = trim($data->QUANTITY);
                    $sub_batch_no = $batch_no;
                    if (isset($data->SUB_BATCH_NO)) {
                        $sub_batch_no = $data->SUB_BATCH_NO;
                    }
                    $item_stock_sub_batch = new ItemStockSubBatch();
                    $item_stock_sub_batch->stocked_by = $user->id;
                    $item_stock_sub_batch->warehouse_id = $warehouse_id;
                    $item_stock_sub_batch->item_id = $item_id;
                    $item_stock_sub_batch->batch_no = $batch_no;
                    $item_stock_sub_batch->sub_batch_no = $sub_batch_no;
                    $item_stock_sub_batch->quantity = $quantity;
                    $item_stock_sub_batch->reserved_for_supply = 0;
                    $item_stock_sub_batch->in_transit = 0; // initial values set to zero
                    $item_stock_sub_batch->supplied = 0;
                    $item_stock_sub_batch->balance = $quantity;
                    // $item_stock_sub_batch->goods_received_note = $goods_received_note;
                    $month = date('m', strtotime($expiry_date));
                    $year = date('Y', strtotime($expiry_date));
                    $no_of_days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                    $item_stock_sub_batch->expiry_date = $year . '-' . $month . '-' . $no_of_days_in_month;
                    $item_stock_sub_batch->save();

                    $items_stocked[] = $this->show($item_stock_sub_batch);



                    // $title = "Bulk upload of products";
                    // $description = "Products were added in bulk to stock at " . $item_stock_sub_batch->warehouse->name . " by " . $user->name;
                    // $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
                    // $this->logUserActivity($title, $description, $roles);
                } else {
                    $unsaved_products[] = $data;
                }
            } catch (\Throwable $th) {
                $unsaved_products[] = $data;
                // return $th; //'An error occured in the file. Check for duplicate entries in Batch No and GRN';
            }
        }
        return response()->json(compact('unsaved_products', 'items_stocked'), 200);



        // if ($request->file('bulk_csv_file') != null && $request->file('bulk_csv_file')->isValid()) {

        //     $bulk_csv_file = $request->file('bulk_csv_file');


        //     if ($bulk_csv_file->getClientOriginalExtension() == 'csv') {

        //         $path = $bulk_csv_file->getRealPath();

        //         $csvAsArray = array_map('str_getcsv', file($path));

        //         /* Map Rows and Loop Through Them */
        //         $header = array_shift($csvAsArray);
        //         $csv    = array();
        //         foreach ($csvAsArray as $row) {
        //             $csv[] = array_combine($header, $row);
        //         }


        //         foreach ($csv as $csvRow) {

        //             $product_name = trim($csvRow['PRODUCT']);
        //             $item = Item::where('name', $product_name)->first();
        //             $request->item_id = $item->id;
        //             $request->batch_no = trim($csvRow['BATCH_NO']);
        //             $request->sub_batch_no = trim($csvRow['SUB_BATCH_NO']);
        //             $request->quantity = trim($csvRow['QUANTITY']);
        //             $request->expiry_date = trim(date('Y-m-d', strtotime($csvRow['EXPIRY_DATE'])));
        //             $request->goods_received_note = trim($csvRow['GRN']);

        //             //store the entry for this student
        //             $this->store($request, 'no_redirect');
        //         }
        //         $action = "registered students in bulk via .csv upload ";

        //         $message = 'Bulk upload successfully';
        //     } else {
        //         $message = 'Please Upload .csv file';
        //     }
        // } else {
        //     $message = 'Invalid File';
        // }
    }
    private function createSubBatches($request, $sub_batches, $is_warehouse_transfered)
    {
        $user = $this->getUser();
        $item_stock_sub_batches = [];
        $unsaved_products = [];
        foreach ($sub_batches as $batch) {
            $item_stock_sub_batch = new ItemStockSubBatch();
            $item_stock_sub_batch->stocked_by = $user->id;
            $item_stock_sub_batch->warehouse_id = $request->warehouse_id;
            $item_stock_sub_batch->expired_from = (isset($request->expired_from)) ? $request->expired_from : NULL;
            $item_stock_sub_batch->confirmed_by = (isset($request->confirmed_by)) ? $request->confirmed_by : NULL;

            $item_stock_sub_batch->item_id = $batch['item_id'];
            $item_stock_sub_batch->batch_no = $batch['batch_no'];
            $item_stock_sub_batch->sub_batch_no = $batch['batch_no'];
            $item_stock_sub_batch->quantity = $batch['quantity'];
            $item_stock_sub_batch->reserved_for_supply = 0;
            $item_stock_sub_batch->in_transit = 0; // initial values set to zero
            $item_stock_sub_batch->supplied = 0;
            $item_stock_sub_batch->balance = $batch['quantity'];
            $item_stock_sub_batch->goods_received_note = $batch['goods_received_note'];
            $item_stock_sub_batch->expiry_date = date('Y-m-d', strtotime($batch['expiry_date']));
            $item_stock_sub_batch->is_warehouse_transfered = $is_warehouse_transfered;
            if ($item_stock_sub_batch->save()) {

                $item_stock_sub_batches[] = $item_stock_sub_batch;

                // log this event
                $title = "Product added to stock";
                $description = $item_stock_sub_batch->quantity . " " . $item_stock_sub_batch->item->name . " was added to stock at " . $item_stock_sub_batch->warehouse->name . " by " . $user->name;
                $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
                $this->logUserActivity($title, $description, $roles);
            } else {
                $unsaved_products[] = $batch;
            }
        }
        return array($item_stock_sub_batches, $unsaved_products);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Stock\ItemStock  $itemStock
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ItemStockSubBatch $item_in_stock)
    {
        //
        $user = $this->getUser();
        $initial_stock = $item_in_stock->quantity;
        $supplied = $item_in_stock->in_transit + $item_in_stock->supplied;
        // if ($initial_stock <= $request->quantity) {
        //     $difference_in_stock = $request->quantity - $initial_stock;
        //     $item_in_stock->quantity = $request->quantity;
        //     $item_in_stock->balance += $difference_in_stock;
        // }
        $old_name = $item_in_stock->item->name;
        $old_batch = $item_in_stock->batch_no;
        $old_warehouse = $item_in_stock->warehouse->name;
        $old_quantity = $item_in_stock->quantity;
        $old_balance = $item_in_stock->balance;
        $old_entry = "Item: $old_name, Batch No: $old_batch, Warehouse: $old_warehouse, Stock Quantity: $old_quantity, Balance: $old_balance.";

        if ($item_in_stock->confirmed_by == NULL) {
            if ($supplied == 0) {
                $item_in_stock->quantity = $request->quantity;
                $item_in_stock->balance = $request->quantity;
            } else {

                $difference_in_stock = $request->quantity - $initial_stock;
                $item_in_stock->quantity = $request->quantity;
                $item_in_stock->balance += $difference_in_stock;
            }
        }
        $item_in_stock->warehouse_id = $request->warehouse_id;
        $item_in_stock->item_id = $request->item_id;
        $item_in_stock->batch_no = $request->batch_no;
        $item_in_stock->expiry_date = date('Y-m-d', strtotime($request->expiry_date));
        $item_in_stock->save();

        // }
        $new_name = $item_in_stock->item->name;
        $new_batch = $item_in_stock->batch_no;
        $new_warehouse = $item_in_stock->warehouse->name;
        $new_quantity = $item_in_stock->quantity;
        $new_balance = $item_in_stock->balance;
        $new_entry = "Item: $new_name, Batch No: $new_batch, Warehouse: $new_warehouse, Stock Quantity: $new_quantity, Balance: $new_balance.";
        // log this event
        $title = 'Product in stock updated by ' . $user->name;
        $description = "Compare the old entry [$old_entry] with the updated entry [$new_entry]";
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($item_in_stock);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Stock\ItemStock  $itemStock
     * @return \Illuminate\Http\Response
     */
    public function destroy(ItemStockSubBatch $item_sub_stock)
    {
        //
        if ($item_sub_stock->confirmed_by == 0 && $item_sub_stock->in_transit == 0 && $item_sub_stock->supplied == 0 && $item_sub_stock->confirmed_by == NULL) {

            $item_sub_stock->delete();

            return response()->json(null, 204);
        }
        return response()->json('Entry cannot be deleted. Some transactions are tied to it', 500);
    }
    public function fetchStockCounts(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $date = date('Y-m-d', strtotime($request->date));
        $stock_counts = StockCount::with('item', 'counter')->where(['warehouse_id' => $warehouse_id, 'date' => $date])->get();
        return response()->json(compact('stock_counts'), 200);
    }
    public function prepareStockCount(Request $request)
    {
        $date = date('Y-m', strtotime($request->date));
        $warehouse_id = $request->warehouse_id;
        // $items_id = $request->item_ids;
        $item_batch_query = ItemStockSubBatch::query();
        $item_batch_query->groupBy('item_id')->where('warehouse_id', $warehouse_id)->select('*', \DB::raw('SUM(balance) as total_balance'));
        // if ($items_id !== 'all') {
        //     $item_batch_query->whereIn('item_id', $items_id);
        // }

        $item_batch_query->chunk(200, function (Collection $batches) use ($date) {
            foreach ($batches as $batch) {
                StockCount::updateOrCreate(
                    ['warehouse_id' => $batch->warehouse_id, 'item_id' => $batch->item_id, 'date' => $date],
                    ['stock_balance' => $batch->total_balance]
                );
                // $stock_count = new StockCount();
                // $stock_count->warehouse_id = $batch->warehouse_id;
                // $stock_count->item_id = $batch->item_id;
                // $stock_count->batch_no = $batch->batch_no;
                // $stock_count->expiry_date = $batch->expiry_date;
                // $stock_count->stock_balance = $batch->total_balance;
                // $stock_count->save();
            }
        });
        $stock_counts = StockCount::with('item')->where('warehouse_id', $warehouse_id)->where('date', $date)->get();
        return response()->json(compact('stock_counts'), 200);
    }
    public function countStock(Request $request, StockCount $stock_count)
    {
        $user = $this->getUser();
        $stock_count->count_by = $user->id;
        $stock_count->count_quantity = $request->count_quantity;
        $stock_count->save();
        return 'done';
    }
    public function saveStockCount(Request $request)
    {
        $sub_batches = json_decode(json_encode($request->sub_batches));
        $user = $this->getUser();
        $warehouse_id = $request->warehouse_id;
        $date = date('Y-m-d', strtotime($request->date));
        foreach ($sub_batches as $batch) {
            $item_id = $batch->item_id;
            $batch_no = $batch->batch_no;
            $quantity = $batch->quantity;
            $expiry_date = date('Y-m-d', strtotime($batch->expiry_date));
            StockCount::updateOrCreate(
                ['warehouse_id' => $warehouse_id, 'item_id' => $item_id, 'batch_no' => $batch_no, 'date' => $date, 'expiry_date' => $expiry_date,],
                ['count_quantity' => $quantity, 'count_by' => $user->id]
            );
        }
        return response()->json([], 204);
    }
}
