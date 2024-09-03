<?php

namespace App\Http\Controllers\Transfers;

use App\Driver;
use App\Http\Controllers\Controller;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Transfers\TransferRequest;
use App\Models\Transfers\TransferRequestDispatchedProduct;
use App\Models\Transfers\TransferRequestHistory;
use App\Models\Transfers\TransferRequestItem;
use App\Models\Transfers\TransferRequestItemBatch;
use App\Models\Transfers\TransferRequestWaybill;
use App\Models\Transfers\TransferRequestWaybillItem;
use App\Models\Warehouse\Warehouse;
use Illuminate\Http\Request;

class GoodsTransferController extends Controller
{
    public function setTransferRequestWarehouse()
    {
        $dispatchedRequests = TransferRequestDispatchedProduct::get();

        foreach ($dispatchedRequests as $dispatchedRequest) {
            $dispatchedRequest->request_warehouse_id = $dispatchedRequest->transferWaybill->request_warehouse_id;
            $dispatchedRequest->save();
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $this->getUser();
        $warehouse_id = $request->warehouse_id;
        $transfer_requests = [];
        $incoming_transfer_requests = [];
        $sent_requests = [];
        if (isset($request->status) && $request->status != '') {
            ////// query by status //////////////
            $status = $request->status;
            $incoming_transfer_requests = TransferRequest::with([
                'supplyWarehouse',
                'requestWarehouse',
                'transferWaybillItems',
                'requestBy',
                'transferRequestItems.item',
                'histories' => function ($q) {
                    $q->orderBy('id', 'DESC');
                }
            ])->where(['supply_warehouse_id' => $warehouse_id, 'status' => $status])->orderBy('id', 'DESC')->get();

            $sent_requests = TransferRequest::with([
                'supplyWarehouse',
                'requestWarehouse',
                'transferWaybillItems',
                'requestBy',
                'transferRequestItems.item',
                'histories' => function ($q) {
                    $q->orderBy('id', 'DESC');
                }
            ])->where(['request_warehouse_id' => $warehouse_id, 'status' => $status])->orderBy('id', 'DESC')->get();
        }
        if (isset($request->from, $request->to, $request->status) && $request->from != '' && $request->from != '' && $request->status != '') {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $status = $request->status;
            $panel = $request->panel;
            $incoming_transfer_requests = TransferRequest::with([
                'supplyWarehouse',
                'requestWarehouse',
                'transferWaybillItems',
                'requestBy',
                'transferRequestItems.item',
                'histories' => function ($q) {
                    $q->orderBy('id', 'DESC');
                }
            ])->where(['supply_warehouse_id' => $warehouse_id, 'status' => $status])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->orderBy('id', 'DESC')->get();

            $sent_requests = TransferRequest::with([
                'supplyWarehouse',
                'requestWarehouse',
                'transferWaybillItems',
                'requestBy',
                'transferRequestItems.item',
                'histories' => function ($q) {
                    $q->orderBy('id', 'DESC');
                }
            ])->where(['request_warehouse_id' => $warehouse_id, 'status' => $status])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->orderBy('id', 'DESC')->get();
        }
        return response()->json(compact('incoming_transfer_requests', 'sent_requests'));
    }
    public function unDeliveredInvoices(Request $request)
    {
        //
        $user = $this->getUser();
        $warehouse_id = $request->warehouse_id;
        $waybill_no = $this->nextReceiptNo('transfer_request_waybill');
        /*$transfer_requests = TransferRequest::get();
        foreach ($transfer_requests as $transfer_request) {
            $customer = Customer::find($transfer_request->customer_id)->first();
            if (!$customer) {
                $transfer_request->delete();
            }
        }*/
        // $transfer_requests = TransferRequest::with(['transferRequestItems', 'transferRequestItems.item'])->where('warehouse_id', $warehouse_id)->where('status', '!=', 'delivered')->get();
        $transfer_requests = TransferRequest::with([
            'requestWarehouse',
            'transferRequestItems' => function ($q) {
                $q->where('supply_status', '!=', 'Complete');
            },
            'transferRequestItems.item.stocks' => function ($p) use ($warehouse_id) {
                $p->groupBy('expiry_date', 'batch_no')
                    ->whereRaw('balance - reserved_for_supply > 0')
                    ->whereRaw('confirmed_by IS NOT NULL')
                    ->where('warehouse_id', $warehouse_id)
                    ->orderBy('expiry_date')
                    ->orderBy('batch_no')
                    ->select('*', \DB::raw('(SUM(balance) - SUM(reserved_for_supply)) as balance'));
            }
        ])
            ->where('supply_warehouse_id', $warehouse_id)
            ->where('full_waybill_generated', 0)
            ->orderBy('id', 'DESC')
            ->get();
        return response()->json(compact('transfer_requests', 'waybill_no'), 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function assignTransferRequestToWarehouse(Request $request, TransferRequest $transfer_request)
    {

        $warehouse_id = $request->warehouse_id;
        $warehouse = Warehouse::find($warehouse_id);
        $transfer_request->warehouse_id = $warehouse_id;
        $transfer_request->save();
        //log this activity
        $title = "TransferRequest Assigned";
        $description = "Assigned invoice ($transfer_request->request_number) to " . $warehouse->name;
        $roles = ['warehouse manager'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($transfer_request);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $user = $this->getUser();
        $request_number = $this->nextReceiptNo('transfer_request'); //$request->request_number;
        $transfer_request_items = json_decode(json_encode($request->request_items));
        $dupicate_invoice = TransferRequest::where('request_number', $request_number)->first();
        if ($dupicate_invoice) {
            $request_number = $this->nextReceiptNo('transfer_request');
        }
        //////update next invoice number/////
        $this->incrementReceiptNo('transfer_request');
        $request_warehouse = Warehouse::find($request->request_warehouse_id);
        $supply_warehouse = Warehouse::find($request->supply_warehouse_id);
        $transfer_request = new TransferRequest();
        $transfer_request->request_warehouse_id = $request->request_warehouse_id;
        $transfer_request->supply_warehouse_id = $request->supply_warehouse_id;
        $transfer_request->request_number = $request_number; // $this->nextTransferRequestNo();
        $transfer_request->request_by = $user->id;
        $transfer_request->status = $request->status;
        $transfer_request->notes = $request->notes;
        $transfer_request->save();
        $title = "New product transfer request generated";
        $description = "New $transfer_request->status product transfer request ($transfer_request->request_number) was sent to $supply_warehouse->name from $request_warehouse->name by $user->name ($user->phone)";
        //log this action to invoice history
        $this->createTransferRequestHistory($transfer_request, $title, $description);
        //create items invoiceed for
        $this->createTransferRequestItems($transfer_request, $transfer_request_items);


        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($transfer_request);
    }

    private function createTransferRequestHistory($transfer_request, $title, $description)
    {
        $transfer_request_history = new TransferRequestHistory();
        $transfer_request_history->transfer_request_id = $transfer_request->id;
        $transfer_request_history->title = $title;
        $transfer_request_history->description = $description;
        $transfer_request_history->save();
    }

    private function createTransferRequestItems($transfer_request, $transfer_request_items)
    {
        foreach ($transfer_request_items as $item) {
            // $batches = $item->batches;
            $transfer_request_item = new TransferRequestItem();
            $transfer_request_item->supply_warehouse_id = $transfer_request->supply_warehouse_id;
            $transfer_request_item->request_warehouse_id = $transfer_request->request_warehouse_id;
            $transfer_request_item->transfer_request_id = $transfer_request->id;
            $transfer_request_item->item_id = $item->item_id;
            $transfer_request_item->quantity = $item->quantity;
            $transfer_request_item->no_of_cartons = $item->no_of_cartons;
            $transfer_request_item->type = $item->type;
            $transfer_request_item->save();
        }
    }
    private function createTransferRequestItemBatches($waybill_item, $transfer_request_item, $batches)
    {
        $item_id = $transfer_request_item->item_id;
        $warehouse_id = $waybill_item->supply_warehouse_id;
        // $quantity = $transfer_request_item->quantity;
        // $quantity = $transfer_request_item->quantity_supplied;
        // If a specific batch was set when raising the invoice, we set it here
        if (!empty($batches)) {
            foreach ($batches as $batch) {
                $batch_no = $batch->batch_no;
                $expiry_date = $batch->expiry_date;
                $supply_quantity = $batch->supply_quantity;
                if ($supply_quantity > 0) {
                    $batches_of_items_in_stock = ItemStockSubBatch::where(['warehouse_id' => $warehouse_id, 'item_id' => $item_id])
                        ->where('batch_no', $batch_no)
                        ->where('expiry_date', $expiry_date)
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
                            $real_balance = $item_sub_batch->balance - $item_sub_batch->reserved_for_supply;

                            $transfer_request_item_batch = new TransferRequestItemBatch();
                            $transfer_request_item_batch->transfer_request_id = $transfer_request_item->transfer_request_id;

                            $transfer_request_item_batch->waybill_item_id = $waybill_item->id;

                            $transfer_request_item_batch->transfer_request_item_id = $transfer_request_item->id;

                            $transfer_request_item_batch->item_stock_sub_batch_id = $item_sub_batch->id;
                            if ($supply_quantity <= $real_balance) {

                                $transfer_request_item_batch->to_supply = $supply_quantity;
                                $transfer_request_item_batch->quantity = $supply_quantity;
                                $transfer_request_item_batch->save();

                                $item_sub_batch->reserved_for_supply += $supply_quantity;
                                $item_sub_batch->save();
                                $supply_quantity = 0;
                                break;
                            } else {
                                $transfer_request_item_batch->to_supply = $real_balance;
                                $transfer_request_item_batch->quantity = $real_balance;
                                $transfer_request_item_batch->save();
                                $item_sub_batch->reserved_for_supply += $real_balance;
                                $item_sub_batch->save();
                                $supply_quantity -= $real_balance;
                            }
                        }
                    }
                }
            }
        }
    }
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\TransferRequest\TransferRequest  $transfer_request
     * @return \Illuminate\Http\Response
     */
    public function show(TransferRequest $transfer_request)
    {
        //
        $transfer_request = $transfer_request->with([
            'supplyWarehouse',
            'requestWarehouse',
            'requestBy',
            'transferWaybillItems',
            'transferRequestItems.item',
            'histories' => function ($q) {
                $q->orderBy('id', 'DESC');
            }
        ])->find($transfer_request->id);
        return response()->json(compact('transfer_request'), 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Transfers\TransferRequest  $transfer_request
     * @return \Illuminate\Http\Response
     */
    public function changeTransferRequestStaus(Request $request, TransferRequest $transfer_request)
    {
        //
        $user = $this->getUser();
        $status = $request->status;

        $transfer_request->status = $status;
        $transfer_request->save();
        $title = "TransferRequest status updated";
        $description = "TransferRequest ($transfer_request->request_number) status changed to " . strtoupper($transfer_request->status) . " by $user->name ($user->email)";
        //log this action to invoice history
        $this->createTransferRequestHistory($transfer_request, $title, $description);

        // log this activity
        $roles = ['assistant admin', 'warehouse manager'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($transfer_request);
    }
    // this fetches all generated waybills
    public function waybills(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $waybills = [];
        $drivers = Driver::with('user')->get();
        if (isset($request->status) && $request->status != '') {
            ////// query by status //////////////
            $status = $request->status;
            $waybills = TransferRequestWaybill::with(['request_warehouse', 'supply_warehouse', 'dispatcher', 'transferRequests.requestWarehouse', 'transferRequests.requestBy', 'waybillItems.item', 'waybillItems.invoice.requestBy'])->where(['supply_warehouse_id' => $warehouse_id, 'status' => $status])->orderBy('id', 'DESC')->get();

            $my_request_waybills = TransferRequestWaybill::with(['request_warehouse', 'supply_warehouse', 'dispatcher', 'transferRequests.requestWarehouse', 'transferRequests.requestBy', 'waybillItems.item', 'waybillItems.invoice.requestBy'])->where(['request_warehouse_id' => $warehouse_id, 'status' => $status])->orderBy('id', 'DESC')->get();
        }
        // if (isset($request->from, $request->to, $request->status) && $request->from != '' && $request->from != '' && $request->status != '') {
        //     $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
        //     $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
        //     $status = $request->status;
        //     $panel = $request->panel;
        //     $transfer_requests = TransferRequestWaybill::with(['dispatcher.vehicle.vehicleDrivers.driver.user', 'waybillItems.invoice.requestBy', 'waybillItems.item'])->where(['warehouse_id' => $warehouse_id, 'status' => $status])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->get();
        // }
        return response()->json(compact('waybills', 'drivers', 'my_request_waybills'));
    }
    // this fetches available drivers so that dispatching orders could be assigned to them
    // public function fetchAvailableVehicles(Request $request)
    // {
    //     //
    //     $warehouse_id = $request->warehouse_id;
    //     $vehicles = Vehicle::with('vehicleDrivers.driver.user')->where('warehouse_id', $warehouse_id)->get();
    //     $available_vehicles = [];
    //     foreach ($vehicles as $vehicle) {
    //         $dispatched_waybill = DispatchedTransferRequestWaybill::where('vehicle_id', $vehicle->id)->orderBy('id', 'Desc')->first();
    //         if ($dispatched_waybill) {
    //             if ($dispatched_waybill->waybill->status === 'delivered') {
    //                 $available_vehicles[] = $vehicle;
    //             }
    //         } else {
    //             $available_vehicles[] = $vehicle;
    //         }
    //     }
    //     return response()->json(compact('available_vehicles'), 200);
    // }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generateWaybill(Request $request)
    {
        //
        $user = $this->getUser();
        $transfer_request_ids = $request->invoice_ids;
        // $transfer_request = TransferRequest::find($request->request_id);
        $warehouse_id = $request->warehouse_id;
        $message = '';
        $transfer_request_items = json_decode(json_encode($request->invoice_items));
        $request_warehouse_id = $transfer_request_items[0]->request_warehouse_id;
        $waybill_no = $this->nextReceiptNo('transfer_request_waybill'); // $request->waybill_no; // $this->nextReceiptNo('transfer_request_waybill');
        // $duplicate_waybill = TransferRequestWaybill::where('transfer_request_waybill_no', $waybill_no)->first();
        // if ($duplicate_waybill) {
        //     $waybill_no  = $this->nextReceiptNo('transfer_request_waybill');
        //     //////update next invoice number/////
        //     $this->incrementReceiptNo('transfer_request_waybill');
        // }
        $waybill = new TransferRequestWaybill();
        $waybill->supply_warehouse_id = $warehouse_id;
        $waybill->request_warehouse_id = $request_warehouse_id;
        $waybill->transfer_request_waybill_no = $waybill_no;
        $waybill->status = $request->status;
        $waybill->save();
        $this->incrementReceiptNo('transfer_request_waybill');



        $waybill->transferRequests()->sync($transfer_request_ids);
        // create way bill items
        $waybill_item_obj = new TransferRequestWaybillItem();
        // check if there are items in stock for this waybil to be generated
        $partial_waybill_generated = [];
        foreach ($transfer_request_items as $transfer_request_item) {
            $batches = $transfer_request_item->batches;
            if (!empty($batches)) {
                $transfer_request_item_update = TransferRequestItem::find($transfer_request_item->id);
                $original_quantity = $transfer_request_item_update->quantity;
                $quantity_supplied = $transfer_request_item_update->quantity_supplied;
                $for_supply = (int) $transfer_request_item->quantity_for_supply;
                if ($for_supply > 0) {
                    if ($original_quantity > $quantity_supplied) {
                        if (($original_quantity - $quantity_supplied) >= $for_supply) {
                            $actual_supply_quantity = $for_supply;
                            $transfer_request_item_update->quantity_supplied += $actual_supply_quantity;
                            $transfer_request_item_update->save();
                            // $this->createTransferRequestItemBatches($transfer_request_item_update, $batches, $actual_supply_quantity);

                            $waybill_item = $waybill_item_obj->createTransferRequestWaybillItems($waybill->id, $warehouse_id, $transfer_request_item, $actual_supply_quantity);

                            $this->createTransferRequestItemBatches($waybill_item, $transfer_request_item_update, $batches);
                        }
                    }
                }

                if ($original_quantity > $transfer_request_item_update->quantity_supplied) {
                    $partial_waybill_generated[] = $transfer_request_item->transfer_request_id;
                }
            }
        }

        // if ($message !== '') {
        //     return response()->json(['status' => 'Insufficient Stock', 'message' => $message], 200);
        // }
        // $waybill = TransferRequestWaybill::where('request_id', $request->request_id)->first();
        // if (!$waybill) {


        // $transfer_request->status = 'waybill generated';
        // $transfer_request->save();

        $transfer_request_nos = [];
        foreach ($transfer_request_ids as $transfer_request_id) {
            $transfer_request = TransferRequest::find($transfer_request_id);
            if (!in_array($transfer_request_id, $partial_waybill_generated)) {
                $transfer_request->full_waybill_generated = '1';
                $transfer_request->save();
            }
            $title = "Transfer Request Waybill Generated";
            $description = "Transfer request waybill ($waybill->transfer_request_waybill_no) generated for invoice ($transfer_request->request_number) by $user->name ($user->email)";
            //log this action to invoice history
            $this->createTransferRequestHistory($transfer_request, $title, $description);

            //log this activity
            $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
            $this->logUserActivity($title, $description, $roles);
        }
        // }
        // $this->createDispatchedTransferRequestWaybill($waybill, $request);

        return response()->json(compact('waybill'), 200);
    }
    public function setWaybillDispatcher(Request $request)
    {
        $dispatcher = $request->user_id;
        $waybill = TransferRequestWaybill::find($request->waybill_id);
        $waybill->dispatched_by = $dispatcher;
        $waybill->save();
        return 'Dispatcher saved';
    }
    public function changeWaybillStatus(Request $request, TransferRequestWaybill $waybill)
    {
        //
        // return $request;
        $item_in_stock_obj = new ItemStockSubBatch();
        $transfer_request_item_obj = new TransferRequestItem();
        $user = $this->getUser();
        $status = $request->status;
        // update items in stock based on waybill status
        if ($status === 'in transit') {
            // list($out_of_stock_count, $message) = $item_in_stock_obj->checkStockBalanceForEachTransferWaybillItem($waybill->waybillItems);
            // if ($out_of_stock_count > 0) {
            //     return response()->json(compact('message'), 500);
            // }
            $waybill_items_ids = $waybill->waybillItems->pluck('id');
            $item_in_stock_obj->sendTransferItemInStockForDelivery($waybill_items_ids);
            // let's update the invoice items for this waybill
        }
        // update waybill status
        $waybill->status = $status;
        $waybill->save();

        // update invoice items to account for partial supplies and complete ones
        $transfer_request_item_obj->updateTransferRequestItemsForTransferRequestWaybill($waybill->waybillItems);
        $transfer_requests = $waybill->transferRequests;
        $title = "Transfer Request Waybill status updated";
        $description = "Transfer Request Waybill ($waybill->transfer_request_waybill_no) status updated to " . strtoupper($waybill->status) . " by $user->name ($user->email)";
        if ($status === 'delivered') {
            $item_in_stock_obj->confirmItemInStockAsSupplied($waybill->dispatchProducts);
            foreach ($transfer_requests as $transfer_request) {

                $transfer_request->status = $status;
                // check for partial supplies
                $incomplete_request_item = $transfer_request->transferRequestItems()->where('supply_status', '=', 'Partial')->first();
                if ($incomplete_request_item) {
                    $transfer_request->status = 'partially supplied';
                }
                $transfer_request->save();
                //log this action to invoice history
                $this->createTransferRequestHistory($transfer_request, $title, $description);
            }

            // we move received items to stock for the receiving warehouse///////
            $dispatched_products = TransferRequestDispatchedProduct::where(['transfer_request_waybill_id' => $waybill->id, 'status' => 'delivered', 'is_stocked' => 0])->get();
            foreach ($dispatched_products as $dispatched_product) {
                $dispatched_stock = $dispatched_product->itemStock;

                $item_stock_sub_batch = new ItemStockSubBatch();
                $item_stock_sub_batch->stocked_by = $dispatched_stock->stocked_by;
                $item_stock_sub_batch->confirmed_by = $user->id;
                $item_stock_sub_batch->warehouse_id = $waybill->request_warehouse_id;
                $item_stock_sub_batch->item_id = $dispatched_stock->item_id;
                // we suffix 'Trans-' so that we can differentiate between transfered stock and normal ones
                $item_stock_sub_batch->batch_no = $dispatched_stock->batch_no; // '(Trans)' . $dispatched_stock->batch_no;
                $item_stock_sub_batch->sub_batch_no = $dispatched_stock->batch_no; //'(Trans)' . $dispatched_stock->batch_no;
                $item_stock_sub_batch->quantity = $dispatched_product->quantity_supplied;
                $item_stock_sub_batch->reserved_for_supply = 0;
                $item_stock_sub_batch->in_transit = 0; // initial values set to zero
                $item_stock_sub_batch->supplied = 0;
                $item_stock_sub_batch->balance = $dispatched_product->quantity_supplied;
                $item_stock_sub_batch->goods_received_note = $dispatched_stock->goods_received_note; // '(Trans)' . $dispatched_stock->goods_received_note;

                $item_stock_sub_batch->expiry_date = $dispatched_stock->expiry_date;
                $item_stock_sub_batch->is_warehouse_transfered = 1;
                $item_stock_sub_batch->save();

                //mark as stocked
                $dispatched_product->is_stocked = 1;
                $dispatched_product->stocked_by = $user->id;
                $dispatched_product->save();

                // log this event
                $sub_title = "Transfered Product added to stock";
                $sub_description = $item_stock_sub_batch->quantity . " " . $item_stock_sub_batch->item->name . " was added to stock at " . $item_stock_sub_batch->warehouse->name . " by " . $user->name;
                $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor', 'stock officer'];
                $this->logUserActivity($sub_title, $sub_description, $roles);
            }
        }


        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['waybill_status' => $status], 200);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\TransferRequest\TransferRequest  $transfer_request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TransferRequest $transfer_request)
    {
        //
        $user = $this->getUser();
        $transfer_request_items = json_decode(json_encode($request->transfer_request_items));
        $transfer_request = TransferRequest::find($request->id);
        $old_request_number = $transfer_request->request_number;
        $transfer_request->request_number = $request->request_number;
        $transfer_request->supply_warehouse_id = $request->supply_warehouse_id;
        $transfer_request->notes = $request->notes;
        $transfer_request->save();
        $extra_info = "";
        if ($old_request_number !== $transfer_request->request_number) {
            $extra_info = $old_request_number . ' was changed to ' . $transfer_request->request_number;
        }
        $this->updateTransferRequestItems($transfer_request, $transfer_request_items);
        $title = "Transfer request modified";
        $description = "Transfer request ($old_request_number) was updated by $user->name ($user->email) " . $extra_info;
        //log this action to invoice history
        $this->createTransferRequestHistory($transfer_request, $title, $description);
        //create items invoiceed for

        //////update next invoice number/////
        // $this->incrementTransferRequestNo();

        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
        return $this->show($transfer_request);
    }
    private function updateTransferRequestItems($transfer_request, $transfer_request_items)
    {
        foreach ($transfer_request_items as $item) {
            // $batches = $item->batches;
            $transfer_request_item = TransferRequestItem::find($item->id);
            $transfer_request_item->supply_warehouse_id = $transfer_request->supply_warehouse_id;
            $transfer_request_item->item_id = $item->item_id;
            $transfer_request_item->quantity = $item->quantity;
            $transfer_request_item->no_of_cartons = $item->no_of_cartons;
            $transfer_request_item->type = $item->type;
            $transfer_request_item->save();
        }
    }
    // private function removeOldTransferRequestItemBatchesAndCreateNewOne($transfer_request_item, $batches, $old_quantity)
    // {
    //     $batch_ids = [];
    //     foreach ($batches as $transfer_request_item_batch) {
    //         $item_sub_batch = ItemStockSubBatch::find($transfer_request_item_batch->item_stock_sub_batch_id);
    //         // remove the old quantity reserved
    //         if ($item_sub_batch->reserved_for_supply >= $old_quantity) {
    //             $item_sub_batch->reserved_for_supply -= $old_quantity;
    //         } else {
    //             $item_sub_batch->reserved_for_supply = 0;
    //         }
    //         $item_sub_batch->save();
    //         $batch_ids[] = $transfer_request_item_batch->item_stock_sub_batch_id;
    //         // delete the old entry
    //         $transfer_request_item_batch->delete();
    //     }
    //     // create new one
    //     $this->createTransferRequestItemBatches($transfer_request_item, $batch_ids);
    // }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\TransferRequest\TransferRequest  $transfer_request
     * @return \Illuminate\Http\Response
     */
    public function destroy(TransferRequest $transfer_request)
    {
        //
        $actor = $this->getUser();
        $title = "TransferRequest deleted";
        $description = "TransferRequest ($transfer_request->request_number) was deleted by $actor->name ($actor->phone)";
        //log this activity


        // $transfer_request_items = $transfer_request->transferRequestItems; //()->batches()->delete();
        // foreach ($transfer_request_items as $transfer_request_item) {
        //     $transfer_request_item_batches = $transfer_request_item->batches;
        //     // we want to unreserve any product for this invoice
        //     foreach ($transfer_request_item_batches as $transfer_request_item_batch) {
        //         $quantity = $transfer_request_item_batch->quantity;
        //         $item_stock_sub_batch = ItemStockSubBatch::find($transfer_request_item_batch->item_stock_sub_batch_id);
        //         $item_stock_sub_batch->reserved_for_supply -= $quantity;
        //         $item_stock_sub_batch->save();

        //         // we then delete the invoice item batch
        //         $transfer_request_item_batch->delete();
        //     }
        // }
        $transfer_request->transferRequestItems()->delete();
        $transfer_request->delete();
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(null, 204);
    }
}
