<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStock;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Warehouse\Warehouse;
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
        //
        $warehouse_id = $request->warehouse_id;
        // $items_in_stock = ItemStock::with(['warehouse', 'item', 'subBatches.stocker', 'subBatches.confirmer'])->where('warehouse_id', $warehouse_id)->orderBy('id', 'DESC')->get();

        $items_in_stock = ItemStockSubBatch::with(['warehouse', 'item', 'stocker', 'confirmer'])->where('warehouse_id', $warehouse_id)->orderBy('id', 'DESC')->get();

        // $items_in_stock = ItemStock::with(['warehouse', 'item'])->groupBy('item_id')->having('warehouse_id', $warehouse_id)
        // ->select('*',\DB::raw('SUM(quantity) as total_quantity'))->get();
        return response()->json(compact('items_in_stock'));
    }
    public function productBatches(Request $request)
    {
        //
        $warehouse_id = $request->warehouse_id;
        $item_id = $request->item_id;
        $batches_of_items_in_stock = ItemStockSubBatch::with(['confirmer', 'stocker'])->where(['warehouse_id' => $warehouse_id, 'item_id' => $item_id])->whereRaw('balance - reserved_for_supply > 0')->select('*', \DB::raw('(balance - reserved_for_supply) as balance'))->orderBy('expiry_date')->get();
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
        return $item_in_stock->with(['warehouse', 'item', 'stocker', 'confirmer'])->find($item_in_stock->id);
        // return response()->json(compact('item_in_stock'), 200);
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

        $item_stock_sub_batches = $this->createSubBatches($request, $sub_batches);
        return response()->json(compact('item_stock_sub_batches'), 200);
    }
    public function uploadBulkProductsInStock(Request $request)
    {
        $user = $this->getUser();
        $bulk_data = json_decode(json_encode($request->bulk_data));
        //return $bulk_data;
        $warehouse_id = $request->warehouse_id;
        // try {
        $unsaved_products = [];
        $items_stocked = [];
        foreach ($bulk_data as $data) {
            $product =  trim($data->PRODUCT);
            $item = Item::where('name', $product)->first();
            if ($item) {
                $item_id = $item->id;
                $quantity =  $data->QUANTITY;
                $goods_received_note =  $data->GRN;
                $expiry_date =  $data->EXPIRY_DATE;
                $batch_no =  $data->BATCH_NO;
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
                $item_stock_sub_batch->goods_received_note = $goods_received_note;
                $month = date('m', strtotime($expiry_date));
                $year = date('Y', strtotime($expiry_date));
                $no_of_days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $item_stock_sub_batch->expiry_date = $year . '-' . $month . '-' . $no_of_days_in_month;
                $item_stock_sub_batch->save();

                $items_stocked[] = $this->show($item_stock_sub_batch);
            } else {
                $unsaved_products[] = $data;
            }
            // print_r($unsaved_products);
        }

        $title = "Bulk upload of products";
        $description = "Products were added in bulk to stock at " . $item_stock_sub_batch->warehouse->name . " by " . $user->name;
        $this->logUserActivity($title, $description);
        return response()->json(compact('unsaved_products', 'items_stocked'), 200);
        // } catch (\Throwable $th) {
        //     return $th; //'An error occured in the file. Check for duplicate entries in Batch No and GRN';
        // }


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
    private function createSubBatches($request, $sub_batches)
    {
        $user = $this->getUser();
        $item_stock_sub_batches = [];
        foreach ($sub_batches as $batch) {
            $item_stock_sub_batch = new ItemStockSubBatch();
            $item_stock_sub_batch->stocked_by = $user->id;
            $item_stock_sub_batch->warehouse_id = $request->warehouse_id;
            $item_stock_sub_batch->item_id = $request->item_id;
            $item_stock_sub_batch->batch_no = $request->batch_no;
            $item_stock_sub_batch->sub_batch_no = $batch['batch_no'];
            $item_stock_sub_batch->quantity = $batch['quantity'];
            $item_stock_sub_batch->reserved_for_supply = 0;
            $item_stock_sub_batch->in_transit = 0; // initial values set to zero
            $item_stock_sub_batch->supplied = 0;
            $item_stock_sub_batch->balance = $batch['quantity'];
            $item_stock_sub_batch->goods_received_note = $batch['goods_received_note'];
            $item_stock_sub_batch->expiry_date = date('Y-m-d', strtotime($batch['expiry_date']));
            $item_stock_sub_batch->save();

            $item_stock_sub_batches[] = $item_stock_sub_batch;

            // log this event
            $title = $item_stock_sub_batch->item->name . " added to stock";
            $description = "Product was added to stock at " . $item_stock_sub_batch->warehouse->name . " by " . $user->name;
            $this->logUserActivity($title, $description);
        }
        return $item_stock_sub_batches;
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
        $sub_batches = $request->sub_batches;

        $this->createSubBatches($request, $sub_batches);
        // $item_in_stock->currency_id = $request->currency_id;
        // $item_in_stock->sale_price = $request->sale_price;
        // $item_in_stock->purchase_price = $request->purchase_price;
        // $item_in_stock->quantity = $request->quantity;
        // $item_in_stock->batch_no = $request->batch_no;
        // $item_in_stock->expiry_date = date('Y-m-d', strtotime($request->expiry_date));
        // $item_in_stock->save();

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
        $item_sub_stock->delete();
        return response()->json(null, 204);
    }
}
