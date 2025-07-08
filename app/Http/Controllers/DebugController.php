<?php

namespace App\Http\Controllers;

use App\Models\Invoice\DispatchedProduct;
use App\Models\Invoice\InvoiceItem;
use App\Models\Invoice\InvoiceItemBatch;
use App\Models\Invoice\WaybillItem;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Transfers\TransferRequestDispatchedProduct;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class DebugController extends Controller
{
    /*public function solveDeliveryTripIssue()
    {
        $delivery_trips = DeliveryTrip::get();
        foreach ($delivery_trips as $delivery_trip) {
            $waybills = $delivery_trip->waybills;
            foreach ($waybills as $waybill) {
                if($delivery_trip->vehicle_id !== NULL) {
                    $this->createDispatchedWaybill($waybill->id, $delivery_trip->vehicle_id);
                }
            }
        }
        return 'Hello';
    }*/
    public function balanceInvoiceItems()
    {
        $invoice_items = InvoiceItem::where('quantity_supplied', '>', 'quantity')->get();
        foreach ($invoice_items as $invoice_item) {
            $invoice_item->quantity_supplied = $invoice_item->quantity;
            $invoice_item->save();
        }
        return 'true';
    }
    public function solveDispatchProductIssue()
    {
        $dispatch_products = DispatchedProduct::where('waybill_item_id', NULL)->get();
        //return $dispatch_products->count();
        foreach ($dispatch_products as $dispatch_product) {
            $waybill = $dispatch_product->waybill;
            $waybill_items = $waybill->waybillItems;
            foreach ($waybill_items as $waybill_item) {
                $invoice_item_batch = InvoiceItemBatch::where(['invoice_item_id' => $waybill_item->invoice_item_id, 'item_stock_sub_batch_id' => $dispatch_product->item_stock_sub_batch_id])->first();
                if ($invoice_item_batch) {
                    // if ($dispatch_product->quantity_supplied === $invoice_item_batch->invoiceItem->quantity_supplied) {
                    $dispatch_product->waybill_item_id = $waybill_item->id;
                    $dispatch_product->save();
                    //}
                }
            }
        }
        return 'saved';
    }

    public function updateDispatchProductInvoiceId()
    {
        set_time_limit(0);
        DispatchedProduct::with('waybillItem')/*->where('invoice_id', NULL)*/ ->chunkById(200, function (Collection $dispatch_products) {
            foreach ($dispatch_products as $dispatch_product) {
                $waybill_item = $dispatch_product->waybillItem;
                $quantity_supplied = $dispatch_product->quantity_supplied;
                $waybill_item->remitted += $quantity_supplied;
                $waybill_item->save();

                // $dispatch_product->invoice_id = $dispatch_product->waybillItem->invoice_id;

                // $dispatch_product->invoice_item_id = $dispatch_product->waybillItem->invoice_item_id;
                // $dispatch_product->item_id = $dispatch_product->waybillItem->item_id;
                // $dispatch_product->save();
            }
        }, $column = 'id');
        return 'true';
    }
    public function totalDispatchedProduct(Request $request)
    {
        $item_stock_sub_batch_id = $request->item_stock_sub_batch_id;
        $total = DispatchedProduct::where('item_stock_sub_batch_id', $item_stock_sub_batch_id)->select(\DB::raw('SUM(quantity_supplied) as quantity'))->get();
        //return $dispatch_products->count();
        return $total[0]->quantity;
    }

    public function reservedProducts(Request $request)
    {
        $item_stock_sub_batch_id = $request->item_stock_sub_batch_id;
        $total = InvoiceItemBatch::where('item_stock_sub_batch_id', $item_stock_sub_batch_id)->select(\DB::raw('SUM(quantity) as quantity'))->get();
        //return $dispatch_products->count();
        return $total;
    }
    public function resetStock()
    {
        // set_time_limit(0);
        // ini_set('memory_limit', '1024M');
        // InvoiceItemBatch::where('quantity', '>', 0)
        //     ->groupBy('item_stock_sub_batch_id')
        //     ->select('*', \DB::raw('SUM(quantity) as reserved'))
        //     ->chunkById(200, function (Collection $invoice_batches) {
        //         foreach ($invoice_batches as $invoice_batch) {
        //             $item_stock_batch_id = $invoice_batch->item_stock_sub_batch_id;
        //             $item_stock_batch = ItemStockSubBatch::find($item_stock_batch_id);

        //             $item_stock_batch->reserved_for_supply = $invoice_batch->reserved;
        //             $item_stock_batch->save();
        //         }
        //     }, $column = 'id');
        //         $invoice_batch->item_stock_sub_batch_id = $stock->id;
        //         $invoice_batch->save();
        // $dispatched_products = DispatchedProduct::whereIn('item_stock_sub_batch_id', [12622, 12693, 14177, 14391, 15550, 16668, 17020, 17487, 17960, 19172, 19568, 20198, 20235, 21769, 23568, 24139, 25414, 25802, 25989, 27545, 27614, 28549, 28992, 29223, 29224, 29278, 29533, 29836, 29854, 30073, 30144, 30486, 30929, 30931, 30935, 31167])->where('created_at', 'LIKE', '%2025-07-04%')->get();

        // $untreated = [];
        // foreach ($dispatched_products as $dispatched_product) {
        //     $old_item_stock_sub_batch_id = $dispatched_product->item_stock_sub_batch_id;
        //     $quantity = $dispatched_product->quantity_supplied;
        //     $item_id = $dispatched_product->item_id;
        //     $waybill_item_id = $dispatched_product->waybill_item_id;
        //     $warehouse_id = $dispatched_product->warehouse_id;
        //     $stock = ItemStockSubBatch::where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])
        //         ->whereRaw('confirmed_by IS NOT NULL')
        //         ->whereRaw("balance - reserved_for_supply >= $quantity")
        //         ->first();
        //     if (!$stock) {
        //         $untreated[] = $old_item_stock_sub_batch_id;
        //     } else {

        //         $dispatched_product->item_stock_sub_batch_id = $stock->id;
        //         $dispatched_product->save();

        //         $former_stock = ItemStockSubBatch::find($old_item_stock_sub_batch_id);
        //         $former_stock->balance += $quantity;
        //         $former_stock->supplied -= $quantity;
        //         $former_stock->in_transit -= $quantity;
        //         $former_stock->save();

        //         $invoice_batch = InvoiceItemBatch::where(['waybill_item_id' => $waybill_item_id, 'item_stock_sub_batch_id' => $old_item_stock_sub_batch_id])->first();
        //         $invoice_batch->item_stock_sub_batch_id = $stock->id;
        //         $invoice_batch->save();

        //         $stock->balance -= $quantity;
        //         $stock->supplied += $quantity;
        //         $stock->save();
        //     }
        // }
        // return $untreated;
        // // ItemStockSubBatch::chunkById(200, function (Collection $item_stock_batches) {
        // //     foreach ($item_stock_batches as $item_stock_batch) {
        // //         $item_stock_batch_id = $item_stock_batch->id;
        // //         $total = DispatchedProduct::where('item_stock_sub_batch_id', $item_stock_batch_id)->select(\DB::raw('SUM(quantity_supplied) as supplied'))->first();
        // //         $transferred = TransferRequestDispatchedProduct::where('item_stock_sub_batch_id', $item_stock_batch_id)->select(\DB::raw('SUM(quantity_supplied) as supplied'))->first();

        // //         $stocked_quantity = $item_stock_batch->quantity;
        // //         $supplied = $total->supplied;
        // //         $transferred = $transferred->supplied;

        // //         $total_out = $supplied + $transferred;

        // //         $item_stock_batch->total_out = $total_out;
        // //         $item_stock_batch->total_sold = $supplied;
        // //         $item_stock_batch->total_transferred = $transferred;
        // //         $item_stock_batch->balance = $stocked_quantity - $total_out;
        // //         $item_stock_batch->supplied = $total_out;
        // //         $item_stock_batch->save();
        // //     }
        // // }, $column = 'id');
        // ini_set('memory_limit', '128M');
        // $item_stock_batches = ItemStockSubBatch::get();
        // foreach ($item_stock_batches as $item_stock_batch) {
        //     $item_stock_batch->reserved_for_supply = 0;
        //     $item_stock_batch->in_transit = 0;
        //     $item_stock_batch->supplied = 0;
        //     $item_stock_batch->balance = $item_stock_batch->quantity;

        //     $item_stock_batch->save();
        // }
        // return 'true';
    }
    public function balanceStockAccount()
    {
        $item_stock_batches = ItemStockSubBatch::orderBy('id')->get();
        foreach ($item_stock_batches as $item_stock_batch) {
            // $total = DispatchedProduct::where(['item_stock_sub_batch_id' => $item_stock_batch->id])->select(\DB::raw('SUM(quantity_supplied) as quantity'))->get();

            // $delivered = DispatchedProduct::where(['item_stock_sub_batch_id' => $item_stock_batch->id, 'status' => 'delivered'])->select(\DB::raw('SUM(quantity_supplied) as quantity'))->get();

            // $transit = DispatchedProduct::where(['item_stock_sub_batch_id' => $item_stock_batch->id, 'status' => 'on transit'])->select(\DB::raw('SUM(quantity_supplied) as quantity'))->get();

            $reserved = InvoiceItemBatch::where('item_stock_sub_batch_id', $item_stock_batch->id)->select(\DB::raw('SUM(quantity) as quantity'))->get();

            $reserved_for_supply = 0;
            if ($reserved[0]->quantity != null) {
                $reserved_for_supply = $reserved[0]->quantity;
            }
            $item_stock_batch->reserved_for_supply = $reserved_for_supply;
            // $item_stock_batch->in_transit = $in_transit;
            // $item_stock_batch->supplied = $supplied;
            // $item_stock_batch->balance = $item_stock_batch->quantity - $balance;

            $item_stock_batch->save();
        }
        return 'Done';
    }
    public function balanceStockAccountPerProduct()
    {
        $products = Item::get();
        foreach ($products as $product) {
            $item_stock_batches = ItemStockSubBatch::where('item_id', $product->id)->orderBy('id')->get();
            $reserved_for_supply = $in_transit = $supplied = $balance = 0;
            if (!$item_stock_batches->isEmpty()) {
                foreach ($item_stock_batches as $item_stock_batch) {
                    $total = DispatchedProduct::where(['item_stock_sub_batch_id' => $item_stock_batch->id])->select(\DB::raw('SUM(quantity_supplied) as quantity'))->get();

                    $delivered = DispatchedProduct::where(['item_stock_sub_batch_id' => $item_stock_batch->id, 'status' => 'delivered'])->select(\DB::raw('SUM(quantity_supplied) as quantity'))->get();

                    $transit = DispatchedProduct::where(['item_stock_sub_batch_id' => $item_stock_batch->id, 'status' => 'on transit'])->select(\DB::raw('SUM(quantity_supplied) as quantity'))->get();

                    $reserved = InvoiceItemBatch::where('item_stock_sub_batch_id', $item_stock_batch->id)->select(\DB::raw('SUM(quantity) as quantity'))->get();

                    if ($reserved[0]->quantity != null) {
                        $reserved_for_supply += $reserved[0]->quantity;
                    }
                    if ($transit[0]->quantity != null) {
                        $in_transit += $transit[0]->quantity;
                    }
                    if ($delivered[0]->quantity != null) {
                        $supplied += $delivered[0]->quantity;
                    }
                    if ($total[0]->quantity != null) {
                        $balance += $total[0]->quantity;
                    }
                    // $item_stock_batch->reserved_for_supply = $reserved_for_supply;
                    // $item_stock_batch->in_transit = $in_transit;
                    // $item_stock_batch->supplied = $supplied;
                    // $item_stock_batch->balance = $item_stock_batch->quantity - $balance;

                    // $item_stock_batch->save();
                }
                foreach ($item_stock_batches as $item_stock_batch) {
                    $item_stock_batch->reserved_for_supply = $reserved_for_supply;
                    $item_stock_batch->in_transit = $in_transit;
                    $item_stock_batch->supplied = $supplied;
                    $item_stock_batch->balance = $item_stock_batch->quantity - $balance;

                    $item_stock_batch->save();
                }
            }
        }

        return 'Done';
    }
    public function stabilizeAccount()
    {
        // $item_stock_batches = ItemStockSubBatch::get();
        // foreach ($item_stock_batches as $item_stock_batch) {
        //     $dispatched_products = DispatchedProduct::where(['item_stock_sub_batch_id' => $item_stock_batch->id, 'remitted' => 0])->get();
        //     $total = 0;
        //     foreach ($dispatched_products as $dispatched_product) {
        //         $status = $dispatched_product->status;
        //         $quantity_supplied = $dispatched_product->quantity_supplied;

        //         if ($quantity_supplied <= $item_stock_batch->balance) {
        //             $item_stock_batch->balance -= $quantity_supplied;

        //             if ($status == 'on transit') {
        //                 $item_stock_batch->in_transit += $quantity_supplied;
        //             } else {
        //                 $item_stock_batch->supplied += $quantity_supplied;
        //             }
        //             $item_stock_batch->save();
        //             $dispatched_product->remitted = 1;
        //             $dispatched_product->save();
        //         }
        //     }
        //     // $out_bound = $item_stock_batch->in_transit =
        //     // $another_batch = ItemStockSubBatch::where(['item_id' => $product->id])->whereRaw('balance > '. $item_stock_batch-> )->get();
        // }
        $dispatched_products = DispatchedProduct::where(['remitted' => 0])->get();

        foreach ($dispatched_products as $dispatched_product) {
            $item_stock_batch = ItemStockSubBatch::find($dispatched_product->item_stock_sub_batch_id);
            $status = $dispatched_product->status;
            $quantity_supplied = $dispatched_product->quantity_supplied;

            if ($quantity_supplied <= $item_stock_batch->balance) {
                $item_stock_batch->balance -= $quantity_supplied;

                if ($status == 'on transit') {
                    $item_stock_batch->in_transit += $quantity_supplied;
                } else {
                    $item_stock_batch->supplied += $quantity_supplied;
                }
                $item_stock_batch->save();
                $dispatched_product->remitted = 1;
                $dispatched_product->instant_balance = $item_stock_batch->balance;
                $dispatched_product->save();
                // $quantity_supplied = 0;
            } else {
                if ($item_stock_batch->balance > 0) {
                    $quantity_supplied -= $item_stock_batch->balance;

                    if ($status == 'on transit') {
                        $item_stock_batch->in_transit += $item_stock_batch->balance;
                    } else {
                        $item_stock_batch->supplied += $item_stock_batch->balance;
                    }
                    $dispatched_product->remitted = 1;
                    $dispatched_product->instant_balance = $item_stock_batch->balance;
                    $dispatched_product->quantity_supplied = $item_stock_batch->balance;
                    $dispatched_product->save();
                    $item_stock_batch->balance = 0;
                    $item_stock_batch->save();




                    # code...

                    $next_item_stock_batches = ItemStockSubBatch::where('item_id', $item_stock_batch->item_id)->orderBy('id')->get();
                    foreach ($next_item_stock_batches as $next_item_stock_batch) {
                        if ($quantity_supplied <= $next_item_stock_batch->balance) {
                            $next_item_stock_batch->balance -= $quantity_supplied;

                            if ($status == 'on transit') {
                                $next_item_stock_batch->in_transit += $quantity_supplied;
                            } else {
                                $next_item_stock_batch->supplied += $quantity_supplied;
                            }
                            $next_item_stock_batch->save();

                            $new_dispatched_product = new DispatchedProduct();
                            $new_dispatched_product->warehouse_id = $dispatched_product->warehouse_id;
                            $new_dispatched_product->item_stock_sub_batch_id = $next_item_stock_batch->id;
                            $new_dispatched_product->waybill_id = $dispatched_product->waybill_id;
                            $new_dispatched_product->waybill_item_id = $dispatched_product->waybill_item_id;
                            $new_dispatched_product->quantity_supplied = $quantity_supplied;
                            $new_dispatched_product->status = $status;
                            $new_dispatched_product->created_at = $dispatched_product->created_at;
                            $new_dispatched_product->remitted = 1;
                            $new_dispatched_product->instant_balance = $next_item_stock_batch->balance;
                            $new_dispatched_product->save();
                        } else {
                            $quantity_supplied -= $next_item_stock_batch->balance;

                            if ($status == 'on transit') {
                                $next_item_stock_batch->in_transit += $next_item_stock_batch->balance;
                            } else {
                                $next_item_stock_batch->supplied += $next_item_stock_batch->balance;
                            }
                            $next_item_stock_batch->balance = 0;
                            $next_item_stock_batch->save();

                            $new_dispatched_product = new DispatchedProduct();
                            $new_dispatched_product->warehouse_id = $dispatched_product->warehouse_id;
                            $new_dispatched_product->item_stock_sub_batch_id = $next_item_stock_batch->id;
                            $new_dispatched_product->waybill_id = $dispatched_product->waybill_id;
                            $new_dispatched_product->waybill_item_id = $dispatched_product->waybill_item_id;
                            $new_dispatched_product->quantity_supplied =
                                $next_item_stock_batch->balance;
                            $new_dispatched_product->status = $status;
                            $new_dispatched_product->created_at = $dispatched_product->created_at;
                            $new_dispatched_product->remitted = 1;
                            $new_dispatched_product->instant_balance = $next_item_stock_batch->balance;
                            $new_dispatched_product->save();
                        }
                    }
                }
            }
        }
        return 'done';
    }
    public function splitExcessStock()
    {
        $dispatched_products = DispatchedProduct::where(['remitted' => 0])->get();
        foreach ($dispatched_products as $dispatched_product) {
            $status = $dispatched_product->status;
            $quantity_supplied = $dispatched_product->quantity_supplied;
            $next_item_stock_batches = ItemStockSubBatch::where('item_id', $dispatched_product->itemStock->item_id)->where('balance', '>', 0)->orderBy('id')->get();
            foreach ($next_item_stock_batches as $next_item_stock_batch) {

                if ($next_item_stock_batch->balance <= $quantity_supplied) {
                    $quantity_supplied -= $next_item_stock_batch->balance;
                    if ($status == 'on transit') {
                        $next_item_stock_batch->in_transit += $next_item_stock_batch->balance;
                    } else {
                        $next_item_stock_batch->supplied += $next_item_stock_batch->balance;
                    }
                    $next_item_stock_batch->balance = 0;
                    $next_item_stock_batch->save();

                    $new_dispatched_product = new DispatchedProduct();
                    $new_dispatched_product = $dispatched_product;
                    $new_dispatched_product->quantity_supplied = $quantity_supplied;
                    $new_dispatched_product->instant_balance = $next_item_stock_batch->balance;
                    $new_dispatched_product->remitted = 1;
                    $new_dispatched_product->save();
                    // $quantity_supplied = 0;
                } else {
                    // $quantity_supplied -= $next_item_stock_batch->balance;
                    if ($status == 'on transit') {
                        $next_item_stock_batch->in_transit += $quantity_supplied;
                    } else {
                        $next_item_stock_batch->supplied += $quantity_supplied;
                    }
                    $next_item_stock_batch->balance -= $quantity_supplied;
                    $next_item_stock_batch->save();
                    $new_dispatched_product = new DispatchedProduct();
                    $new_dispatched_product = $dispatched_product;
                    $new_dispatched_product->quantity_supplied = $quantity_supplied;
                    $new_dispatched_product->instant_balance = $next_item_stock_batch->balance;
                    $new_dispatched_product->remitted = 1;
                    $new_dispatched_product->save();
                }
            }
            $dispatched_product->delete();
            return 'done';
        }
    }
}
