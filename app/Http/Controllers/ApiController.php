<?php

namespace App\Http\Controllers;

use App\Customer;
use App\Models\Invoice\DispatchedProduct;
use App\Models\Invoice\InvoiceItem;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Stock\ReturnedProduct;
use App\Models\Stock\StockCount;
use App\Models\Transfers\TransferRequestDispatchedProduct;
use App\Models\Warehouse\Warehouse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ApiController extends Controller
{
    //
    public function customers(Request $request)
    {
        $searchParams = $request->all();
        $userQuery = Customer::query();
        $userQuery = $userQuery->join('users', 'customers.user_id', 'users.id')
            // ->orderBy('users.name')
            ->select('customers.id as id', 'name', 'email', 'phone', 'address', 'type');
        // $customers = $userQuery->get();
        $customers = $userQuery->lazy();
        // foreach ($userQuery->lazy() as $flight) {
        //     $customers[] = $flight;
        // }
        return response()->json(compact('customers'), 200);
    }
    public function unsuppliedInvoices(Request $request)
    {
        set_time_limit(0);
        $date_from = Carbon::now()->startOfYear();
        $date_to = Carbon::now()->endOfYear();
        $invoiceItemQuery = InvoiceItem::query();

        $invoiceItemQuery = $invoiceItemQuery->join('invoices', 'invoice_items.invoice_id', 'invoices.id')
            ->join('customers', 'invoices.customer_id', 'customers.id')
            ->join('users', 'customers.user_id', 'users.id')
            ->join('items', 'invoice_items.item_id', 'items.id')
            ->join('warehouses', 'invoice_items.warehouse_id', 'warehouses.id')

            ->whereRaw('invoice_items.quantity - invoice_items.quantity_supplied - invoice_items.quantity_reversed > 0')
            ->selectRaw(
                'warehouses.name as warehouse,
                users.name as customer,
                invoice_number,
                items.name as product,
                invoice_items.type as uom,
                invoice_items.rate,
                invoice_items.amount,
                invoice_items.quantity,
                invoice_items.quantity_supplied,
                invoice_items.quantity_reversed,
                invoice_items.quantity - invoice_items.quantity_supplied - invoice_items.quantity_reversed as unsupplied,
                invoice_items.created_at'
            );

        if (isset($request->warehouse_id) && $request->warehouse_id !== 'all') {
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.warehouse_id', $request->warehouse_id);
        }

        if (isset($request->from, $request->to) && $request->from !== '' && $request->to !== '') {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.created_at', '>=', $date_from);
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.created_at', '<=', $date_to);
        }

        $invoice_items = $invoiceItemQuery->lazy();
        $start_date = date('d-m-Y H:i:s', strtotime($date_from));
        $end_date = date('d-m-Y H:i:s', strtotime($date_to));
        return response()->json(compact('start_date', 'end_date', 'invoice_items'), 200);
    }
    public function allInvoicesRaised(Request $request)
    {

        set_time_limit(0);
        $date_from = Carbon::now()->startOfYear();
        $date_to = Carbon::now()->endOfYear();
        $invoiceItemQuery = InvoiceItem::query();

        $invoiceItemQuery = $invoiceItemQuery->join('invoices', 'invoice_items.invoice_id', 'invoices.id')
            ->join('customers', 'invoices.customer_id', 'customers.id')
            ->join('users', 'customers.user_id', 'users.id')
            ->join('items', 'invoice_items.item_id', 'items.id')
            ->join('warehouses', 'invoice_items.warehouse_id', 'warehouses.id')
            ->join('waybill_items', 'waybill_items.invoice_item_id', 'invoice_items.id')
            ->selectRaw(
                'warehouses.name as warehouse,
                users.name as customer,
                invoice_number,
                items.name as product,
                invoice_items.type as uom,
                invoice_items.rate,
                invoice_items.amount,
                invoice_items.quantity,
                invoice_items.quantity_supplied,
                invoice_items.quantity_reversed,
                invoice_items.created_at,
                waybill_items.created_at as date_waybilled,
                DATEDIFF(waybill_items.created_at, invoice_items.created_at) as invoice_delay_before_waybilled_in_days'
            );

        if (isset($request->warehouse_id) && $request->warehouse_id !== 'all') {
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.warehouse_id', $request->warehouse_id);
        }

        if (isset($request->from, $request->to) && $request->from !== '' && $request->to !== '') {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.created_at', '>=', $date_from);
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.created_at', '<=', $date_to);
        }

        $invoice_items = $invoiceItemQuery->lazy();
        $start_date = date('d-m-Y H:i:s', strtotime($date_from));
        $end_date = date('d-m-Y H:i:s', strtotime($date_to));
        return response()->json(compact('start_date', 'end_date', 'invoice_items'), 200);
    }

    // private function getProductTransaction($item_id, $date_from, $date_to, $warehouse_id)
    // {

    //     $total_stock_till_date = ItemStockSubBatch::groupBy('item_id')
    //         ->where('warehouse_id', $warehouse_id)
    //         ->where('item_id', $item_id)
    //         ->where('created_at', '<', $date_from)
    //         ->where(function ($q) {
    //             $q->where('confirmed_by', '!=', null);
    //             $q->orWhere(function ($p) {
    //                 $p->where('confirmed_by', null);
    //                 $p->where('supplied', '>', 0);
    //             });
    //         })
    //         ->select(\DB::raw('SUM(quantity) as total_quantity'))
    //         ->first();
    //     $previous_outbound = DispatchedProduct::join('item_stock_sub_batches', 'dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')->groupBy('item_stock_sub_batches.item_id')
    //         ->where('dispatched_products.warehouse_id', $warehouse_id)
    //         ->where('item_stock_sub_batches.item_id', $item_id)
    //         ->where('dispatched_products.created_at', '<', $date_from)
    //         // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
    //         ->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))
    //         ->orderby('dispatched_products.created_at')->first();

    //     $previous_transfer_outbound = TransferRequestDispatchedProduct::join('item_stock_sub_batches', 'transfer_request_dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')->groupBy('item_stock_sub_batches.item_id')
    //         ->where('supply_warehouse_id', $warehouse_id)
    //         ->where('item_stock_sub_batches.item_id', $item_id)
    //         ->where('transfer_request_dispatched_products.created_at', '<', $date_from)
    //         // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
    //         ->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))
    //         ->orderby('transfer_request_dispatched_products.created_at')
    //         ->first();
    //     // expired warehouse has id of 8
    //     $previous_expired_product = ItemStockSubBatch::groupBy(['item_id'])
    //         ->where(['item_id' => $item_id, 'warehouse_id' => 8, 'expired_from' => $warehouse_id])
    //         ->where('created_at', '<', $date_from)
    //         ->select(\DB::raw('SUM(quantity) as total_quantity'))
    //         ->first();

    //     $inbounds = ItemStockSubBatch::groupBy(['item_id'])->where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])
    //         ->where('created_at', '>=', $date_from)
    //         ->where('created_at', '<=', $date_to)
    //         ->where(function ($q) {
    //             $q->where('confirmed_by', '!=', null);
    //             $q->orWhere(function ($p) {
    //                 $p->where('confirmed_by', null);
    //                 $p->where('supplied', '>', 0);
    //             });
    //         })
    //         ->select(\DB::raw('SUM(quantity) as total_inbound'))
    //         ->orderby('created_at')
    //         ->lazy();
    //     $outbounds = DispatchedProduct::join('item_stock_sub_batches', 'dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')
    //         ->groupBy(['item_stock_sub_batches.item_id'])
    //         ->where('dispatched_products.warehouse_id', $warehouse_id)
    //         ->where('item_stock_sub_batches.item_id', $item_id)
    //         ->where('dispatched_products.created_at', '>=', $date_from)
    //         ->where('dispatched_products.created_at', '<=', $date_to)
    //         // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
    //         ->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->orderby('dispatched_products.created_at')->lazy();
    //     $outbounds2 = TransferRequestDispatchedProduct::join('item_stock_sub_batches', 'transfer_request_dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')
    //         ->groupBy(['item_stock_sub_batches.item_id'])
    //         ->where('supply_warehouse_id', $warehouse_id)
    //         ->where('item_stock_sub_batches.item_id', $item_id)
    //         ->where('transfer_request_dispatched_products.created_at', '>=', $date_from)
    //         ->where('transfer_request_dispatched_products.created_at', '<=', $date_to)
    //         // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
    //         ->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->orderby('transfer_request_dispatched_products.created_at')->lazy();

    //     // $expired_product = ExpiredProduct::groupBy(['batch_no'])->where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])->where('expiry_date', '>=', $date_from)->where('expiry_date', '<=', $date_to)->select('*', \DB::raw('SUM(quantity) as total_quantity'))->first();

    //     // expired warehouse has id of 8
    //     $expired_products = ItemStockSubBatch::groupBy(['item_id'])->where(['item_id' => $item_id, 'expired_from' => $warehouse_id])->where('created_at', '>=', $date_from)
    //         ->where('created_at', '<=', $date_to)
    //         ->select(\DB::raw('SUM(quantity) as total_expired'))
    //         ->lazy();

    //     return array($total_stock_till_date, $previous_outbound, $previous_transfer_outbound, $previous_expired_product, $inbounds, $outbounds, $outbounds2, $expired_products);
    // }
    // public function instantBalances(Request $request)
    // {
    //     set_time_limit(0);
    //     $date_from = Carbon::now()->startOfYear();
    //     $date_to = Carbon::now()->endOfYear();
    //     $panel = 'month';
    //     if (isset($request->from, $request->to) && $request->from !== '' && $request->to !== '') {
    //         $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
    //         $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
    //         $panel = $request->panel;
    //     }
    //     $item_ids = ItemStockSubBatch::groupBy(['item_id'])->pluck('item_id');
    //     $items = Item::whereIn('id', $item_ids)->orderBy('name')->select('id', 'name', 'package_type')->get();
    //     if (isset($request->warehouse_id) && $request->warehouse_id != 'all' && $request->warehouse_id != '') {

    //         $warehouse_id = $request->warehouse_id;
    //         // we do it this way to get a collection even though it is single
    //         $warehouses = Warehouse::where('id', $warehouse_id)->get();
    //     } else {
    //         $warehouses = Warehouse::get();
    //     }
    //     $instant_balances = [];
    //     foreach ($items as $item) {
    //         $item_id = $item->id;
    //         $products = [];
    //         foreach ($warehouses as $warehouse) {
    //             $warehouse_id = $warehouse->id;
    //             list($total_stock_till_date, $previous_outbound, $previous_transfer_outbound, $previous_expired_product, $inbounds, $outbounds, $outbounds2, $expired_products) = $this->getProductTransaction($item_id, $date_from, $date_to, $warehouse_id);

    //             $quantity_in_stock = ($total_stock_till_date) ? $total_stock_till_date->total_quantity : 0;
    //             $quantity_supplied = ($previous_outbound) ? $previous_outbound->total_quantity_supplied : 0;
    //             $transfer_item_quantity_supplied = ($previous_transfer_outbound) ? $previous_transfer_outbound->total_quantity_supplied : 0;

    //             $expired_quantity = ($previous_expired_product) ? $previous_expired_product->total_quantity : 0;

    //             $brought_forward = (int)$quantity_in_stock - (int) $quantity_supplied - (int) $transfer_item_quantity_supplied - (int) $expired_quantity;

    //             $quantity_in = 0;
    //             $quantity_out = 0; //$quantity_supplied + $transfer_item_quantity_supplied;
    //             $total_on_transit = 0;
    //             $total_delivered = 0;
    //             $total_reserved = 0;
    //             $quantity_expired = 0;
    //             if ($inbounds->isNotEmpty()) {
    //                 foreach ($inbounds as $inbound) {
    //                     $quantity_in += $inbound->total_inbound;
    //                 }
    //             }
    //             if ($outbounds->isNotEmpty()) {
    //                 foreach ($outbounds as $outbound) {
    //                     $quantity_out += $outbound->total_quantity_supplied;
    //                     if ($outbound->status == 'on transit') {
    //                         $total_on_transit += $outbound->total_quantity_supplied;
    //                     } else {
    //                         $total_delivered += $outbound->total_quantity_supplied;
    //                     }
    //                 }
    //             }
    //             if ($outbounds2->isNotEmpty()) {
    //                 foreach ($outbounds2 as $outbound2) {
    //                     $quantity_out += $outbound2->total_quantity_supplied;
    //                     if ($outbound2->status == 'on transit') {
    //                         $total_on_transit += $outbound2->total_quantity_supplied;
    //                     } else {
    //                         $total_delivered += $outbound2->total_quantity_supplied;
    //                     }
    //                 }
    //             }
    //             if ($expired_products->isNotEmpty()) {
    //                 foreach ($expired_products as $expired_product) {
    //                     $quantity_expired += $expired_product->total_expired;
    //                 }
    //             }
    //             $products[] = [
    //                 'product_id' => $item->id,
    //                 'product_name' => $item->name,
    //                 'uom' => $item->package_type,
    //                 'warehouse' => $warehouse->name,
    //                 'brought_forward' => $brought_forward,
    //                 'quantity_in' => $quantity_in,
    //                 'quantity_out' => $quantity_out,
    //                 'quantity_expired' => $quantity_expired,
    //                 'balance' => $brought_forward + $quantity_in - $quantity_out - $quantity_expired,
    //             ];
    //         }
    //         $instant_balances = array_merge($instant_balances, $products);
    //     }
    //     $start_date = date('d-m-Y H:i:s', strtotime($date_from));
    //     $end_date = date('d-m-Y H:i:s', strtotime($date_to));
    //     return response()->json(compact('start_date', 'end_date', 'instant_balances'), 200);
    // }
    private function getProductTransactionForInstantBalance($item_id, $date_from, $date_to, $warehouse_id)
    {
        $total_stock_till_date = ItemStockSubBatch::query();
        $previous_outbound = DispatchedProduct::query();
        $previous_transfer_outbound = TransferRequestDispatchedProduct::query();
        $previous_expired_product = ItemStockSubBatch::query();
        $inbounds = ItemStockSubBatch::query();
        $outbounds = DispatchedProduct::query();
        $outbounds2 = TransferRequestDispatchedProduct::query();
        $expired_products = ItemStockSubBatch::query();

        $total_stock_till_date->groupBy('item_id')
            ->where('item_id', $item_id)
            ->where('created_at', '<', $date_from)
            ->where(function ($q) {
                $q->where('confirmed_by', '!=', null);
                $q->orWhere(function ($p) {
                    $p->where('confirmed_by', null);
                    $p->where('supplied', '>', 0);
                });
            });
        //if ($warehouse_id != 'all') {
        $total_stock_till_date->where('warehouse_id', $warehouse_id);
        //}
        $total_stock_till_date = $total_stock_till_date->select(\DB::raw('SUM(quantity) as total_quantity'))
            ->first();


        $previous_outbound->join('item_stock_sub_batches', 'dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')->groupBy('item_stock_sub_batches.item_id')
            ->where('item_stock_sub_batches.item_id', $item_id)
            ->where('dispatched_products.created_at', '<', $date_from);
        // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
        //if ($warehouse_id != 'all') {
        $previous_outbound->where('dispatched_products.warehouse_id', $warehouse_id);
        //}
        $previous_outbound = $previous_outbound->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))
            ->orderby('dispatched_products.created_at')->first();



        $previous_transfer_outbound->join('item_stock_sub_batches', 'transfer_request_dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')->groupBy('item_stock_sub_batches.item_id')

            ->where('item_stock_sub_batches.item_id', $item_id)
            ->where('transfer_request_dispatched_products.created_at', '<', $date_from);
        // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
        // if ($warehouse_id != 'all') {
        $previous_transfer_outbound->where('supply_warehouse_id', $warehouse_id);
        // }
        $previous_transfer_outbound = $previous_transfer_outbound->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))
            ->orderby('transfer_request_dispatched_products.created_at')
            ->first();


        // expired warehouse has id of 8
        $previous_expired_product->groupBy(['item_id'])
            ->where(['item_id' => $item_id, 'warehouse_id' => 8])
            ->where('created_at', '<', $date_from);
        // if ($warehouse_id != 'all') {
        $previous_expired_product->where('expired_from', $warehouse_id);
        // }
        $previous_expired_product = $previous_expired_product->select(\DB::raw('SUM(quantity) as total_quantity'))->first();



        $inbounds->where(['item_id' => $item_id])
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            ->where(function ($q) {
                $q->where('confirmed_by', '!=', null);
                $q->orWhere(function ($p) {
                    $p->where('confirmed_by', null);
                    $p->where('supplied', '>', 0);
                });
            });
        // if ($warehouse_id != 'all') {
        $inbounds->where('warehouse_id', $warehouse_id);
        // }
        $inbounds = $inbounds->select(\DB::raw('SUM(quantity) as total_inbound'))
            ->orderby('created_at')
            ->get();



        $outbounds->join('item_stock_sub_batches', 'dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')
            ->groupBy(['waybill_item_id'])

            ->where('item_stock_sub_batches.item_id', $item_id)
            ->where('dispatched_products.created_at', '>=', $date_from)
            ->where('dispatched_products.created_at', '<=', $date_to);
        // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
        // if ($warehouse_id != 'all') {
        $outbounds->where('dispatched_products.warehouse_id', $warehouse_id);
        // }
        $outbounds = $outbounds->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->get();


        $outbounds2->join('item_stock_sub_batches', 'transfer_request_dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')
            ->groupBy(['item_stock_sub_batches.item_id'])
            ->where('item_stock_sub_batches.item_id', $item_id)
            ->where('transfer_request_dispatched_products.created_at', '>=', $date_from)
            ->where('transfer_request_dispatched_products.created_at', '<=', $date_to);
        // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
        // if ($warehouse_id != 'all') {
        $outbounds2->where('supply_warehouse_id', $warehouse_id);
        // }
        $outbounds2 = $outbounds2->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->orderby('transfer_request_dispatched_products.created_at')->get();

        // $expired_product = ExpiredProduct::groupBy(['batch_no'])->where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])->where('expiry_date', '>=', $date_from)->where('expiry_date', '<=', $date_to)->select('*', \DB::raw('SUM(quantity) as total_quantity'))->first();

        // expired warehouse has id of 8
        $expired_products->groupBy(['item_id'])->where(['item_id' => $item_id])
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            ->where('expired_from', '!=', NULL);
        // if ($warehouse_id != 'all') {
        $expired_products->where('expired_from', $warehouse_id);
        // }
        $expired_products = $expired_products->select(\DB::raw('SUM(quantity) as total_expired'))
            ->get();

        return array($total_stock_till_date, $previous_outbound, $previous_transfer_outbound, $previous_expired_product, $inbounds, $outbounds, $outbounds2, $expired_products);
    }
    public function instantBalances(Request $request)
    {
        set_time_limit(0);
        $date_from = Carbon::now()->startOfYear();
        $date_to = Carbon::now()->endOfYear();
        $panel = 'month';
        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $panel = $request->panel;
        }
        $is_download = $request->is_download;

        $item_id = $request->item_id;
        if ($item_id != '' && $item_id != 'all') {
            $items = Item::where('id', $item_id)->paginate(1);
        } else {

            $items = Item::join('categories', 'categories.id', '=', 'items.category_id')->where('categories.name', '!=', 'Promo')->orderBy('items.name')->select('items.id', 'items.name', 'package_type')->lazy();
        }



        $warehouse_id = $request->warehouse_id;
        if ($warehouse_id === 'all') {
            $warehouses = Warehouse::get();
        } else {
            // we do it this way to get a collection even though it is single
            $warehouses = Warehouse::where('id', $warehouse_id)->get();
        }
        $instant_balances = [];
        foreach ($items as $item) {
            $item_id = $item->id;
            $products = [];
            foreach ($warehouses as $warehouse) {
                $warehouse_id = $warehouse->id;
                list($total_stock_till_date, $previous_outbound, $previous_transfer_outbound, $previous_expired_product, $inbounds, $outbounds, $outbounds2, $expired_products) = $this->getProductTransactionForInstantBalance($item_id, $date_from, $date_to, $warehouse_id);

                // $waybill_items = WaybillItem::where(['warehouse_id' => $warehouse_id, 'item_id' => $item_id])->where('created_at', '>=', $date_from)
                //     ->where('created_at', '<=', $date_to)->get();

                // $transfer_waybill_items = TransferRequestWaybillItem::where(['supply_warehouse_id' => $warehouse_id, 'item_id' => $item_id])->where('created_at', '>=', $date_from)
                //     ->where('created_at', '<=', $date_to)->get();

                $quantity_in_stock = ($total_stock_till_date) ? $total_stock_till_date->total_quantity : 0;
                $quantity_supplied = ($previous_outbound) ? $previous_outbound->total_quantity_supplied : 0;
                $transfer_item_quantity_supplied = ($previous_transfer_outbound) ? $previous_transfer_outbound->total_quantity_supplied : 0;

                $expired_quantity = ($previous_expired_product) ? $previous_expired_product->total_quantity : 0;

                $brought_forward = (int)$quantity_in_stock - (int) $quantity_supplied - (int) $transfer_item_quantity_supplied - (int) $expired_quantity;

                $quantity_in = 0;
                $quantity_out = 0; //$quantity_supplied + $transfer_item_quantity_supplied;
                $total_on_transit = 0;
                $total_delivered = 0;
                $total_reserved = 0;
                $quantity_expired = 0;
                if ($inbounds->isNotEmpty()) {
                    foreach ($inbounds as $inbound) {
                        // $quantity_in += $inbound->quantity;
                        $quantity_in += $inbound->total_inbound;
                    }
                }
                if ($outbounds->isNotEmpty()) {
                    foreach ($outbounds as $outbound) {
                        $quantity_out += $outbound->total_quantity_supplied;
                        if ($outbound->status == 'on transit') {
                            $total_on_transit += $outbound->total_quantity_supplied;
                        } else {
                            $total_delivered += $outbound->total_quantity_supplied;
                        }
                    }
                }
                if ($outbounds2->isNotEmpty()) {
                    foreach ($outbounds2 as $outbound2) {
                        $quantity_out += $outbound2->total_quantity_supplied;
                        if ($outbound2->status == 'on transit') {
                            $total_on_transit += $outbound2->total_quantity_supplied;
                        } else {
                            $total_delivered += $outbound2->total_quantity_supplied;
                        }
                    }
                }
                if ($expired_products->isNotEmpty()) {
                    foreach ($expired_products as $expired_product) {
                        $quantity_expired += $expired_product->total_expired;
                    }
                }
                $products[] = [
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'uom' => $item->package_type,
                    'warehouse' => $warehouse->name,
                    'brought_forward' => $brought_forward,
                    'quantity_in' => $quantity_in,
                    'quantity_out' => $quantity_out,
                    'quantity_expired' => $quantity_expired,
                    'balance' => $brought_forward + $quantity_in - $quantity_out - $quantity_expired,
                ];
            }
            $instant_balances = array_merge($instant_balances, $products);
        }
        $start_date = date('d-m-Y H:i:s', strtotime($date_from));
        $end_date = date('d-m-Y H:i:s', strtotime($date_to));
        return response()->json(compact('start_date', 'end_date', 'instant_balances'), 200);
    }
    public function productInStock(Request $request)
    {
        $date = date('Y-m-d', strtotime('now'));
        $items_in_stock_query = ItemStockSubBatch::query();

        $items_in_stock_query->join('warehouses', 'warehouses.id', '=', 'item_stock_sub_batches.warehouse_id')
            ->join('items', 'items.id', '=', 'item_stock_sub_batches.item_id');
        if (isset($request->warehouse_id) && $request->warehouse_id != 'all' && $request->warehouse_id != '') {

            $warehouse_id = $request->warehouse_id;
            $items_in_stock_query->where('warehouse_id', $warehouse_id);
        }
        if (isset($request->item_id) && $request->item_id != 'all' && $request->item_id != '') {

            $item_id = $request->item_id;
            $items_in_stock_query->where('item_id', $item_id);
        }
        $items_in_stock_query->where('balance', '>', '0')
            ->where('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->select('warehouses.name as warehouse', 'items.name as product', 'batch_no', 'goods_received_note as grn', 'quantity as quantity_in', \DB::raw('(in_transit + supplied) as quantity_out'), 'balance', 'expiry_date', 'item_stock_sub_batches.created_at');
        // $items_in_stock = $items_in_stock_query->get();
        $items_in_stock = $items_in_stock_query->lazy();
        return response()->json(compact('items_in_stock'));
    }
    public function expiredProducts(Request $request)
    {
        $date = date('Y-m-d', strtotime('now'));
        $items_in_stock_query = ItemStockSubBatch::query();

        $items_in_stock_query->join('warehouses', 'warehouses.id', '=', 'item_stock_sub_batches.warehouse_id')
            ->join('items', 'items.id', '=', 'item_stock_sub_batches.item_id');
        if (isset($request->warehouse_id) && $request->warehouse_id != 'all' && $request->warehouse_id != 'all') {

            $warehouse_id = $request->warehouse_id;
            $items_in_stock_query->where('warehouse_id', $warehouse_id);
        }
        if (isset($request->item_id) && $request->item_id != 'all' && $request->item_id != '') {

            $item_id = $request->item_id;
            $items_in_stock_query->where('item_id', $item_id);
        }
        $items_in_stock_query->where('balance', '>', '0')
            ->where('expiry_date', '<', $date)
            ->orderBy('expiry_date')
            ->select('warehouses.name as warehouse', 'items.name as product', 'batch_no', 'goods_received_note as grn', 'quantity as quantity_stocked', 'balance as expired_quantity', 'expiry_date', 'item_stock_sub_batches.created_at');
        $expired_products = $items_in_stock_query->lazy();
        return response()->json(compact('expired_products'));
    }
    public function returnedProducts(Request $request)
    {
        $returns_query = ReturnedProduct::query();

        $returns_query->join('warehouses', 'warehouses.id', '=', 'returned_products.warehouse_id')
            ->join('items', 'items.id', '=', 'returned_products.item_id');
        if (isset($request->item_id) && $request->item_id != 'all' && $request->item_id != '') {

            $item_id = $request->item_id;
            $returns_query->where('item_id', $item_id);
        }
        $returns_query->select('warehouses.name as warehouse', 'items.name as product', 'returned_products.*');
        $returned_products = $returns_query->lazy();
        return response()->json(compact('returned_products'));
    }
    public function stockCount(Request $request)
    {
        $date_from = Carbon::now()->startOfYear();
        $date_to = Carbon::now()->endOfYear();
        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from));
            $date_to = date('Y-m-d', strtotime($request->to));
        }
        $count_query = StockCount::query();

        $count_query->join('warehouses', 'warehouses.id', '=', 'stock_counts.warehouse_id')
            ->join('items', 'items.id', '=', 'stock_counts.item_id');
        if (isset($request->warehouse_id) && $request->warehouse_id != 'all' && $request->warehouse_id != 'all') {
            $warehouse_id = $request->warehouse_id;
            $count_query->where('warehouse_id', $warehouse_id);
        }
        if (isset($request->item_id) && $request->item_id != 'all' && $request->item_id != '') {

            $item_id = $request->item_id;
            $count_query->where('item_id', $item_id);
        }
        $count_query->where('date', '>=', $date_from)
            ->where('date', '<=', $date_to);
        $count_query->select('warehouses.name as warehouse', 'items.name as product', 'batch_no', 'expiry_date', 'count_quantity', 'date as count_date', 'stock_counts.created_at');
        $stoct_counts = $count_query->lazy();
        return response()->json(compact('stoct_counts'));
    }
}
