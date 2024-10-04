<?php

namespace App\Http\Controllers\Invoice;

use App\Customer;
use App\Driver;
use App\Http\Controllers\Controller;
use App\Models\Invoice\DeliveryTrip;
use App\Models\Invoice\DeliveryTripExpense;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceHistory;
use App\Models\Invoice\InvoicePayment;
use App\Models\Invoice\InvoiceStatus;
use App\Models\Invoice\Waybill;
use App\Models\Invoice\DispatchedInvoice;
use App\Models\Invoice\DispatchedProduct;
use App\Models\Invoice\DispatchedWaybill;
use App\Models\Invoice\InvoiceItem;
use App\Models\Invoice\InvoiceItemBatch;
use App\Models\Invoice\WaybillItem;
use App\Models\Logistics\Vehicle;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStock;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Warehouse\Warehouse;
use App\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class InvoicesController extends Controller
{
    const ITEM_PER_PAGE = 10;
    // public function clearPartialInvoices()
    // {
    //     $invoice_items = InvoiceItem::where(['delivery_status' => 'delivered', 'supply_status' => 'Partial'])->whereRaw('quantity = quantity_supplied')->get();
    //     foreach ($invoice_items as $invoice_item) {
    //         $invoice_item->supply_status = 'Complete';
    //         $invoice_item->save();
    //     }

    //     $invoices = Invoice::where('status', 'partially supplied')->get();
    //     foreach ($invoices as  $invoice) {

    //         $incomplete_invoice_item = $invoice->invoiceItems()->where('supply_status', '=', 'Partial')->first();
    //         if (!$incomplete_invoice_item) {
    //             $invoice->status = 'delivered';
    //             $invoice->full_waybill_generated = '1';
    //             $invoice->save();
    //         }
    //     }
    //     return 'done';
    // }
    // public function checkInvoiceItemsWithoutWaybill()
    // {
    //     $invoice_items = InvoiceItem::has('waybillItems', '<', 1)->with('batches')->where('quantity_supplied', '>', 0)->get();

    //     foreach ($invoice_items as $invoice_item) {
    //         $invoice_item->batches()->delete();
    //         $invoice_item->quantity_supplied = 0;
    //         $invoice_item->save();
    //     }
    // }
    // public function correctDispatchProductDate()
    // {
    //     set_time_limit(0);
    //     $waybills = Waybill::where('status', '!=', 'pending')->get();
    //     foreach ($waybills as $waybill) {
    //         $dispatch_products = $waybill->dispatchProducts;
    //         foreach ($dispatch_products as $dispatch_product) {
    //             $dispatch_product->created_at = $waybill->created_at;
    //             $dispatch_product->save();
    //         }
    //     }
    //     return 'true';
    // }
    // public function stabilizeInvoiceItems()
    // {
    //     set_time_limit(0);
    //     $invoice_items = InvoiceItem::where('remitted', 0)->where('quantity_supplied', '>', 0)->get();
    //     foreach ($invoice_items as $invoice_item) {
    //         $this->createInvoiceItemBatches($invoice_item, [], $invoice_item->quantity_supplied);
    //         $invoice_item->remitted = 1;
    //         $invoice_item->save();
    //     }
    //     return 'true';
    // }

    // public function deliverProducts()
    // {
    //     set_time_limit(0);
    //     $waybills = Waybill::where('remitted', 0)->where('status', '!=', 'pending')->get();
    //     foreach ($waybills as $waybill) {
    //         $waybillItems = $waybill->waybillItems()->where('remitted', 0)->get();

    //         $status = $waybill->status;
    //         if ($status != 'delivered') {
    //             $status = 'on transit';
    //         }
    //         $this->sendItemInStockForDelivery($waybillItems, $status);
    //         $waybill->remitted = 1;
    //         $waybill->save();
    //     }
    //     return 'true';
    // }

    // private function dispatchProduct($warehouse_id, $item_stock_batch, $waybill_item, $quantity, $status)
    // {
    //     $dispatched_product = new DispatchedProduct();
    //     $dispatched_product->warehouse_id = $warehouse_id;
    //     $dispatched_product->item_stock_sub_batch_id = $item_stock_batch->id;
    //     $dispatched_product->waybill_id = $waybill_item->waybill_id;
    //     $dispatched_product->waybill_item_id = $waybill_item->id;
    //     $dispatched_product->quantity_supplied = $quantity;
    //     $dispatched_product->remitted = 1;
    //     $dispatched_product->instant_balance = $item_stock_batch->balance;
    //     $dispatched_product->status = $status;
    //     $dispatched_product->save();
    // }
    // public function sendItemInStockForDelivery($waybill_items, $status = 'on transit')
    // {
    //     foreach ($waybill_items as $waybill_item) {

    //         $warehouse_id = $waybill_item->warehouse_id;
    //         $waybill_quantity = $waybill_item->quantity;
    //         $invoice_item_id = $waybill_item->invoice_item_id;
    //         $invoice_item_batches = InvoiceItemBatch::with('itemStockBatch')->where('invoice_item_id', $invoice_item_id)->where('quantity', '>', '0')->get();
    //         // $items_in_stock= ItemStock::where(['warehouse_id' => $warehouse_id, 'item_id' => $waybill_item->item_id])
    //         //     ->where('balance', '>', '0')->orderBy('id')->get();
    //         // $item_stock_sub_batches = ItemStockSubBatch::with('itemStock')->where(['warehouse_id' => $warehouse_id, 'item_id' => $waybill_item->item_id])
    //         //     ->where('balance', '>', '0')->orderBy('id')->get();
    //         if ($invoice_item_batches->count() > 0) {
    //             foreach ($invoice_item_batches as $invoice_item_batch) :

    //                 $for_supply = $invoice_item_batch->quantity;
    //                 $item_stock_batch = $invoice_item_batch->itemStockBatch;
    //                 $item_id = $item_stock_batch->item_id;

    //                 if ($waybill_quantity <= $for_supply) {
    //                     $invoice_item_batch->quantity -= $waybill_quantity;
    //                     $invoice_item_batch->save();

    //                     if ($item_stock_batch->balance > 0) {
    //                         if ($item_stock_batch->balance >= $waybill_quantity) {
    //                             $item_stock_batch->reserved_for_supply -= $waybill_quantity;
    //                             if ($status == 'on transit') {
    //                                 $item_stock_batch->in_transit += $waybill_quantity;
    //                             } else {
    //                                 $item_stock_batch->supplied += $waybill_quantity;
    //                             }

    //                             $item_stock_batch->balance -=  $waybill_quantity;
    //                             $item_stock_batch->save();

    //                             $this->dispatchProduct($warehouse_id, $item_stock_batch, $waybill_item, $waybill_quantity, $status);

    //                             $waybill_quantity = 0;
    //                         } else {
    //                             $waybill_quantity -= $item_stock_batch->balance;
    //                             $this->dispatchProduct($warehouse_id, $item_stock_batch, $waybill_item, $item_stock_batch->balance, $status);
    //                             $item_stock_batch->reserved_for_supply -=
    //                                 $item_stock_batch->balance;
    //                             if ($status == 'on transit') {
    //                                 $item_stock_batch->in_transit += $item_stock_batch->balance;
    //                             } else {
    //                                 $item_stock_batch->supplied += $item_stock_batch->balance;
    //                             }
    //                             $item_stock_batch->balance =  0;
    //                             $item_stock_batch->save();

    //                             $next_item_stock_batches = ItemStockSubBatch::where('item_id', $item_id)->where('balance', '>', 0)->orderBy('id')->get();
    //                             foreach ($next_item_stock_batches as $next_item_stock_batch) {
    //                                 if ($waybill_quantity <= $next_item_stock_batch->balance) {

    //                                     if ($status == 'on transit') {
    //                                         $next_item_stock_batch->in_transit += $waybill_quantity;
    //                                     } else {
    //                                         $next_item_stock_batch->supplied += $waybill_quantity;
    //                                     }
    //                                     $next_item_stock_batch->balance -=  $waybill_quantity;
    //                                     $next_item_stock_batch->save();

    //                                     $this->dispatchProduct($warehouse_id, $next_item_stock_batch, $waybill_item, $waybill_quantity, $status);

    //                                     $waybill_quantity = 0;
    //                                     break;
    //                                 } else {
    //                                     if ($status == 'on transit') {
    //                                         $next_item_stock_batch->in_transit +=
    //                                             $next_item_stock_batch->balance;
    //                                     } else {
    //                                         $next_item_stock_batch->supplied +=
    //                                             $next_item_stock_batch->balance;
    //                                     }
    //                                     $this->dispatchProduct($warehouse_id, $next_item_stock_batch, $waybill_item, $next_item_stock_batch->balance, $status);

    //                                     $waybill_quantity -= $next_item_stock_batch->balance;
    //                                     $next_item_stock_batch->balance =  0;
    //                                     $next_item_stock_batch->save();
    //                                 }
    //                             }
    //                         }
    //                     } else {
    //                         $next_item_stock_batches = ItemStockSubBatch::where('item_id', $item_id)->where('balance', '>', 0)->orderBy('id')->get();
    //                         foreach ($next_item_stock_batches as $next_item_stock_batch) {
    //                             if ($waybill_quantity <= $next_item_stock_batch->balance) {
    //                                 if ($status == 'on transit') {
    //                                     $next_item_stock_batch->in_transit += $waybill_quantity;
    //                                 } else {
    //                                     $next_item_stock_batch->supplied += $waybill_quantity;
    //                                 }
    //                                 $next_item_stock_batch->balance -=  $waybill_quantity;
    //                                 $next_item_stock_batch->save();

    //                                 $this->dispatchProduct($warehouse_id, $next_item_stock_batch, $waybill_item, $waybill_quantity, $status);

    //                                 $waybill_quantity = 0;
    //                                 break;
    //                             } else {

    //                                 if ($status == 'on transit') {
    //                                     $next_item_stock_batch->in_transit += $next_item_stock_batch->balance;
    //                                 } else {
    //                                     $next_item_stock_batch->supplied += $next_item_stock_batch->balance;
    //                                 }
    //                                 $this->dispatchProduct($warehouse_id, $next_item_stock_batch, $waybill_item, $next_item_stock_batch->balance, $status);

    //                                 $waybill_quantity -= $next_item_stock_batch->balance;
    //                                 $next_item_stock_batch->balance =  0;
    //                                 $next_item_stock_batch->save();
    //                             }
    //                         }
    //                     }
    //                     //// also update item_stocks table/////////
    //                     // $invoice_item_batch->itemStockBatch->itemStock->in_transit +=  $waybill_quantity;
    //                     // $invoice_item_batch->itemStockBatch->itemStock->balance -=  $waybill_quantity;
    //                     // $invoice_item_batch->itemStockBatch->itemStock->save();



    //                     $waybill_quantity = 0; //we have sent all items for delivery
    //                     break;
    //                 } else {
    //                     $invoice_item_batch->quantity = 0;
    //                     $invoice_item_batch->save();

    //                     if ($item_stock_batch->balance > 0) {
    //                         if ($item_stock_batch->balance >= $for_supply) {
    //                             $item_stock_batch->reserved_for_supply -= $for_supply;
    //                             if ($status == 'on transit') {
    //                                 $item_stock_batch->in_transit += $for_supply;
    //                             } else {
    //                                 $item_stock_batch->supplied += $for_supply;
    //                             }

    //                             $item_stock_batch->balance -=  $for_supply;
    //                             $item_stock_batch->save();

    //                             $this->dispatchProduct($warehouse_id, $item_stock_batch, $waybill_item, $for_supply, $status);
    //                         } else {
    //                             $for_supply -= $item_stock_batch->balance;
    //                             $this->dispatchProduct($warehouse_id, $item_stock_batch, $waybill_item, $item_stock_batch->balance, $status);
    //                             $item_stock_batch->reserved_for_supply -=
    //                                 $item_stock_batch->balance;
    //                             if ($status == 'on transit') {
    //                                 $item_stock_batch->in_transit += $item_stock_batch->balance;
    //                             } else {
    //                                 $item_stock_batch->supplied += $item_stock_batch->balance;
    //                             }

    //                             $item_stock_batch->balance =  0;
    //                             $item_stock_batch->save();

    //                             $next_item_stock_batches2 = ItemStockSubBatch::where('item_id', $item_id)->where('balance', '>', 0)->orderBy('id')->get();
    //                             foreach ($next_item_stock_batches2 as $next_item_stock_batch2) {
    //                                 if ($for_supply <= $next_item_stock_batch2->balance) {
    //                                     if ($status == 'on transit') {
    //                                         $next_item_stock_batch2->in_transit += $for_supply;
    //                                     } else {
    //                                         $next_item_stock_batch2->supplied += $for_supply;
    //                                     }

    //                                     $next_item_stock_batch2->balance -=  $for_supply;
    //                                     $next_item_stock_batch2->save();
    //                                     $this->dispatchProduct($warehouse_id, $next_item_stock_batch2, $waybill_item, $for_supply, $status);
    //                                     //$for_supply = 0;
    //                                     break;
    //                                 } else {
    //                                     if ($status == 'on transit') {
    //                                         $next_item_stock_batch2->in_transit += $next_item_stock_batch2->balance;
    //                                     } else {
    //                                         $next_item_stock_batch2->supplied += $next_item_stock_batch2->balance;
    //                                     }

    //                                     $this->dispatchProduct($warehouse_id, $next_item_stock_batch2, $waybill_item, $next_item_stock_batch2->balance, $status);
    //                                     $for_supply -= $next_item_stock_batch2->balance;

    //                                     $next_item_stock_batch2->balance =  0;
    //                                     $next_item_stock_batch2->save();
    //                                 }
    //                             }
    //                         }
    //                     } else {
    //                         $next_item_stock_batches2 = ItemStockSubBatch::where('item_id', $item_id)->where('balance', '>', 0)->orderBy('id')->get();
    //                         foreach ($next_item_stock_batches2 as $next_item_stock_batch2) {
    //                             if ($for_supply <= $next_item_stock_batch2->balance) {
    //                                 if ($status == 'on transit') {
    //                                     $next_item_stock_batch2->in_transit += $for_supply;
    //                                 } else {
    //                                     $next_item_stock_batch2->supplied += $for_supply;
    //                                 }
    //                                 $next_item_stock_batch2->balance -=  $for_supply;
    //                                 $next_item_stock_batch2->save();
    //                                 $this->dispatchProduct($warehouse_id, $next_item_stock_batch2, $waybill_item, $for_supply, $status);
    //                                 //$for_supply = 0;
    //                                 break;
    //                             } else {

    //                                 if ($status == 'on transit') {
    //                                     $next_item_stock_batch2->in_transit += $next_item_stock_batch2->balance;
    //                                 } else {
    //                                     $next_item_stock_batch2->supplied += $next_item_stock_batch2->balance;
    //                                 }
    //                                 $this->dispatchProduct($warehouse_id, $next_item_stock_batch2, $waybill_item, $next_item_stock_batch2->balance, $status);
    //                                 $for_supply -= $next_item_stock_batch2->balance;

    //                                 $next_item_stock_batch2->balance =  0;
    //                                 $next_item_stock_batch2->save();
    //                             }
    //                         }
    //                     }


    //                     $waybill_quantity -= $for_supply;
    //                 }
    //             endforeach;
    //         }
    //         $waybill_item->remitted = 1;
    //         $waybill_item->save();
    //     }
    // }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $searchParams = $request->all();
        $invoiceQuery = Invoice::query();
        $limit = Arr::get($searchParams, 'limit', static::ITEM_PER_PAGE);
        $keyword = Arr::get($searchParams, 'keyword', '');
        if (!empty($keyword)) {
            $invoiceQuery->where(function ($q) use ($keyword) {
                $q->where('invoice_number', 'LIKE', '%' . $keyword . '%');
                $q->orWhere('invoice_date', 'LIKE', '%' . $keyword . '%');
                $q->orWhere('amount', 'LIKE', '%' . $keyword . '%');
                $q->orWhere('created_at', 'LIKE', '%' . $keyword . '%');
                $q->orWhereHas('customer', function ($q) use ($keyword) {
                    $q->whereHas('user', function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', '%' . $keyword . '%');
                    });
                });
            });
        }
        $user = $this->getUser();
        // $warehouse_id = $request->warehouse_id;
        $invoices = [];
        if (isset($request->from, $request->to) && $request->from != '' && $request->from != '') {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';

            $invoiceQuery->where('created_at', '>=', $date_from)
                ->where('created_at', '<=', $date_to);
        }
        $status = (isset($request->status) && $request->status != '') ? $request->status : 'pending';

        $invoices = $invoiceQuery->with([
            'warehouse',
            'waybillItems',
            'customer.user',
            'customer.type',
            'confirmer',
            'invoiceItems.item' => function ($q) {
                $q->withTrashed();
            },
            'histories' => function ($q) {
                $q->orderBy('id', 'DESC');
            }
        ])
            ->where([/*'warehouse_id' => $warehouse_id,*/ 'status' => $status])
            ->orderBy('updated_at', 'DESC')
            ->paginate($limit);

        return response()->json(compact('invoices'));
    }
    public function unDeliveredInvoices(Request $request)
    {
        //
        $user = $this->getUser();
        $warehouse_id = (isset($request->warehouse_id) && $request->warehouse_id != '') ? $request->warehouse_id : 1;
        $waybill_no = $this->nextReceiptNo('waybill');
        /*$invoices = Invoice::get();
        foreach ($invoices as $invoice) {
            $customer = Customer::find($invoice->customer_id)->first();
            if (!$customer) {
                $invoice->delete();
            }
        }*/
        // $invoices = Invoice::with(['invoiceItems', 'invoiceItems.item'])->where('warehouse_id', $warehouse_id)->where('status', '!=', 'delivered')->get();
        $invoices = Invoice::with([
            'customer.user',
            'confirmer'
        ])
            ->where('warehouse_id', $warehouse_id)
            ->where('status', '!=', 'delivered')
            ->where('status', '!=', 'archived')
            // ->where('full_waybill_generated', '0')
            ->whereRaw('confirmed_by IS NOT NULL')
            ->orderBy('id', 'DESC')
            ->paginate(20);
        return response()->json(compact('invoices', 'waybill_no'), 200);
    }

    public function unDeliveredInvoicesSearch(Request $request)
    {
        $user = $this->getUser();
        $warehouse_id = (isset($request->warehouse_id) && $request->warehouse_id != '') ? $request->warehouse_id : 1;
        $invoice_no = $request->invoice_no;
        $invoices = Invoice::with([
            'customer.user',
            'confirmer',
        ])
            ->where('warehouse_id', $warehouse_id)
            ->where('status', '!=', 'delivered')
            ->where('status', '!=', 'archived')
            ->where('invoice_number', 'LIKE', '%' . $invoice_no . '%')
            // ->where('full_waybill_generated', '0')
            ->whereRaw('confirmed_by IS NOT NULL')
            ->orderBy('id', 'DESC')
            ->get();
        return response()->json(compact('invoices'), 200);
    }
    public function waybillSelectedInvoices(Request $request)
    {
        $invoice_ids = $request->invoice_ids;
        $warehouse_id = $request->warehouse_id;
        $invoice_items = InvoiceItem::with([
            'item' => function ($q) {
                $q->withTrashed();
            },
            'item.stocks' => function ($p) use ($warehouse_id) {
                $p->where('warehouse_id', $warehouse_id)
                    ->whereRaw('balance - reserved_for_supply > 0')
                    ->whereRaw('confirmed_by IS NOT NULL');
            }
        ])
            ->whereRaw('quantity - quantity_supplied - quantity_reversed > 0')
            ->where('warehouse_id', $warehouse_id)
            ->whereIn('invoice_id', $invoice_ids)
            ->get();
        return response()->json(compact('invoice_items'), 200);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function assignInvoiceToWarehouse(Request $request, Invoice $invoice)
    {

        $warehouse_id = $request->warehouse_id;
        $warehouse = Warehouse::find($warehouse_id);
        $invoice->warehouse_id = $warehouse_id;
        $invoice->save();
        //log this activity
        $title = "Invoice Assigned";
        $description = "Assigned invoice ($invoice->invoice_number) to " . $warehouse->name;
        $roles = ['warehouse manager'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($invoice);
    }

    public function checkProductQuantityInStock(Request $request)
    {
        //
        $warehouse_id = $request->warehouse_id;
        $invoice_items = json_decode(json_encode($request->invoice_items));
        $insufficient_stock_count = 0;
        foreach ($invoice_items as $item) {
            $item_id = $item->item_id;
            $quantity = $item->quantity;
            $stock = ItemStockSubBatch::groupBy('item_id')
                ->where('warehouse_id', '!=', 7)
                ->where('item_id', $item_id)
                ->whereRaw('confirmed_by IS NOT NULL')
                ->select(\DB::raw('(SUM(balance) -  SUM(reserved_for_supply)) as total_balance'))
                ->first();

            $invoiced_item = InvoiceItem::groupBy('item_id')
                ->where('warehouse_id', '!=', 7)
                ->where('item_id', $item_id)
                ->where('supply_status', '!=', 'Archived')
                ->whereRaw('quantity > quantity_supplied')
                ->select(columns: \DB::raw('(SUM(quantity) -  SUM(quantity_supplied)) as total_invoiced'))
                ->first();
            $total_stock_balance = ($stock) ? (int) $stock->total_balance : (int) 0;
            // $total_invoice_quantity = ($invoiced_item) ? (int) $invoiced_item->total_invoiced : (int) 0;
            // $diff = $total_stock_balance - $total_invoice_quantity;

            $item->stock_balance = $total_stock_balance; // (int) $diff;

            if ($quantity > $item->stock_balance) {
                $insufficient_stock_count++;
            }
        }

        $can_submit = ($insufficient_stock_count == 0) ? true : false;
        return response()->json(compact('invoice_items', 'can_submit'), 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        $user = $this->getUser();
        $customer_ids = $request->customer_ids;
        $invoice_items = json_decode(json_encode($request->invoice_items));
        // $dupicate_invoice = Invoice::where('invoice_number', $request->invoice_number)->first();
        // if ($dupicate_invoice) {
        //     return response()->json(['message' => 'Duplicate Invoice'], 500);
        // }
        foreach ($customer_ids as $customer_id) {
            $invoice = new Invoice();
            $invoice->warehouse_id = $request->warehouse_id;
            $invoice->customer_id = $customer_id;
            $invoice->subtotal = $request->subtotal;
            $invoice->discount = $request->discount;
            $invoice->amount = $request->amount;
            $invoice->invoice_number = $this->nextReceiptNo('invoice'); // $request->invoice_number; // $this->nextInvoiceNo();
            $invoice->status = $request->status;
            $invoice->notes = $request->notes;
            $invoice->invoice_date = date('Y-m-d H:i:s', strtotime($request->invoice_date));
            $invoice->save();
            $this->incrementReceiptNo('invoice');
            $title = "New invoice generated";
            $description = "New $invoice->status invoice ($invoice->invoice_number) was generated by $user->name ($user->email)";
            //log this action to invoice history
            $this->createInvoiceHistory($invoice, $title, $description);
            //create items invoiceed for
            $total = $this->createInvoiceItems($invoice, $invoice_items);
            //////update next invoice number/////
            $invoice->subtotal = $total;
            $invoice->amount = $total - $invoice->discount;
            $invoice->save();
            //log this activity
            $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
            $this->logUserActivity($title, $description, $roles);
        }
        return 'successful';
    }

    public function bulkUpload(Request $request)
    {
        // return $request;
        $user = $this->getUser();
        $bulk_invoices = json_decode(json_encode($request->bulk_invoices));
        $warehouse_id = $request->warehouse_id;
        $error = [];
        $header_error = [];
        foreach ($bulk_invoices as $bulk_invoice) {
            $customer_id = $bulk_invoice->customer_id;
            $invoice_number = $bulk_invoice->invoice_number;
            $invoice_date = $bulk_invoice->invoice_date;
            $dupicate_invoice = Invoice::where('invoice_number', $invoice_number)->first();
            if (!$dupicate_invoice) {
                // We want to make sure all headers are set and what we need
                $invoice_item_headers = json_decode(json_encode($bulk_invoice->bulk_invoices_header));
                if (!in_array('Description of Goods', $invoice_item_headers)) {
                    $header_error[] = "'Description of Goods' is needed as an header";
                }
                if (!in_array('Quantity', $invoice_item_headers)) {
                    $header_error[] = "'Quantity' is needed as an header";
                }
                if (!in_array('Rate', $invoice_item_headers)) {
                    $header_error[] = "'Rate' is needed as an header";
                }
                if (!in_array('per', $invoice_item_headers)) {
                    $header_error[] = "'per' is needed as an header";
                }
                if (!in_array('Amount', $invoice_item_headers)) {
                    $header_error[] = "'Amount' is needed as an header";
                }
                if (empty($header_error)) {
                    $invoice_items = json_decode(json_encode($bulk_invoice->bulk_invoices_data));
                    $amount = 0;
                    $discount = 0;
                    foreach ($invoice_items as $item) {
                        $amount_label = 'Amount';
                        $amount += $item->$amount_label;
                    }
                    $invoice = new Invoice();
                    $invoice->warehouse_id = $warehouse_id;
                    $invoice->customer_id = $customer_id;
                    $invoice->subtotal = $amount;
                    $invoice->discount = $discount;
                    $invoice->amount = $amount;
                    $invoice->invoice_number = $invoice_number; // $this->nextInvoiceNo();
                    $invoice->status = 'pending';
                    $invoice->notes = 'BEING GOODS SOLD TO THE ABOVE CUSTOMER';
                    $invoice->invoice_date = date('Y-m-d H:i:s', strtotime($invoice_date));
                    $invoice->save();
                    if ($invoice->save()) {
                        $title = "New invoice generated";
                        $description = "New $invoice->status invoice ($invoice->invoice_number) was generated by $user->name ($user->email)";
                        //log this action to invoice history
                        $this->createInvoiceHistory($invoice, $title, $description);
                        foreach ($invoice_items as $item) {
                            $description_label = 'Description of Goods';
                            $quantity_label = 'Quantity';
                            $rate_label = 'Rate';
                            $amount_label = 'Amount';
                            $type_label = 'per';

                            $product = Item::where('name', $item->$description_label)->first();
                            if ($product) {
                                $invoice_item = new InvoiceItem();
                                $invoice_item->warehouse_id = $warehouse_id;
                                $invoice_item->invoice_id = $invoice->id;
                                $invoice_item->item_id = $product->id;
                                $invoice_item->quantity = $item->$quantity_label;
                                $invoice_item->quantity_per_carton = $product->quantity_per_carton;
                                $invoice_item->no_of_cartons = $invoice_item->quantity / $invoice_item->quantity_per_carton;
                                $invoice_item->type = $item->$type_label;
                                $invoice_item->rate = $item->$rate_label;
                                $invoice_item->amount = $item->$amount_label;
                                $invoice_item->save();
                            } else {
                                $error[] = $item->$description_label . ' is not found in the database. This might be a spelling issue. Contact the admin';
                            }
                        }
                    }
                } else {
                    // Terminate if a header is not set
                    return response()->json(['message' => 'error', 'error' => $header_error], 200);
                }
            } else {
                $error[] = 'Invoice number: ' . $invoice_number . ' already exists.';
            }
        }
        $message = 'success';
        if (!empty($error)) {
            $message = 'error';
        } else {
        }
        return response()->json(['message' => $message, 'error' => $error], 200);
    }
    private function createInvoiceHistory($invoice, $title, $description)
    {
        $invoice_history = new InvoiceHistory();
        $invoice_history->invoice_id = $invoice->id;
        $invoice_history->title = $title;
        $invoice_history->description = $description;
        $invoice_history->save();
    }

    private function createInvoiceItems($invoice, $invoice_items)
    {
        $total = 0;
        foreach ($invoice_items as $item) {
            // $batches = $item->batches;
            $invoice_item = new InvoiceItem();
            $invoice_item->warehouse_id = $invoice->warehouse_id;
            $invoice_item->invoice_id = $invoice->id;
            $invoice_item->item_id = $item->item_id;
            $invoice_item->quantity = $item->quantity;
            $invoice_item->quantity_per_carton = $item->quantity_per_carton;
            $invoice_item->no_of_cartons = $item->quantity / $item->quantity_per_carton; //$item->no_of_cartons;
            $invoice_item->type = $item->type;
            $invoice_item->rate = ($item->rate) ? $item->rate : 0;
            $invoice_item->amount = $item->quantity * $item->rate;
            $invoice_item->save();
            $total += $invoice_item->amount;
        }
        return $total;
    }

    public function edit(Request $request, Invoice $invoice)
    {
        //
        $invoice_id = $invoice->id;
        $invoiceQuery = InvoiceItem::query();
        $user = $this->getUser();
        // $warehouse_id = $request->warehouse_id;
        $invoices = [];
        $date = date('Y-m-d', strtotime('now'));

        $invoice_items = $invoiceQuery->with([
            'item.stocks' => function ($q) {
                $q->groupBy('item_id')
                    ->where('warehouse_id', '!=', 7)
                    ->whereRaw('confirmed_by IS NOT NULL')
                    ->select(['id', 'item_id', \DB::raw('(SUM(balance) -  SUM(reserved_for_supply)) as total_balance')]);
                // ->first();
            },
            'invoice.warehouse',
            'invoice.customer.user'
        ])
            // ->orderBy('updated_at', 'DESC')
            ->where('invoice_id', $invoice_id)
            ->get();

        return response()->json(compact('invoice_items'));
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Invoice\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Invoice $invoice)
    {
        //
        $user = $this->getUser();
        $invoice_items = json_decode(json_encode($request->invoice_items));
        $invoice = Invoice::find($request->id);
        $old_invoice_number = $invoice->invoice_number;
        $invoice->invoice_number = $request->invoice_number;
        $invoice->warehouse_id = $request->warehouse_id;
        $invoice->customer_id = $request->customer_id;
        $invoice->invoice_date = date('Y-m-d H:i:s', strtotime($request->invoice_date));
        $invoice->subtotal = $request->subtotal;
        $invoice->discount = $request->discount;
        $invoice->amount = $request->amount;
        $invoice->notes = $request->notes;
        $invoice->save();
        $extra_info = "";
        if ($old_invoice_number !== $invoice->invoice_number) {
            $extra_info = $old_invoice_number . ' was changed to ' . $invoice->invoice_number;
        }
        if ($invoice->waybillItems()->count() < 1) {
            // delete existing invoice items
            $invoice->invoiceItems()->delete();
            //create new set of invoice items
            $total = $this->createInvoiceItems($invoice, $invoice_items);
            $invoice->subtotal = $total;
            $invoice->amount = $total - $invoice->discount;
            $invoice->save();
        }

        $title = "Invoice modified";
        $description = "invoice ($old_invoice_number) was updated by $user->name ($user->email) " . $extra_info;
        //log this action to invoice history
        $this->createInvoiceHistory($invoice, $title, $description);
        //create items invoiceed for

        //////update next invoice number/////
        // $this->incrementInvoiceNo();

        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($invoice);
    }

    private function updateInvoiceItems($invoice_items)
    {
        foreach ($invoice_items as $item) {
            // $batches = $item->batches;
            $invoice_item = InvoiceItem::find($item->id);
            // keep the old quantity
            // $old_quantity = $invoice_item->quantity;

            $invoice_item->quantity = $item->quantity;
            $invoice_item->no_of_cartons = $item->no_of_cartons;
            $invoice_item->item_id = $item->item_id;
            $invoice_item->type = $item->type;
            $invoice_item->rate = $item->rate;
            $invoice_item->amount = $item->amount;
            $invoice_item->save();

            // $batches = $invoice_item->batches;
            // $this->removeOldInvoiceItemBatchesAndCreateNewOne($invoice_item, $batches, $old_quantity);
        }
    }
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Invoice\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function show(Invoice $invoice)
    {
        //
        $invoice = $invoice->with([
            'warehouse',
            'waybillItems',
            'customer.user',
            'customer.type',
            'confirmer',
            'invoiceItems.item',
            'histories' => function ($q) {
                $q->orderBy('id', 'DESC');
            }
        ])->find($invoice->id);
        return response()->json(compact('invoice'), 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Invoice\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function changeInvoiceStaus(Request $request, Invoice $invoice)
    {
        //
        $user = $this->getUser();
        $status = $request->status;

        $invoice->status = $status;
        $invoice->save();
        $title = "Invoice status updated";
        $description = "Invoice ($invoice->invoice_number) status changed to " . strtoupper($invoice->status) . " by $user->name ($user->email)";
        //log this action to invoice history
        $this->createInvoiceHistory($invoice, $title, $description);

        // log this activity
        $roles = ['assistant admin', 'warehouse manager'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($invoice);
    }
    public function showWaybill(Waybill $waybill)
    {
        //
        $date = date('Y-m-d', strtotime($waybill->created_at));
        $waybill = $waybill->with([
            'invoices.customer' => function ($q) {
                $q->withTrashed();
            },
            'invoices.customer.user',
            'waybillItems' => function ($q) {
                $q->where('quantity', '>', 0);
            },
            'waybillItems.invoice.customer' => function ($q) {
                $q->withTrashed();
            },
            'waybillItems.invoice.customer.user',
            'waybillItems.item.stocks',
            'waybillItems.invoiceItem.batches' => function ($q) use ($date) {
                $q->where('created_at', 'LIKE', '%' . $date . '%');
            },
            'waybillItems.invoiceItem.batches.itemStockBatch',
            'dispatcher.vehicle.vehicleDrivers.driver.user',
            'trips',
            'dispatchProducts'
        ])->find($waybill->id);
        return response()->json(compact('waybill'), 200);
    }
    // this fetches all generated waybills
    public function waybills(Request $request)
    {
        $searchParams = $request->all();
        $waybillQuery = Waybill::query();
        $limit = Arr::get($searchParams, 'limit', static::ITEM_PER_PAGE);
        $keyword = Arr::get($searchParams, 'keyword', '');

        $warehouse_id = $request->warehouse_id;
        $waybills = [];

        if (isset($request->status) && $request->status != '') {
            $status = $request->status;

            ////// query by status //////////////
            $waybillQuery->with([
                'invoices.customer.user',
                'dispatcher.vehicle.vehicleDrivers.driver.user',
                'trips',
                'dispatchProducts'
            ])->where(['warehouse_id' => $warehouse_id, 'status' => $status]);
            if (!empty($keyword)) {
                $waybillQuery->where(function ($q) use ($keyword) {
                    $q->where('waybill_no', 'LIKE', '%' . $keyword . '%');
                    $q->orWhere('dispatch_company', 'LIKE', '%' . $keyword . '%');
                    $q->orWhereHas('invoices', function ($q) use ($keyword) {
                        $q->where('invoice_number', 'LIKE', '%' . $keyword . '%');
                    });
                });
            }
            $waybills = $waybillQuery->orderBy('id', 'DESC')->paginate($limit);
        }
        // if (isset($request->from, $request->to, $request->status) && $request->from != '' && $request->from != '' && $request->status != '') {
        //     $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
        //     $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
        //     $status = $request->status;
        //     $panel = $request->panel;
        //     $invoices = Waybill::with(['dispatcher.vehicle.vehicleDrivers.driver.user', 'waybillItems.invoice.customer.user', 'waybillItems.item'])->where(['warehouse_id' => $warehouse_id, 'status' => $status])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->get();
        // }
        return response()->json(compact('waybills'));
    }
    // this fetches available drivers so that dispatching orders could be assigned to them
    public function fetchAvailableVehicles(Request $request)
    {
        //
        $warehouse_id = $request->warehouse_id;
        $available_vehicles = Vehicle::with('vehicleDrivers.driver.user')->where(['warehouse_id' => $warehouse_id, 'availabilty' => 'available'])->get();
        // $available_vehicles = [];
        // foreach ($vehicles as $vehicle) {
        //     $dispatched_waybill = DispatchedWaybill::where('vehicle_id', $vehicle->id)->orderBy('id', 'Desc')->first();
        //     if ($dispatched_waybill) {
        //         if ($dispatched_waybill->waybill->status === 'delivered') {
        //             $available_vehicles[] = $vehicle;
        //         }
        //     } else {
        //         $available_vehicles[] = $vehicle;
        //     }
        // }
        return response()->json(compact('available_vehicles'), 200);
    }

    private function createInvoiceItemBatches($waybill_item, $invoice_item, $batches, $update = true)
    {
        $item_id = $invoice_item->item_id;
        foreach ($batches as $batch) {

            $supply_quantity = $batch->supply_quantity;
            if ($supply_quantity > 0) {

                $batches_of_items_in_stock = ItemStockSubBatch::where(['warehouse_id' => $waybill_item->warehouse_id, 'item_id' => $item_id])
                    ->where('batch_no', $batch->batch_no)
                    ->where('expiry_date', $batch->expiry_date)
                    ->whereRaw('balance - reserved_for_supply > 0')
                    ->whereRaw('confirmed_by IS NOT NULL')
                    ->orderBy('expiry_date')
                    ->orderBy('batch_no')
                    ->get();
                if ($batches_of_items_in_stock->isNotEmpty()) {
                    foreach ($batches_of_items_in_stock as $item_sub_batch) {
                        if ($supply_quantity < 1) {
                            break;
                        }
                        # code...

                        // $item_sub_batch = ItemStockSubBatch::find($batch);
                        $real_balance = $item_sub_batch->balance - $item_sub_batch->reserved_for_supply;
                        $invoice_item_batch = new InvoiceItemBatch();
                        $invoice_item_batch->invoice_id = $invoice_item->invoice_id;
                        $invoice_item_batch->invoice_item_id = $invoice_item->id;
                        $invoice_item_batch->waybill_item_id = $waybill_item->id;
                        $invoice_item_batch->item_stock_sub_batch_id = $item_sub_batch->id;

                        if ($supply_quantity <= $real_balance) {
                            $invoice_item_batch->to_supply = $supply_quantity;
                            $invoice_item_batch->quantity = $supply_quantity;
                            $invoice_item_batch->save();
                            if ($update === true) {
                                $invoice_item->quantity_supplied += $supply_quantity;
                                $invoice_item->save();
                            }


                            $item_sub_batch->reserved_for_supply += $supply_quantity;
                            $item_sub_batch->save();
                            $supply_quantity = 0;
                            break;
                        } else {
                            $invoice_item_batch->to_supply = $real_balance;
                            $invoice_item_batch->quantity = $real_balance;
                            $invoice_item_batch->save();
                            if ($update === true) {
                                $invoice_item->quantity_supplied += $real_balance;
                                $invoice_item->save();
                            }

                            $item_sub_batch->reserved_for_supply += $real_balance;
                            $item_sub_batch->save();
                            $supply_quantity -= $real_balance;
                        }
                    }
                }
            }
        }
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generateWaybill(Request $request)
    {
        //
        // return $request;
        set_time_limit(0);
        $user = $this->getUser();
        // $invoice = Invoice::find($request->invoice_id);
        $warehouse_id = $request->warehouse_id;
        $message = '';
        $invoice_items = json_decode(json_encode($request->invoice_items));
        if (empty($invoice_items)) {
            return response()->json(['message' => 'Pick at least one invoice to generate a waybill'], 500);
        }
        $waybill_no = $this->nextReceiptNo('waybill');
        $waybill = new Waybill();
        $waybill->warehouse_id = $warehouse_id;
        // $waybill->invoice_id = $request->invoice_id;
        $waybill->waybill_no = $waybill_no;
        $waybill->status = $request->status;
        $waybill->save();
        // create way bill items
        $waybill_item_obj = new WaybillItem();
        $partial_waybill_generated = [];

        $invoice_ids = [];
        foreach ($invoice_items as $invoice_item) {
            $batches = $invoice_item->batches;
            if (!empty($batches)) {
                # code...

                $for_supply = (int) $invoice_item->quantity_for_supply;
                if ($for_supply > 0) {
                    $invoice_item_update = InvoiceItem::find($invoice_item->id);
                    $original_quantity = $invoice_item_update->quantity;
                    $quantity_supplied = $invoice_item_update->quantity_supplied;
                    if ($original_quantity > $quantity_supplied) {
                        if (($original_quantity - $quantity_supplied) >= $for_supply) {


                            // $actual_supply_quantity = $for_supply; //  - $balance;
                            $quantity_supplied += $for_supply;
                            // $invoice_item_update->save();

                            $waybill_item = $waybill_item_obj->createWaybillItems($waybill->id, $warehouse_id, $invoice_item_update, $batches);


                            $this->createInvoiceItemBatches($waybill_item, $invoice_item_update, $batches);


                            if ($original_quantity > $quantity_supplied) {
                                $partial_waybill_generated[] = $invoice_item_update->invoice_id;
                            }
                            $invoice_ids[] = $invoice_item_update->invoice_id;
                        }
                    }
                }
            }
        }

        $invoice_ids = array_unique($invoice_ids);

        if (empty($invoice_ids)) {
            $waybill->waybill_no .= '-deleted-' . time();
            $waybill->save();
            $waybill->delete();
            return response()->json(['message' => 'Pick at least one invoice to generate a waybill'], 500);
        }


        $this->incrementReceiptNo('waybill');
        $partial_waybill_generated = array_unique($partial_waybill_generated);
        $invoice_numbers = '';
        foreach ($invoice_ids as $invoice_id) {
            $invoice = Invoice::find($invoice_id);
            $invoice->waybill_generated = 0;
            $invoice->full_waybill_generated = '0';
            if (!in_array($invoice_id, $partial_waybill_generated)) {

                $invoice->full_waybill_generated = '1';
            }
            $invoice->save();
            $invoice_numbers = $invoice_numbers . $invoice->invoice_number . ', ';

            $title = "Waybill Generated";
            $description = "Waybill ($waybill->waybill_no) generated for invoices ($invoice_numbers) by $user->name ($user->email)";
            //log this action to invoice history
            $this->createInvoiceHistory($invoice, $title, $description);
        }


        $waybill->invoices()->sync($invoice_ids);
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        // }
        // $this->createDispatchedWaybill($waybill, $request);

        return response()->json(compact('waybill'), 200);
    }
    public function editWaybill(Waybill $waybill)
    {
        //
        $warehouse_id = $waybill->warehouse_id;
        $waybill = $waybill->with([
            'waybillItems.invoiceItem.item.stocks' => function ($p) use ($warehouse_id) {
                $p->groupBy('expiry_date', 'batch_no');
                $p->whereRaw('balance - reserved_for_supply > 0')->where('warehouse_id', $warehouse_id)->whereRaw('confirmed_by IS NOT NULL')
                    ->orderBy('expiry_date')
                    ->orderBy('batch_no')
                    ->select('*', \DB::raw('(SUM(balance) - SUM(reserved_for_supply)) as total_balance'));
            },

            'waybillItems.batches' => function ($q) {
                $q->groupBy('item_stock_sub_batch_id')
                    ->select('*', \DB::raw('SUM(to_supply) as to_supply_total'), \DB::raw('SUM(quantity) as quantity_total'));
            },
            'waybillItems.batches.itemStockBatch',
        ])->find($waybill->id);
        return response()->json(compact('waybill'), 200);
    }
    public function updateWaybill(Request $request, $waybill_id)
    {
        // return $request;
        set_time_limit(0);
        $user = $this->getUser();
        $waybill = Waybill::find($waybill_id);
        $invoices = $waybill->invoices;
        // we fetch previous waybill items
        $waybill_items = json_decode(json_encode($request->waybill_items));
        foreach ($waybill_items as $waybill_item) {
            // we want to get all invoice item for this waybill item and deduct what was reserved
            $old_quantity = $waybill_item->old_quantity;
            $diff = $waybill_item->quantity_for_supply - $old_quantity;
            $waybillItem = WaybillItem::with('item', 'invoice')->find($waybill_item->id);
            $waybillItem->quantity = $waybill_item->quantity_for_supply;
            $waybillItem->save();

            $invoice_item = $waybillItem->invoiceItem;
            $invoice_item->quantity_supplied += $diff; // add the differnce between the new and former quantity
            $invoice_item->save();

            $invoice = Invoice::find($invoice_item->invoice_id);
            if ($invoice_item->quantity > $invoice_item->quantity_supplied) {
                $invoice->full_waybill_generated = '0';
                $invoice->save();
            }

            // we wanna get and remove all reserved products

            // $waybill_quantity = $waybillItem->quantity;
            $reserved_batches = $waybill_item->batches;
            $batches = new Collection();
            foreach ($reserved_batches as $reserved_batch) {
                $old_supply = (int) $reserved_batch->old_supply_quantity;
                $new_supply_quantity = $reserved_batch->supply_quantity;
                $batch_no = $reserved_batch->batch_no;
                $expiry_date = $reserved_batch->expiry_date;
                if ($old_supply > 0) {

                    $waybill_batch_id = $reserved_batch->waybill_batch_id;
                    $invoice_item_batch = InvoiceItemBatch::with('itemStockBatch')->find($waybill_batch_id);
                    $item_stock_batch = $invoice_item_batch->itemStockBatch;
                    $item_stock_batch->reserved_for_supply -= $old_supply;
                    $item_stock_batch->save();

                    $invoice_item_batch->forceDelete();
                }
                if ($new_supply_quantity > 0) {
                    $batches->push((object) [
                        'batch_no' => $batch_no,
                        'expiry_date' => $expiry_date,
                        'supply_quantity' => $new_supply_quantity

                    ]);
                }
            }
            // return $batches;
            $this->createInvoiceItemBatches($waybillItem, $invoice_item, $batches, false);

            if ($diff > 0) {
                $item = $waybillItem->item->name;
                $invoice = $waybillItem->invoice;
                $title = "Waybill items Updated";
                $description = "Quantity of $item in waybill ($waybill->waybill_no) was updated from $old_quantity to $waybillItem->quantity by $user->name ($user->email)";
                //log this action to invoice history
                $this->createInvoiceHistory($invoice, $title, $description);
            }
            // delete the waybill item
            // $waybill_item->delete();
        }
        $waybill->edited = 1;
        $waybill->save();
        return response()->json(compact('waybill'), 200);
    }

    public function changeWaybillStatus(Request $request, Waybill $waybill)
    {
        //
        $item_in_stock_obj = new ItemStockSubBatch();
        $invoice_item_obj = new InvoiceItem();
        $user = $this->getUser();

        $vehicle = ($waybill->dispatcher) ? $waybill->dispatcher->vehicle : '';
        $status = $request->status;
        if ($status === 'in transit') {
            // list($out_of_stock_count, $message) = $item_in_stock_obj->checkStockBalanceForEachWaybillItem($waybill->waybillItems);
            // if ($out_of_stock_count > 0) {
            //     return response()->json(compact('message'), 500);
            // }
            // change vehicle status to 'in transit'
            if ($vehicle) {
                $this->setVehicleAvailability($vehicle, 'in transit');
            }
            $waybill_items_ids = $waybill->waybillItems->pluck('id');
            // return $waybill_items_ids;
            $item_in_stock_obj->sendItemInStockForDelivery($waybill_items_ids);
            // let's update the invoice items for this waybill
        }


        // update invoice items to account for partial supplies and complete ones
        $invoice_item_obj->updateInvoiceItemsForWaybill($waybill->waybillItems, $status);
        // update items in stock based on waybill status
        // we want to automatically change the status to delivered once the waybill has been moved to transtit
        // $status = 'delivered';
        if ($status === 'delivered') {
            $waybill->delivery_date = date('Y-m-d H:i:s', strtotime('now'));
        }
        // update waybill status
        $waybill->status = $status;
        $waybill->save();

        $invoices = $waybill->invoices;
        $title = "Waybill status updated";
        $description = "Waybill ($waybill->waybill_no) status updated to " . strtoupper($waybill->status) . " by $user->name ($user->email)";
        if ($status === 'delivered') {
            // change vehicle status to 'available'
            if ($vehicle) {

                $this->setVehicleAvailability($vehicle, 'available');
            }
            $item_in_stock_obj->confirmItemInStockAsSupplied($waybill->dispatchProducts);
            foreach ($invoices as $invoice) {

                $invoice->status = $status;
                // check for partial supplies
                $incomplete_invoice_item = $invoice->invoiceItems()->whereRaw('quantity - quantity_supplied > 0')->get();
                if ($incomplete_invoice_item->isNotEmpty()) {
                    $invoice->status = 'partially supplied';
                    $invoice->full_waybill_generated = '0';

                    // $invoice->status = 'pending';
                }
                $invoice->save();
                //log this action to invoice history
                $this->createInvoiceHistory($invoice, $title, $description);
            }
        }


        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
    }
    private function createDispatchedWaybill($waybill_id, $vehicle_id)
    {
        //
        $dispatched_waybill = DispatchedWaybill::where('waybill_id', $waybill_id)->first();
        if (!$dispatched_waybill) {
            $dispatched_waybill = new DispatchedWaybill();
        }
        $dispatched_waybill->waybill_id = $waybill_id;
        $dispatched_waybill->vehicle_id = $vehicle_id;
        $dispatched_waybill->save();
    }
    public function waybillExpenses(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $trip_no = $this->nextReceiptNo('trip');
        $vehicles = Vehicle::with('vehicleDrivers.driver.user')/*->where('warehouse_id', $warehouse_id)*/ ->get();
        $delivery_trips = DeliveryTrip::with('cost.confirmer', 'waybills', 'vehicle.vehicleDrivers.driver.user')->orderBy('id', 'DESC')->where(['warehouse_id' => $warehouse_id])->paginate($request->limit);

        $waybills_with_pending_wayfare = Waybill::where(['warehouse_id' => $warehouse_id, 'waybill_wayfare_status' => 'pending'])->whereRaw('confirmed_by IS NOT NULL')->get();

        return response()->json(compact('delivery_trips', 'waybills_with_pending_wayfare', 'trip_no', 'vehicles'), 200);
    }

    public function addWaybillExpenses(Request $request)
    {
        $trip_no = $request->trip_no;
        $delivery_trip = DeliveryTrip::where('trip_no', $trip_no)->first();
        if ($delivery_trip) {
            // $this->incrementReceiptNo('waybill');
            $trip_no = $this->nextReceiptNo('trip');
        }
        $waybill_ids = $request->waybill_ids;
        $description = $request->description;
        $warehouse_id = $request->warehouse_id;
        $vehicle_id = $request->vehicle_id;
        $vehicle_no = $request->vehicle_no;
        $dispatchers = $request->dispatchers;
        // if ($vehicle_id !== null) {
        //     $vehicle = Vehicle::with('vehicleDrivers.driver.user')->find($vehicle_id);
        // }
        $dispatch_company = $request->dispatch_company;

        # code...

        $delivery_trip = new DeliveryTrip();
        $delivery_trip->warehouse_id = $warehouse_id;
        $delivery_trip->vehicle_id = $vehicle_id;
        $delivery_trip->dispatch_company = $dispatch_company;
        $delivery_trip->vehicle_no = $vehicle_no;
        $delivery_trip->dispatchers = $dispatchers;
        $delivery_trip->trip_no = $trip_no;
        $delivery_trip->description = $description;
        if ($delivery_trip->save()) {
            //update next receipt no
            $this->incrementReceiptNo('trip');
            $delivery_trip->waybills()->syncWithoutDetaching($waybill_ids); // add all waybills for this trip
            foreach ($delivery_trip->waybills as $waybill) {
                // update waybill wayfare status
                if ($vehicle_id != null) {
                    $this->createDispatchedWaybill($waybill->id, $vehicle_id);
                }
                $waybill->dispatch_company = $dispatch_company;
                $waybill->waybill_wayfare_status = 'given';
                $waybill->save();
            }
            // populate delivery trip expenses
            $delivery_trip_id = $delivery_trip->id;
            $delivery_expense = DeliveryTripExpense::where('delivery_trip_id', $delivery_trip_id)->first();
            if (!$delivery_expense) {
                $delivery_expense = new DeliveryTripExpense();
                $delivery_expense->warehouse_id = $warehouse_id;
                $delivery_expense->delivery_trip_id = $delivery_trip_id;
                $delivery_expense->amount = $request->amount;
                $delivery_expense->details = $description;
                $delivery_expense->save();
            }
        }
        $user = $this->getUser();
        $title = "Created waybill delivery cost";
        $description = "New delivery cost for trip no.: " . $trip_no . " was created by $user->name ($user->email)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];

        $this->logUserActivity($title, $description, $roles);
        return $this->showDeliveryTrip($delivery_trip->id, $warehouse_id);
    }

    public function deliveryTripsForExtraCost(Request $request)
    {
        $regular_delivery_trips = DeliveryTripExpense::with('deliveryTrip')->where('expense_type', 'regular')->get();
        $extra_delivery_costs = DeliveryTripExpense::with(['confirmer', 'deliveryTrip.waybills', 'deliveryTrip.vehicle.vehicleDrivers.driver.user'])->where('expense_type', 'extra')->get();
        return response()->json(compact('regular_delivery_trips', 'extra_delivery_costs'), 200);
    }

    public function addExtraDeliveryCost(Request $request)
    {
        $delivery_trip_id = $request->delivery_trip_id;
        $delivery_trip = DeliveryTrip::find($delivery_trip_id);
        $delivery_expense = DeliveryTripExpense::where(['delivery_trip_id' => $delivery_trip_id, 'expense_type' => 'extra'])->first();
        if (!$delivery_expense) {
            $delivery_expense = new DeliveryTripExpense();
            $delivery_expense->warehouse_id = $delivery_trip->warehouse_id;
            $delivery_expense->delivery_trip_id = $request->delivery_trip_id;
            $delivery_expense->expense_type = 'extra';
            $delivery_expense->amount = $request->amount;
            $delivery_expense->details = $request->details;
            $delivery_expense->save();
        }
        $delivery_expense = DeliveryTripExpense::with(['confirmer', 'deliveryTrip.waybills', 'deliveryTrip.vehicle.vehicleDrivers.driver.user'])->where('expense_type', 'extra')->find($delivery_expense->id);
        return response()->json(compact('delivery_expense'), 200);
    }
    private function showDeliveryTrip($delivery_trip_id, $warehouse_id)
    {
        $trip_no = $this->nextReceiptNo('trip');
        $delivery_trip = DeliveryTrip::with('cost', 'waybills', 'vehicle.vehicleDrivers.driver.user')->orderBy('id', 'DESC')->find($delivery_trip_id);
        $waybills_with_pending_wayfare = Waybill::where(['warehouse_id' => $warehouse_id, 'waybill_wayfare_status' => 'pending'])->get();
        return response()->json(compact('delivery_trip', 'waybills_with_pending_wayfare', 'trip_no'), 200);
    }
    public function addWaybillToTrip(Request $request)
    {
        $waybill_id = $request->waybill_id;
        $delivery_trip_id = $request->delivery_trip_id;
        $delivery_trip = DeliveryTrip::find($delivery_trip_id);
        // if($delivery_trip->waybills()->count() == 1 ){
        //     // delete the delivery trip entry
        // }
        // $delivery_trip->waybills()->syncWithoutDetaching($waybill_id);
        $delivery_trip->waybills()->syncWithoutDetaching($waybill_id);
        if ($delivery_trip->vehicle_id !== NULL) {
            $this->createDispatchedWaybill($waybill_id, $delivery_trip->vehicle_id);
        }
        // update waybill wayfare status to pending
        $waybill = Waybill::find($waybill_id);
        $waybill->waybill_wayfare_status = 'given';
        $waybill->save();
        $actor = $this->getUser();
        $title = "Waybill added to trip";
        $description = "Waybill $waybill->waybill_no was added to trip with trip no.: " . $delivery_trip->trip_no . " by $actor->name ($actor->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];

        $this->logUserActivity($title, $description, $roles);
        return $this->showDeliveryTrip($delivery_trip->id, $delivery_trip->warehouse_id);
    }

    public function stabilizeDeliveryTripToWaybillRelationship()
    {
        // $dispatched_products = DispatchedProduct::groupBy('item_stock_sub_batch_id')
        //     ->select('*', \DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->get();
        // foreach ($dispatched_products as $dispatched_product) {
        //     $batch_id = $dispatched_product->item_stock_sub_batch_id;
        //     $supplied = $dispatched_product->total_quantity_supplied;
        //     DB::table('item_stock_sub_batches_check')->where('id', $batch_id)->update(['supplied' => $supplied]);
        // }

        // InvoiceItem::with('invoice')->groupBy('invoice_id')->select('*', \DB::raw('SUM(quantity) as order_quantity'), \DB::raw('SUM(quantity_supplied + quantity_reversed) as supply_quantity'))
        //     ->chunkById(200, function ($invoice_items) {
        //         foreach ($invoice_items as $invoice_item) {
        //             $invoice = $invoice_item->invoice;
        //             if ($invoice) {

        //                 $diff = $invoice_item->order_quantity = $invoice_item->supply_quantity;
        //                 if ($diff == 0) {

        //                     $invoice->status = 'delivered';
        //                     $invoice->save();
        //                 }
        //             }

        //         }

        //     }, $column = 'id');

    }
    // public function stabilizeDeliveryTripToWaybillRelationship()
    // {
    //     InvoiceItem::groupBy('invoice_id')
    //         ->select('*', \DB::raw('SUM(amount) as total_amount'))
    //         ->chunkById(200, function ($invoice_items) {
    //             foreach ($invoice_items as $invoice_item) {
    //                 $invoice = Invoice::find($invoice_item->invoice_id);
    //                 if ($invoice) {

    //                     $discount = $invoice->discount;
    //                     $invoice->subtotal = $invoice_item->total_amount;
    //                     $invoice->amount = $invoice_item->total_amount - $discount;
    //                     $invoice->save();
    //                 }
    //             }
    //         }, $column = 'id');
    // }
    // public function stabilizeDeliveryTripToWaybillRelationship()
    // {
    //     return Waybill::leftJoin('delivery_trip_waybill', 'delivery_trip_waybill.waybill_id', 'waybills.id')->where('delivery_trip_waybill.waybill_id', NULL)->select('waybill_no')->get();


    // }
    // public function stabilizeDeliveryTripToWaybillRelationship()
    // {
    //     $notifications = Notification::where('notifiable_id', 1)->where('data', 'LIKE', '%Waybill added to trip%')->select('data')->get();

    //     foreach ($notifications as $notification) {
    //         $data = $notification->data;
    //         $description = $data['description'];
    //         $str_array = explode(' ', $description);
    //         $waybill_no = $str_array[1];
    //         $trip_no = $str_array[9];
    //         $waybill = Waybill::where('waybill_no', $waybill_no)->first();
    //         $delivery_trip = DeliveryTrip::where('trip_no', operator: $trip_no)->first();
    //         if ($delivery_trip) {
    //             if ($waybill) {
    //                 $delivery_trip->waybills()->syncWithoutDetaching($waybill->id);
    //             }

    //         }
    //     }


    // }
    // public function stabilizeDeliveryTripToWaybillRelationship()
    // {
    //     Waybill::with('trips')->chunkById(200, function ($waybills) {
    //         foreach ($waybills as $waybill) {
    //             $trips = $waybill->trips;
    //             $trip_count = count($trips);
    //             if ($trip_count > 1) {
    //                 for ($i = 1; $i < $trip_count; $i++) {
    //                     $trip_id = $trips[$i]->id;
    //                     $waybill->trips()->detach($trip_id);
    //                 }
    //             }
    //         }
    //     }, $column = 'id');
    // }
    public function changeTripVehicle(Request $request)
    {
        $actor = $this->getUser();
        $vehicle_id = $request->vehicle_id;
        $vehicle = Vehicle::find($vehicle_id);
        $delivery_trip_id = $request->delivery_trip_id;
        $delivery_trip = DeliveryTrip::find($delivery_trip_id);
        $old_number = $delivery_trip->vehicle_no;
        $delivery_trip->vehicle_id = $vehicle_id;
        $delivery_trip->vehicle_no = $vehicle->plate_no;
        $delivery_trip->save();

        $title = "Trip vehicle changed";
        $description = "Vehicle no.: $old_number was changed to " . $vehicle->plate_no . " for trip no.: " . $delivery_trip->trip_no . " by $actor->name ($actor->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];

        $this->logUserActivity($title, $description, $roles);
        $delivery_trip = $delivery_trip->with('cost.confirmer', 'waybills', 'vehicle.vehicleDrivers.driver.user')->find($delivery_trip->id);
        return response()->json(compact('delivery_trip'), 200);
    }
    public function detachWaybillFromTrip(Request $request)
    {
        $waybill_id = $request->waybill_id;
        $delivery_trip_id = $request->delivery_trip_id;
        $delivery_trip = DeliveryTrip::find($delivery_trip_id);
        // if($delivery_trip->waybills()->count() == 1 ){
        //     // delete the delivery trip entry
        // }
        $waybill = Waybill::find($waybill_id);
        $delivery_trip->waybills()->detach($waybill_id);

        // update waybill wayfare status to pending

        $waybill->dispatcher()->delete();
        $waybill->waybill_wayfare_status = 'pending';
        $waybill->save();
        $actor = $this->getUser();
        $title = "Waybill removed from trip";
        $description = "Waybill $waybill->waybill_no was removed from trip with trip no.: " . $delivery_trip->trip_no . " by $actor->name ($actor->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];

        $this->logUserActivity($title, $description, $roles);
        return $this->showDeliveryTrip($delivery_trip->id, $delivery_trip->warehouse_id);
    }
    private function setVehicleAvailability(Vehicle $vehicle, $status)
    {
        $vehicle->availability = $status;
        $vehicle->save();
    }
    public function deleteRestoredInvoices()
    {
        //DB::select('select id from invoice_items_blend where deleted_at IS NOT NULL');
        $invoice_items = InvoiceItemBlend::onlyTrashed()->pluck('id');
        InvoiceItem::whereIn('id', $invoice_items)
            ->chunkById(200, function (Collection $flights) {
                $flights->each->delete();
            }, $column = 'id');
        return $invoice_items;
    }

    // onlyTrashed()
    // private function removeOldInvoiceItemBatchesAndCreateNewOne($invoice_item, $batches, $old_quantity)
    // {
    //     $batch_ids = [];
    //     foreach ($batches as $invoice_item_batch) {
    //         $item_sub_batch = ItemStockSubBatch::find($invoice_item_batch->item_stock_sub_batch_id);
    //         // remove the old quantity reserved
    //         if ($item_sub_batch->reserved_for_supply >= $old_quantity) {
    //             $item_sub_batch->reserved_for_supply -= $old_quantity;
    //         } else {
    //             $item_sub_batch->reserved_for_supply = 0;
    //         }
    //         $item_sub_batch->save();
    //         $batch_ids[] = $invoice_item_batch->item_stock_sub_batch_id;
    //         // delete the old entry
    //         $invoice_item_batch->delete();
    //     }
    //     // create new one
    //     $this->createInvoiceItemBatches($invoice_item, $batch_ids);
    // }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Invoice\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function destroy(Invoice $invoice)
    {
        //
        $actor = $this->getUser();
        $title = "Invoice deleted";
        $description = "Invoice ($invoice->invoice_number) was deleted by $actor->name ($actor->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];

        // $invoice_items = $invoice->invoiceItems; //()->batches()->delete();
        // foreach ($invoice_items as $invoice_item) {
        //     $invoice_item_batches = $invoice_item->batches;
        //     // we want to unreserve any product for this invoice
        //     foreach ($invoice_item_batches as $invoice_item_batch) {
        //         $quantity = $invoice_item_batch->quantity;
        //         $item_stock_sub_batch = ItemStockSubBatch::find($invoice_item_batch->item_stock_sub_batch_id);
        //         $item_stock_sub_batch->reserved_for_supply -= $quantity;
        //         $item_stock_sub_batch->save();

        //         // we then delete the invoice item batch
        //         $invoice_item_batch->delete();
        //     }
        // }
        $invoice->invoiceItems()->delete();
        $invoice->delete();

        $this->logUserActivity($title, $description, $roles);
        return response()->json(null, 204);
    }
    public function deleteWaybill(Waybill $waybill)
    {
        set_time_limit(0);
        if ($waybill->status == 'pending') {
            $waybill->waybill_no .= '-deleted-' . time();
            $waybill->save();
            // delete all relationships with waybill and the waybill itself
            $actor = $this->getUser();
            $title = "Waybill deleted";
            $description = "Waybill ($waybill->waybill_no) was deleted by $actor->name ($actor->phone)";
            //log this activity

            $waybill_items = $waybill->waybillItems;
            foreach ($waybill_items as $waybill_item) {
                $invoice_item = $waybill_item->invoiceItem;
                $invoice = $invoice_item->invoice;
                $invoice->full_waybill_generated = '0';
                $invoice->save();

                $batches = $waybill_item->batches;
                foreach ($batches as $batch) {
                    if ($invoice_item->quantity_supplied >= $batch->quantity) {
                        $invoice_item->quantity_supplied -= $batch->quantity;
                        $invoice_item->save();
                    }
                    // we want to unreserve all reserved products made as a result of waybill generation
                    $item_stock_sub_batch = $batch->itemStockBatch;
                    $item_stock_sub_batch->reserved_for_supply -= $batch->to_supply;
                    $item_stock_sub_batch->save();
                }
                $waybill_item->batches()->delete();
            }
            $waybill->waybillItems()->delete();
            $waybill->trips()->delete();
            $waybill->dispatcher()->delete();
            $waybill->delete();
            $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
            $this->logUserActivity($title, $description, $roles);
        }
        return response()->json(null, 204);
    }


    public function reverseUnTreatedInvoiceItem(Request $request, InvoiceItem $invoice_item)
    {
        //since we have not treated this yet, we just remove it
        $user = $this->getUser();
        $invoice = Invoice::find($invoice_item->invoice_id);
        $item = $invoice_item->item->name;
        $package_type = $invoice_item->type;
        $total_reversed_quantity = $invoice_item->quantity - $invoice_item->quantity_supplied;
        $title = "Invoice Item Reversal";
        $description = "$total_reversed_quantity $package_type of $item was reversed from invoice $invoice->invoice_number by $user->name ($user->email)";
        $this->createInvoiceHistory($invoice, $title, $description);

        $invoice_item->quantity_reversed = $total_reversed_quantity;
        $invoice_item->save();

        $incomplete_invoice_item = $invoice->invoiceItems()->whereRaw('quantity - quantity_supplied - quantity_reversed > 0')->get();
        if ($incomplete_invoice_item->isNotEmpty()) {
            $invoice->status = 'partially supplied';
        } else {
            $invoice->status = 'delivered';
        }
        $invoice->save();
        $this->logUserActivity($title, $description);
        return response()->json([], 204);
    }
    public function reversePartiallyTreatedInvoiceItem(Request $request, InvoiceItem $invoice_item)
    {

        $invoice = Invoice::find($invoice_item->invoice_id);
        $item = $invoice_item->item->name;
        $package_type = $invoice_item->type;
        $quantity_to_reverse = $invoice_item->quantity - $invoice_item->quantity_supplied;

        $invoice_item->quantity_supplied -= $quantity_to_reverse;
        $invoice_item->quantity_reversed += $quantity_to_reverse;
        $invoice_item->save();
        //reverse all waybill generated
        $waybill_item = WaybillItem::where('invoice_item_id', $invoice_item->id)
            ->whereRaw("quantity - quantity_reversed - remitted >= $quantity_to_reverse")->first();
        if ($waybill_item) {

            $waybill_item->quantity -= $quantity_to_reverse;
            $waybill_item->quantity_reversed += $quantity_to_reverse;
            $waybill_item->save();
        }
        //since we have not treated this yet, we just remove it
        // $invoiceItemBatches = InvoiceItemBatch::with('itemStockBatch')->where('quantity', '>', 0)
        //     ->where('invoice_item_id', $invoice_item->id)
        //     ->get();
        // foreach ($invoiceItemBatches as $invoiceItemBatch) {
        //     $date = date('Y-m-d', strtotime($invoiceItemBatch->created_at));
        //     $quantity_to_reverse = $invoiceItemBatch->quantity - $invoice_item->quantity_supplied;


        //     $invoiceItemBatch->quantity = 0;
        //     $invoiceItemBatch->quantity_reversed = $quantity_to_reverse;
        //     $invoiceItemBatch->save();

        //     $invoice_item->quantity_supplied -= $quantity_to_reverse;
        //     $invoice_item->quantity_reversed += $quantity_to_reverse;
        //     $invoice_item->save();

        //     //unreserve all reservations
        //     $item_stock_batch = $invoiceItemBatch->itemStockBatch;
        //     if ($item_stock_batch->reserved_for_supply >= $quantity_to_reverse) {

        //         $item_stock_batch->reserved_for_supply -= $quantity_to_reverse;
        //         $item_stock_batch->save();
        //     }
        //     //reverse all waybill generated
        //     $waybill_item = WaybillItem::where('invoice_item_id', $invoice_item->id)
        //         ->whereRaw("quantity - remitted >= $quantity_to_reverse")->first();
        //     if ($waybill_item) {

        //         $waybill_item->quantity -= $quantity_to_reverse;
        //         $waybill_item->quantity_reversed += $quantity_to_reverse;
        //         $waybill_item->save();
        //     }

        //     $total_reversed_quantity += $quantity_to_reverse;
        // }
        $incomplete_invoice_item = $invoice->invoiceItems()->whereRaw('quantity - quantity_supplied - quantity_reversed > 0')->get();
        if ($incomplete_invoice_item->isNotEmpty()) {
            $invoice->status = 'partially supplied';
        } else {
            $invoice->status = 'delivered';
        }
        $invoice->save();

        $user = $this->getUser();

        $title = "Invoice Item Reversal";
        $description = "$quantity_to_reverse $package_type of $item was reversed from invoice $invoice->invoice_number by $user->name ($user->email)";
        //log this action to invoice history
        $this->createInvoiceHistory($invoice, $title, $description);
        $this->logUserActivity($title, $description);
        return response()->json([], 204);
    }

    public function fetchPendingInvoices(Request $request)
    {
        set_time_limit(0);
        $warehouse_id = $request->warehouse_id;
        $panel = 'month';
        $condition = [];

        $invoiceQuery = Invoice::where(['waybill_generated' => 0, 'confirmed_by' => NULL]);
        if (isset($request->warehouse_id) && $request->warehouse_id !== 'all') {
            $invoiceQuery->where('warehouse_id', $request->warehouse_id);
        }

        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $invoiceQuery->where('created_at', '>=', $date_from);
            $invoiceQuery->where('created_at', '<=', $date_to);
        }
        $is_download = 'no';
        if (isset($request->is_download)) {
            $is_download = $request->is_download;
        }

        if ($is_download == 'yes') {
            $invoices = $invoiceQuery->get();
        } else {
            $invoices = $invoiceQuery->paginate($request->limit);
        }
        return response()->json(compact('invoices'), 200);
    }
    public function archiveInvoices(Request $request)
    {
        $invoice_ids = json_decode(json_encode($request->invoice_ids));

        Invoice::whereIn('id', $invoice_ids)
            ->chunkById(200, function ($invoices) {
                $invoices->each->update(['status' => 'archived']);
            }, $column = 'id');

        InvoiceItem::whereIn('invoice_id', $invoice_ids)
            ->chunkById(200, function ($invoiceItems) {
                $invoiceItems->each->update(['supply_status' => 'Archived']);
            }, $column = 'id');

        return response()->json([], 204);
    }
    public function restoreArchivedInvoices(Request $request)
    {
        $invoice_ids = json_decode(json_encode($request->invoice_ids));

        Invoice::whereIn('id', $invoice_ids)
            ->chunkById(200, function ($invoices) {
                $invoices->each->update(['status' => 'pending']);
            }, $column = 'id');

        InvoiceItem::whereIn('invoice_id', $invoice_ids)
            ->chunkById(200, function ($invoiceItems) {
                $invoiceItems->each->update(['supply_status' => 'Pending']);
            }, $column = 'id');

        return response()->json([], 204);
    }
}
