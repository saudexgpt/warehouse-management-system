<?php

namespace App\Http\Controllers;

use App\Models\Invoice\Invoice;
use App\Models\Invoice\Waybill;
use App\Models\Invoice\DeliveryTripExpense;
use App\Models\Invoice\DispatchedProduct;
use App\Models\Invoice\InvoiceItem;
use App\Models\Invoice\InvoiceItemBatch;
use App\Models\Invoice\WaybillItem;
use App\Models\Logistics\Vehicle;
use App\Models\Logistics\VehicleCondition;
use App\Models\Logistics\VehicleExpense;
use App\Models\Stock\Category;
use App\Models\Stock\ExpiredProduct;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Transfers\TransferRequestDispatchedProduct;
use App\Models\Transfers\TransferRequestItem;
use App\Models\Transfers\TransferRequestItemBatch;
use App\Models\Transfers\TransferRequestWaybillItem;
use App\Models\Warehouse\Warehouse;
use App\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ReportsController extends Controller
{

    // public function productsInStockGraph(Request $request)
    // {
    //     $warehouse_id = $request->warehouse_id;
    //     $date_from = Carbon::now()->startOfYear();
    //     $date_to = Carbon::now()->endOfYear();
    //     $panel = 'year';
    //     if (isset($request->from, $request->to, $request->panel)) {
    //         $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
    //         $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
    //         $panel = $request->panel;

    //     }
    //     $warehouse = Warehouse::find($warehouse_id);
    //     $categories = [];
    //     $quantity = [];
    //     $in_transit = [];
    //     $balance = [];
    //     $supplied = [];

    //     $items_in_stock = ItemStock::with('item')->groupBy('item_id')->where(['warehouse_id' => $warehouse_id])->select('*',\DB::raw('SUM(quantity) as quantity'), \DB::raw('SUM(in_transit) as in_transit'), \DB::raw('SUM(balance) as total_balance'), \DB::raw('SUM(supplied) as total_supplied'))->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->get();
    //     foreach ($items_in_stock as $item_in_stock) {

    //         $item_name = $item_in_stock->item->name;
    //         $categories[] = $item_name;

    //         $total_stocked = 0;
    //         $item_in_transit = 0;
    //         $total_balance = 0;
    //         $total_supplied = 0;
    //         if ($item_in_stock) {
    //             $total_stocked = $item_in_stock->quantity;
    //             $item_in_transit = $item_in_stock->in_transit;
    //             $total_balance = $item_in_stock->total_balance;
    //             $total_supplied = $item_in_stock->total_supplied;
    //         }
    //         $quantity[] = [
    //             'name' => $item_name,
    //             'y' => (int) $total_stocked,
    //         ];
    //         $in_transit[] = [
    //             'name' => $item_name,
    //             'y' => (int) $item_in_transit,
    //         ];

    //         $balance[] = [
    //             'name' => $item_name,
    //             'y' => (int) $total_balance,
    //         ];
    //         $supplied[] = [
    //             'name' => $item_name,
    //             'y' => (int) $total_supplied,
    //         ];

    //     }
    //     $series = [
    //         [
    //             'name' => 'Initial Stock',
    //             'data' => $quantity, //array format
    //             // 'color' => '#333333',
    //             'stack' => 'Initial Stock'
    //         ],
    //         [
    //             'name' => 'In Transit',
    //             'data' => $in_transit, //array format
    //             'color' => '#f39c12',
    //             'stack' => 'In Stock'
    //         ],
    //         [
    //             'name' => 'In Stock',
    //             'data' => $balance, //array format
    //             'color' => '#00a65a',
    //             'stack' => 'In Stock'
    //         ],
    //         [
    //             'name' => 'Supplied',
    //             'data' => $supplied, //array format
    //             'color' => '#DC143C',
    //             'stack' => 'Supplied'
    //         ],
    //     ];
    //     $extra_title = ' from ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
    //     $subtitle = strtoupper($panel . 'ly Report');
    //     return response()->json([

    //         'categories'    => $categories,
    //         'series'      => $series,
    //         'title' => 'Number of Products in Stock at ' . $warehouse->name . $extra_title,
    //         'subtitle' => $subtitle,

    //     ],
    //         200
    //     );
    // }
    public function productsInStockGraph(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $date_from = Carbon::now()->startOfYear();
        $date_to = Carbon::now()->endOfYear();
        $panel = 'year';
        if (isset($request->from, $request->to, $request->panel)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $panel = $request->panel;
        }
        $warehouse = Warehouse::find($warehouse_id);
        $categories = [];
        $quantity = [];
        $in_transit = [];
        $balance = [];
        $supplied = [];

        $items = Item::orderBy('name')->get();
        foreach ($items as $item) {
            $item_in_stock = ItemStockSubBatch::with('item')->groupBy('item_id')->where(['warehouse_id' => $warehouse_id, 'item_id' => $item->id])->select('*', \DB::raw('SUM(quantity) as quantity'), \DB::raw('SUM(in_transit) as in_transit'), \DB::raw('SUM(balance) as total_balance'), \DB::raw('SUM(supplied) as total_supplied'))->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->first();

            $item_name = $item->name;
            $categories[] = $item_name . ' (' . $item->package_type . ')';

            $total_stocked = 0;
            $item_in_transit = 0;
            $total_balance = 0;
            $total_supplied = 0;
            if ($item_in_stock) {
                $total_stocked = $item_in_stock->quantity;
                $item_in_transit = $item_in_stock->in_transit;
                $total_balance = $item_in_stock->total_balance;
                $total_supplied = $item_in_stock->total_supplied;
            }
            $quantity[] = [
                'name' => $item_name,
                'y' => (int) $total_stocked,
            ];
            $in_transit[] = [
                'name' => $item_name,
                'y' => (int) $item_in_transit,
            ];

            $balance[] = [
                'name' => $item_name,
                'y' => (int) $total_balance,
            ];
            $supplied[] = [
                'name' => $item_name,
                'y' => (int) $total_supplied,
            ];
        }
        $series = [
            [
                'name' => 'Initial Stock',
                'data' => $quantity, //array format
                // 'color' => '#333333',
                'stack' => 'Initial Stock'
            ],
            [
                'name' => 'In Transit',
                'data' => $in_transit, //array format
                'color' => '#f39c12',
                'stack' => 'In Stock'
            ],
            [
                'name' => 'In Stock',
                'data' => $balance, //array format
                'color' => '#00a65a',
                'stack' => 'In Stock'
            ],
            [
                'name' => 'Supplied',
                'data' => $supplied, //array format
                'color' => '#DC143C',
                'stack' => 'Supplied'
            ],
        ];
        $extra_title = ' from ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
        $subtitle = strtoupper($panel . 'ly Report');
        return response()->json(
            [

                'categories' => $categories,
                'series' => $series,
                'title' => 'Number of Products Physically in Stock at ' . $warehouse->name . $extra_title,
                'subtitle' => $subtitle,

            ],
            200
        );
    }
    public function productsInStockTabular(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $date_from = Carbon::now()->startOfYear();
        $date_to = Carbon::now()->endOfYear();
        $panel = 'year';
        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
        }
        $panel = $request->panel;
        $view_by = $request->view_by;
        if ($view_by === 'sub_batch') {
            if ($warehouse_id === 'all') {
                $items_in_stock = ItemStockSubBatch::with(['item', 'warehouse'])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->orderBy('expiry_date')->get();
            } else {
                $items_in_stock = ItemStockSubBatch::with(['item', 'warehouse'])->where('warehouse_id', $warehouse_id)->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->orderBy('expiry_date')->get();
            }

            return response()->json(compact('items_in_stock'));
        }
        if ($view_by === 'batch') {
            // $items_in_stock = ItemStockSubBatch::with(['item'])->where('warehouse_id', $warehouse_id)->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->orderBy('id', 'DESC')->get();
            if ($warehouse_id === 'all') {
                $items_in_stock = ItemStockSubBatch::with(['item', 'warehouse'])->groupBy(['batch_no', 'warehouse_id'])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->select('*', \DB::raw('SUM(quantity) as quantity'), \DB::raw('SUM(in_transit) as in_transit'), \DB::raw('SUM(supplied) as supplied'), \DB::raw('SUM(balance) as balance'))->get();
            } else {
                $items_in_stock = ItemStockSubBatch::with(['item', 'warehouse'])->groupBy('batch_no')->where('warehouse_id', $warehouse_id)->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->select('*', \DB::raw('SUM(quantity) as quantity'), \DB::raw('SUM(in_transit) as in_transit'), \DB::raw('SUM(supplied) as supplied'), \DB::raw('SUM(balance) as balance'))->get();
            }
            return response()->json(compact('items_in_stock'));
        }
        if ($view_by === 'product') {
            if ($warehouse_id === 'all') {
                $items_in_stock = ItemStockSubBatch::with(['item', 'warehouse'])->groupBy(['item_id', 'warehouse_id'])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->select('*', \DB::raw('SUM(quantity) as quantity'), \DB::raw('SUM(in_transit) as in_transit'), \DB::raw('SUM(supplied) as supplied'), \DB::raw('SUM(balance) as balance'), \DB::raw('SUM(reserved_for_supply) as reserved_for_supply'))->get();
            } else {
                $items_in_stock = ItemStockSubBatch::with(['item', 'warehouse'])->groupBy('item_id')->where('warehouse_id', $warehouse_id)->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->select('*', \DB::raw('SUM(quantity) as quantity'), \DB::raw('SUM(in_transit) as in_transit'), \DB::raw('SUM(supplied) as supplied'), \DB::raw('SUM(balance) as balance'), \DB::raw('SUM(reserved_for_supply) as reserved_for_supply'))->get();
            }
            return response()->json(compact('items_in_stock'));
        }
    }
    public function reportsOnVehiclesGraph(Request $request)
    {
        return response()->json(
            [
                'expenses' => $this->expensesOnVehicles($request),
                'vehicles' => $this->getCurrentVehicleCondition($request),

            ],
            200
        );
    }
    private function fetchExpenseReport($expenses, $panel, $date_from, $date_to)
    {
        switch ($panel) {
            case 'week':
                $year_month = date('Y-m', strtotime($date_from));
                $first_day = date('d', strtotime($date_from));
                $last_day = date('d', strtotime($date_to));
                $categories = [];
                $approved = [];
                for ($i = $first_day; $i <= $last_day; $i++) {
                    $date = $year_month . '-' . $i;
                    $categories[] = date('M d, Y', strtotime($date));
                    $daily_expense = 0;
                    foreach ($expenses as $expense) {

                        if (date('d', strtotime($expense->updated_at)) == $i) {
                            $daily_expense += (float) $expense->amount;
                        }
                    }
                    $approved[] = $daily_expense;
                }
                return [$approved, $categories];
                break;
            case 'month':
                $month = date('m', strtotime($date_from));
                $year = date('Y', strtotime($date_from));
                $no_of_days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $categories = [];
                $approved = [];
                for ($i = 1; $i <= $no_of_days_in_month; $i++) {
                    $categories[] = $i;
                    $daily_expense = 0;
                    foreach ($expenses as $expense) {

                        if (date('d', strtotime($expense->updated_at)) == $i) {
                            $daily_expense += (float) $expense->amount;
                        }
                    }
                    $approved[] = $daily_expense;
                }
                return [$approved, $categories];
                break;
            case 'quarter':
                $month = date('m', strtotime($date_from));
                $year = date('Y', strtotime($date_from));
                $first_month = date('m', strtotime($date_from));
                $last_month = date('m', strtotime($date_to));
                $month_key = date('M', strtotime($date_from));
                $categories = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; //$this->monthsInAQuarter($month_key);
                $approved = [];
                for ($i = 1; $i <= 12; $i++) {
                    $categories[] = $i;
                    $daily_expense = 0;
                    foreach ($expenses as $expense) {

                        if (date('m', strtotime($expense->updated_at)) == $i) {
                            $daily_expense += (float) $expense->amount;
                        }
                    }
                    $approved[] = $daily_expense;
                }
                return [$approved, $categories];
                break;
            case 'year':
                $month = date('m', strtotime($date_from));
                $year = date('Y', strtotime($date_from));
                $no_of_days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $categories = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                $approved = [];
                for ($i = 1; $i <= 12; $i++) {
                    $daily_expense = 0;
                    foreach ($expenses as $expense) {

                        if (date('m', strtotime($expense->updated_at)) == $i) {
                            $daily_expense += (float) $expense->amount;
                        }
                    }
                    $approved[] = $daily_expense;
                }
                return [$approved, $categories];
                break;
            default:
                # code...
                break;
        }
    }
    private function expensesOnVehicles(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $date_from = Carbon::now()->startOfYear();
        $date_to = Carbon::now()->endOfYear();
        $panel = 'year';
        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $panel = $request->panel;
        }
        $warehouse = Warehouse::find($warehouse_id);

        $categories = [];
        $approved = [];
        // $vehicle_expenses = VehicleExpense::where(['warehouse_id' => $warehouse_id])->where('updated_at', '>=', $date_from)->where('updated_at', '<=', $date_to)->get();
        $vehicle_expenses = VehicleExpense::where(['warehouse_id' => $warehouse_id, 'status' => 'Approved'])->where('updated_at', '>=', $date_from)->where('updated_at', '<=', $date_to)->get();
        list($data, $categories) = $this->fetchExpenseReport($vehicle_expenses, $panel, $date_from, $date_to);

        $series = [
            [
                'name' => 'Vehicle Expenses',
                'lineWidth' => 4,
                'data' => $data,
            ],
        ];
        $plot_band = [];
        if ($panel === 'quarter' || $panel === 'year') {
            $plot_band = [
                [
                    'color' => '#fce7e734',
                    'from' => -0.5,
                    'to' => 2.5,
                    'label' => ['text' => '1st Quarter']
                ],
                [
                    'color' => '#e8c1ff34',
                    'from' => 2.5,
                    'to' => 5.5,
                    'label' => ['text' => '2nd Quarter']
                ],
                [
                    'color' => '#f7f58634',
                    'from' => 5.5,
                    'to' => 8.5,
                    'label' => ['text' => '3rd Quarter']
                ],
                [
                    'color' => '#c2e3f734',
                    'from' => 8.5,
                    'to' => 11.5,
                    'label' => ['text' => '4th Quarter']
                ],
            ];
        }
        $extra_title = ' from ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
        $subtitle = strtoupper($panel . 'ly Report');

        return [
            'categories' => $categories,
            'series' => $series,
            'title' => 'Total expenses on vehicles in ' . $warehouse->name . $extra_title,
            'subtitle' => $subtitle,
            'plot_band' => $plot_band

        ];
    }

    private function getCurrentVehicleCondition(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $vehicles = Vehicle::where('warehouse_id', $warehouse_id)->get();
        foreach ($vehicles as $vehicle) {
            $vehicle_condition = $vehicle->conditions()->orderBy('id', 'DESC')->where('warehouse_id', $warehouse_id)->first();
            $condition = 'Sound';
            $status = 'Good Condition';
            if ($vehicle_condition) {
                $condition = $vehicle_condition->condition;
                $status = $vehicle_condition->status;
            }
            $vehicle->condition = $condition;
            $vehicle->status = $status;
        }
        return $vehicles;
    }
    public function reportsOnWaybillGraph(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $date_from = Carbon::now()->startOfYear();
        $date_to = Carbon::now()->endOfYear();
        $panel = 'year';
        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $panel = $request->panel;
        }
        $warehouse = Warehouse::find($warehouse_id);

        $categories = [];
        // $vehicle_expenses = VehicleExpense::where(['warehouse_id' => $warehouse_id])->where('updated_at', '>=', $date_from)->where('updated_at', '<=', $date_to)->get();
        $delivery_expenses = DeliveryTripExpense::where(['warehouse_id' => $warehouse_id])->where('updated_at', '>=', $date_from)->where('updated_at', '<=', $date_to)->where('confirmed_by', '!=', null)->get();
        list($data, $categories) = $this->fetchExpenseReport($delivery_expenses, $panel, $date_from, $date_to);

        $series = [
            [
                'name' => 'Delivery Cost',
                'lineWidth' => 4,
                'data' => $data,
            ],
        ];
        $plot_band = [];
        if ($panel === 'quarter' || $panel === 'year') {
            $plot_band = [
                [
                    'color' => '#fce7e734',
                    'from' => -0.5,
                    'to' => 2.5,
                    'label' => ['text' => '1st Quarter']
                ],
                [
                    'color' => '#e8c1ff34',
                    'from' => 2.5,
                    'to' => 5.5,
                    'label' => ['text' => '2nd Quarter']
                ],
                [
                    'color' => '#f7f58634',
                    'from' => 5.5,
                    'to' => 8.5,
                    'label' => ['text' => '3rd Quarter']
                ],
                [
                    'color' => '#c2e3f734',
                    'from' => 8.5,
                    'to' => 11.5,
                    'label' => ['text' => '4th Quarter']
                ],
            ];
        }
        $extra_title = ' from ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
        $subtitle = strtoupper($panel . 'ly Report');

        return [
            'categories' => $categories,
            'series' => $series,
            'title' => 'Total Delivery Cost on Waybills in ' . $warehouse->name . $extra_title,
            'subtitle' => $subtitle,
            'plot_band' => $plot_band,

        ];
    }

    public function outbounds(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $warehouse_id = $request->warehouse_id;
        $date_from = Carbon::now()->startOfMonth();
        $date_to = Carbon::now()->endOfMonth();
        $panel = 'month';
        if (isset($request->from, $request->to, $request->panel)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $panel = $request->panel;
        }
        $outbounds = [];

        $dispatched_products = DispatchedProduct::with(['itemStock', 'waybill.dispatcher.vehicle.vehicleDrivers.driver.user', 'waybillItem.invoiceItem.invoice.customer.user', 'waybillItem.invoiceItem.item'])
            ->where(['warehouse_id' => $warehouse_id])
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            ->orderBy('id', 'DESC')
            ->get();
        // $invoice_items = InvoiceItem::with(['warehouse', 'invoice.customer.user', 'item', 'waybillItems.waybill.dispatcher.vehicle.vehicleDrivers.driver.user', 'batches.itemStockBatch', 'waybillItems.dispatchProduct'])->where(['warehouse_id' => $warehouse_id])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->orderBy('id', 'DESC')->get();
        $quantity_supplied = [];
        foreach ($dispatched_products as $dispatched_product) {


            $batch_nos = $dispatched_product->itemStock->batch_no;

            $dispatcher = '';
            $transit_date = $dispatched_product->created_at;
            if ($dispatched_product->waybill->dispatcher) {
                foreach ($dispatched_product->waybill->dispatcher->vehicle->vehicleDrivers as $vehicle_driver) {
                    $dispatcher .= ($vehicle_driver->driver) ? $vehicle_driver->driver->user->name : '-';
                }
            }

            $invoice_item = $dispatched_product->waybillItem->invoiceItem;
            if (!isset($quantity_supplied[$invoice_item->id])) {

                $quantity_supplied[$invoice_item->id] = 0;
            }
            $quantity = $invoice_item->quantity;
            $supplied = $dispatched_product->quantity_supplied;


            $total_supplied = $quantity_supplied[$invoice_item->id] + $supplied;
            $outbounds[] = [
                'dispatcher' => $dispatcher,
                'invoice_no' => $invoice_item->invoice->invoice_number,
                'customer' => ($invoice_item->invoice->customer) ? $invoice_item->invoice->customer->user->name : '',
                'product' => ($invoice_item->item) ? $invoice_item->item->name : '',
                'batch_nos' => $batch_nos,
                'amount' => $invoice_item->amount,
                'quantity' => $quantity . ' ' . $invoice_item->type,
                'supplied' => $supplied . ' ' . $invoice_item->type,
                'balance' => $quantity - $total_supplied . ' ' . $invoice_item->type, // initially set to zero
                'date' => $dispatched_product->waybillItem->created_at,
                'status' => $dispatched_product->status,
                'transit_date' => $transit_date,
                'delivery_date' => ($dispatched_product->status === 'delivered') ? $dispatched_product->updated_at : 'Pending',
            ];
            $quantity_supplied[$invoice_item->id] = $total_supplied;
        }
        $transfer_dispatched_products = TransferRequestDispatchedProduct::with(['itemStock', 'transferWaybill.dispatcher', 'transferWaybillItem.invoiceItem'])
            ->where(['supply_warehouse_id' => $warehouse_id])
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            ->get();
        $transfered_quantity_supplied = [];
        foreach ($transfer_dispatched_products as $transfer_dispatched_product) {
            $batch_nos = $transfer_dispatched_product->itemStock->batch_no;

            $dispatcher = '';
            $transit_date = $transfer_dispatched_product->created_at;
            if ($transfer_dispatched_product->transferWaybill->dispatcher) {
                $dispatcher = $transfer_dispatched_product->transferWaybill->dispatcher->name;
            }

            $invoice_item = $transfer_dispatched_product->transferWaybillItem->invoiceItem;
            $quantity = $invoice_item->quantity;
            $supplied = $transfer_dispatched_product->quantity_supplied;

            if (!isset($transfered_quantity_supplied[$invoice_item->id])) {

                $transfered_quantity_supplied[$invoice_item->id] = 0;
                ;
            }
            $total_supplied = $transfered_quantity_supplied[$invoice_item->id] + $supplied;
            $outbounds[] = [
                'dispatcher' => $dispatcher,
                'invoice_no' => $invoice_item->transferRequest->request_number,
                'customer' => $invoice_item->requestWarehouse->name,
                'product' => $invoice_item->item->name,
                'batch_nos' => $batch_nos,
                'amount' => $invoice_item->amount,
                'quantity' => $quantity . ' ' . $invoice_item->type,
                'supplied' => $supplied . ' ' . $invoice_item->type,
                'balance' => $quantity - $total_supplied . ' ' . $invoice_item->type,
                'date' => $transfer_dispatched_product->transferWaybillItem->created_at,
                'status' => $transfer_dispatched_product->status,
                'transit_date' => $transit_date,
                'delivery_date' => ($transfer_dispatched_product->status === 'delivered') ? $transfer_dispatched_product->updated_at : 'Pending',
            ];
            $transfered_quantity_supplied[$invoice_item->id] = $total_supplied;
        }
        usort($outbounds, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        ini_set('memory_limit', '128M');
        return response()->json(compact('outbounds'));
    }
    // public function waybills(Request $request)
    // {
    //     $warehouse_id = $request->warehouse_id;
    //     $waybills = [];

    //     if (isset($request->status) && $request->status != '') {
    //         ////// query by status //////////////
    //         $status = $request->status;
    //         $waybills = WayBill::with(['dispatcher.vehicle.vehicleDrivers.driver.user', 'invoice.customer.user', 'invoice.customer.type', 'waybillItems.item'])->where(['warehouse_id' => $warehouse_id, 'status' => $status])->get();
    //     }
    //     if (isset($request->from, $request->to, $request->status) && $request->from != '' && $request->from != '' && $request->status != '') {
    //         $date_from = date('Y-m-d H:i:s', strtotime($request->from));
    //         $date_to = date('Y-m-d H:i:s', strtotime($request->to));
    //         $status = $request->status;
    //         $panel = $request->panel;
    //         $invoices = WayBill::with(['dispatcher.vehicle.vehicleDrivers.driver.user', 'invoice.customer.user', 'invoice.customer.type', 'waybillItems.item'])->where(['warehouse_id' => $warehouse_id, 'status' => $status])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->get();
    //     }
    //     return response()->json(compact('waybills'));
    // }

    public function auditTrails(Request $request)
    {
        //     $schedule = new Schedule();
        //     $list = $schedule
        //   ->command('backup:run')
        //   ->onFailure(function () {
        //         return 'failed';
        //   })
        //   ->onSuccess(function () {
        //         return 'success';
        //   });
        $date_from = Carbon::now()->startOfWeek();
        $date_to = Carbon::now()->endOfWeek();
        $panel = 'week';
        if (isset($request->from, $request->to, $request->panel)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $panel = $request->panel;
        }
        $activity_logs = Notification::where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->orderBy('created_at', 'DESC')->paginate(20);
        return response()->json(compact('activity_logs'), 200);
    }
    public function markAsRead()
    {
        $user = $this->getUser();
        $user->unreadNotifications->markAsRead();
        return response()->json([], 204);
    }
    public function backUps()
    {
        $date = Date('Y-m-d', strtotime('now'));
        $file_name = 'gpl_db_backup_' . $date . '.sql.gz';
        $url = 'storage/bkup/db/' . $file_name;
        // $directories = Storage::allDirectories($directory);

        return response()->json(compact('url', 'date'), 200);
    }
    public function fetchBinCard(Request $request)
    {
        $date_from = Carbon::now()->startOfMonth();
        $date_to = Carbon::now()->endOfMonth();
        $panel = 'month';
        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $panel = $request->panel;
        }
        $warehouse_id = $request->warehouse_id;
        $item_id = $request->item_id;
        $item = Item::find($item_id);
        if (isset($request->batch_no) && $request->batch_no != '') {
            // $batch_no_array = explode(',', $request->batch_no);
            // $products_in_stock_ids = ItemStockSubBatch::whereIn('batch_no', $batch_no_array)->pluck('id');
            $products_in_stock_ids = ItemStockSubBatch::where('batch_no', 'LIKE', '%' . $request->batch_no . '%')->pluck('id');

            list($total_stock_till_date, $previous_outbound, $previous_transfer_outbound, $previous_expired_product, $inbounds, $outbounds, $outbounds2, $expired_products) = $this->getProductTransactionBasedOnBatchNo($item_id, $date_from, $date_to, $warehouse_id, $products_in_stock_ids);

        } else {
            list($total_stock_till_date, $previous_outbound, $previous_transfer_outbound, $previous_expired_product, $inbounds, $outbounds, $outbounds2, $expired_products) = $this->getProductTransaction($item_id, $date_from, $date_to, $warehouse_id);
        }




        $bincards = [];
        $quantity_in_stock = ($total_stock_till_date) ? $total_stock_till_date->total_quantity : 0;
        $quantity_supplied = ($previous_outbound) ? $previous_outbound->total_quantity_supplied : 0;
        $transfer_item_quantity_supplied = ($previous_transfer_outbound) ? $previous_transfer_outbound->total_quantity_supplied : 0;
        $expired_quantity = ($previous_expired_product) ? $previous_expired_product->total_quantity : 0;

        $brought_forward = (int) $quantity_in_stock - (int) $quantity_supplied - (int) $transfer_item_quantity_supplied - (int) $expired_quantity;
        if ($inbounds->isNotEmpty()) {
            foreach ($inbounds as $inbound) {
                //$running_balance += $inbound->quantity;
                $bincards[] = [
                    'type' => 'in_bound',
                    'date' => $inbound->created_at,
                    'invoice_no' => '',
                    'waybill_grn' => $inbound->batch_no . '/' . $inbound->goods_received_note,
                    'quantity_transacted' => (int) $inbound->total_quantity,
                    'in' => (int) $inbound->total_quantity,
                    'out' => '',
                    'balance' => 0, // initially set to zero
                    'packaging' => $item->package_type,
                    'physical_quantity' => '',
                    'sign' => '',
                    'comments' => $inbound->comments
                ];
            }
        }
        if ($outbounds->isNotEmpty()) {
            foreach ($outbounds as $outbound) {
                //$running_balance -= $outbound->quantity_supplied;
                $bincards[] = [
                    'type' => 'out_bound',
                    'date' => $outbound->created_at,
                    'invoice_no' => $outbound->waybillItem->invoice->invoice_number,
                    'waybill_grn' => $outbound->waybill->waybill_no,
                    'quantity_transacted' => (int) $outbound->total_quantity_supplied,
                    'in' => '',
                    'out' => (int) $outbound->total_quantity_supplied,
                    'balance' => 0, // initially set to zero
                    'packaging' => $item->package_type,
                    'physical_quantity' => '',
                    'sign' => '',
                    'comments' => ''
                ];
            }
        }
        if ($outbounds2->isNotEmpty()) {
            foreach ($outbounds2 as $outbound) {
                //$running_balance -= $outbound->quantity_supplied;
                $bincards[] = [
                    'type' => 'out_bound',
                    'date' => $outbound->created_at,
                    'invoice_no' => $outbound->transferWaybillItem->invoice->request_number,
                    'waybill_grn' => ($outbound->transferWaybill) ? $outbound->transferWaybill->transfer_request_waybill_no : '',
                    'quantity_transacted' => $outbound->total_quantity_supplied,
                    'in' => '',
                    'out' => $outbound->total_quantity_supplied,
                    'balance' => 0, // initially set to zero
                    'packaging' => $item->package_type,
                    'physical_quantity' => '',
                    'sign' => '',
                    'comments' => ''
                ];
            }
        }
        if ($expired_products->isNotEmpty()) {
            foreach ($expired_products as $expired_product) {
                $bincards[] = [
                    'type' => 'out_bound',
                    'date' => $expired_product->created_at,
                    'invoice_no' => '-',
                    'waybill_grn' => 'Batch: ' . $expired_product->batch_no . " (EXPIRED)",
                    'quantity_transacted' => $expired_product->quantity,
                    'in' => '',
                    'out' => $expired_product->quantity,
                    'balance' => 0, // initially set to zero
                    'packaging' => $item->package_type,
                    'physical_quantity' => '',
                    'sign' => '',
                    'comments' => 'EXPIRED'
                ];
            }
        }

        usort($bincards, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        $date_from_formatted = date('Y-m-d', strtotime($date_from));
        $date_to_formatted = date('Y-m-d', strtotime($date_to));
        return response()->json(compact('bincards', 'brought_forward', 'date_from_formatted', 'date_to_formatted'), 200);
    }
    private function getProductTransactionBasedOnBatchNo($item_id, $date_from, $date_to, $warehouse_id, $products_in_stock_ids = [])
    {

        $total_stock_till_date = ItemStockSubBatch::groupBy('item_id')
            ->whereIn('id', $products_in_stock_ids)
            ->where('warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '<', $date_from)
            ->where(function ($q) {
                $q->whereRaw('confirmed_by IS NOT NULL');
                $q->orWhere(function ($p) {
                    $p->whereRaw('confirmed_by IS NULL');
                    // $p->where('supplied', '>', 0);
                    $p->whereRaw('supplied + expired > 0');
                });
            })
            ->select(\DB::raw('SUM(quantity - old_balance_before_recount) as total_quantity'))
            ->first();
        $previous_outbound = DispatchedProduct::groupBy('item_id')
            ->whereIn('item_stock_sub_batch_id', $products_in_stock_ids)
            ->where('warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '<', $date_from)
            // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
            ->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))
            ->orderby('created_at')->first();

        $previous_transfer_outbound = TransferRequestDispatchedProduct::groupBy('item_id')
            ->whereIn('item_stock_sub_batch_id', $products_in_stock_ids)
            ->where('supply_warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '<', $date_from)
            // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
            ->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))
            ->orderby('created_at')
            ->first();
        // expired warehouse has id of 8
        $previous_expired_product = ItemStockSubBatch::groupBy(['item_id'])
            ->whereIn('id', $products_in_stock_ids)
            ->where(['item_id' => $item_id, 'warehouse_id' => 8, 'expired_from' => $warehouse_id])
            ->where('created_at', '<', $date_from)
            ->select(\DB::raw('SUM(quantity - old_balance_before_recount) as total_quantity'))
            ->first();

        $inbounds = ItemStockSubBatch::groupBy('batch_no', 'is_warehouse_transfered')
            ->whereIn('id', $products_in_stock_ids)
            ->where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            ->where(function ($q) {
                $q->whereRaw('confirmed_by IS NOT NULL');
                $q->orWhere(function ($p) {
                    $p->whereRaw('confirmed_by IS NULL');
                    // $p->where('supplied', '>', 0);
                    $p->whereRaw('supplied + expired > 0');
                });
            })
            ->select('batch_no', 'goods_received_note', 'comments', 'created_at', \DB::raw('SUM(quantity) as total_quantity'))
            // ->selectRaw('SUM(quantity - old_balance_before_recount) as total_quantity, batch_no, goods_received_note, comments, created_at')
            ->orderby('created_at')
            ->get();
        // $inbounds2 = ItemStockSubBatch::groupBy(['batch_no'])
        //     ->where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])
        //     ->where('created_at', '>=', $date_from)
        //     ->where('created_at', '<=', $date_to)
        //     ->where('is_warehouse_transfered', '=',  1)
        //     ->where(function ($q) {
        //         $q->whereRaw('confirmed_by IS NOT NULL');
        //         $q->orWhere(function ($p) {
        //             $p->whereRaw('confirmed_by IS NULL');
        //             // $p->where('supplied', '>', 0);
        //             $p->whereRaw('supplied + expired > 0');
        //         });
        //     })
        //     ->select('batch_no', 'goods_received_note', 'comments', 'created_at', \DB::raw('SUM(quantity) as total_quantity'))
        //     // ->selectRaw('SUM(quantity - old_balance_before_recount) as total_quantity, batch_no, goods_received_note, comments, created_at')
        //     ->orderby('created_at')
        //     ->get();
        // $inbounds = $inbounds1->merge($inbounds2);
        $outbounds = DispatchedProduct::groupBy(['waybill_item_id'])
            ->whereIn('item_stock_sub_batch_id', $products_in_stock_ids)
            ->where('warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
            ->select('*', \DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->orderby('created_at')->get();
        $outbounds2 = TransferRequestDispatchedProduct::groupBy(['transfer_request_waybill_item_id'])
            ->whereIn('item_stock_sub_batch_id', $products_in_stock_ids)
            ->where('supply_warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
            ->select('*', \DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->orderby('created_at')->get();

        // $expired_product = ExpiredProduct::groupBy(['batch_no'])->where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])->where('expiry_date', '>=', $date_from)->where('expiry_date', '<=', $date_to)->select('*', \DB::raw('SUM(quantity) as total_quantity'))->first();

        // expired warehouse has id of 8
        $expired_products = ItemStockSubBatch::where(['item_id' => $item_id, 'expired_from' => $warehouse_id])
            ->whereIn('id', $products_in_stock_ids)
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            // ->select('quantity - old_balance_before_recountas quantity', 'batch_no', 'goods_received_note', 'created_at')
            ->selectRaw('quantity - old_balance_before_recount as quantity, batch_no, goods_received_note, comments, created_at')
            ->get();

        return array($total_stock_till_date, $previous_outbound, $previous_transfer_outbound, $previous_expired_product, $inbounds, $outbounds, $outbounds2, $expired_products);
    }
    private function getProductTransaction($item_id, $date_from, $date_to, $warehouse_id)
    {

        $total_stock_till_date = ItemStockSubBatch::groupBy('item_id')
            ->where('warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '<', $date_from)
            ->where(function ($q) {
                $q->whereRaw('confirmed_by IS NOT NULL');
                $q->orWhere(function ($p) {
                    $p->whereRaw('confirmed_by IS NULL');
                    // $p->where('supplied', '>', 0);
                    $p->whereRaw('supplied + expired > 0');
                });
            })
            ->select(\DB::raw('SUM(quantity - old_balance_before_recount) as total_quantity'))
            ->first();
        $previous_outbound = DispatchedProduct::groupBy('item_id')
            ->where('warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '<', $date_from)
            // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
            ->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))
            ->orderby('created_at')->first();

        $previous_transfer_outbound = TransferRequestDispatchedProduct::groupBy('item_id')
            ->where('supply_warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '<', $date_from)
            // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
            ->select(\DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))
            ->orderby('created_at')
            ->first();
        // expired warehouse has id of 8
        $previous_expired_product = ItemStockSubBatch::groupBy(['item_id'])
            ->where(['item_id' => $item_id, 'warehouse_id' => 8, 'expired_from' => $warehouse_id])
            ->where('created_at', '<', $date_from)
            ->select(\DB::raw('SUM(quantity - old_balance_before_recount) as total_quantity'))
            ->first();

        $inbounds = ItemStockSubBatch::groupBy('batch_no', 'is_warehouse_transfered', 'created_at')
            ->where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            ->where(function ($q) {
                $q->whereRaw('confirmed_by IS NOT NULL');
                $q->orWhere(function ($p) {
                    $p->whereRaw('confirmed_by IS NULL');
                    // $p->where('supplied', '>', 0);
                    $p->whereRaw('supplied + expired > 0');
                });
            })
            ->select('batch_no', 'goods_received_note', 'comments', 'created_at', \DB::raw('SUM(quantity) as total_quantity'))
            // ->selectRaw('SUM(quantity - old_balance_before_recount) as total_quantity, batch_no, goods_received_note, comments, created_at')
            ->orderby('created_at')
            ->get();
        // $inbounds2 = ItemStockSubBatch::groupBy(['batch_no'])
        //     ->where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])
        //     ->where('created_at', '>=', $date_from)
        //     ->where('created_at', '<=', $date_to)
        //     ->where('is_warehouse_transfered', '=',  1)
        //     ->where(function ($q) {
        //         $q->whereRaw('confirmed_by IS NOT NULL');
        //         $q->orWhere(function ($p) {
        //             $p->whereRaw('confirmed_by IS NULL');
        //             // $p->where('supplied', '>', 0);
        //             $p->whereRaw('supplied + expired > 0');
        //         });
        //     })
        //     ->select('batch_no', 'goods_received_note', 'comments', 'created_at', \DB::raw('SUM(quantity) as total_quantity'))
        //     // ->selectRaw('SUM(quantity - old_balance_before_recount) as total_quantity, batch_no, goods_received_note, comments, created_at')
        //     ->orderby('created_at')
        //     ->get();
        // $inbounds = $inbounds1->merge($inbounds2);
        $outbounds = DispatchedProduct::groupBy(['waybill_item_id'])
            ->where('warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
            ->select('*', \DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->orderby('created_at')->get();
        $outbounds2 = TransferRequestDispatchedProduct::groupBy(['transfer_request_waybill_item_id', 'status'])
            ->where('supply_warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            // ->where('item_stock_sub_batches.confirmed_by', '!=', null)
            ->select('*', \DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->orderby('created_at')->get();

        // $expired_product = ExpiredProduct::groupBy(['batch_no'])->where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])->where('expiry_date', '>=', $date_from)->where('expiry_date', '<=', $date_to)->select('*', \DB::raw('SUM(quantity) as total_quantity'))->first();

        // expired warehouse has id of 8
        $expired_products = ItemStockSubBatch::where(['item_id' => $item_id, 'expired_from' => $warehouse_id])
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            // ->select('quantity - old_balance_before_recountas quantity', 'batch_no', 'goods_received_note', 'created_at')
            ->selectRaw('quantity - old_balance_before_recount as quantity, batch_no, goods_received_note, comments, created_at')
            ->get();

        return array($total_stock_till_date, $previous_outbound, $previous_transfer_outbound, $previous_expired_product, $inbounds, $outbounds, $outbounds2, $expired_products);
    }
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
                $q->whereRaw('confirmed_by IS NOT NULL');
                $q->orWhere(function ($p) {
                    $p->whereRaw('confirmed_by IS NULL');
                    // $p->where('supplied', '>', 0);
                    $p->whereRaw('supplied + expired > 0');
                });
            });
        //if ($warehouse_id != 'all') {
        $total_stock_till_date->where('warehouse_id', $warehouse_id);
        //}
        $total_stock_till_date = $total_stock_till_date->select(\DB::raw('SUM(quantity - old_balance_before_recount) as total_quantity'))
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
        $previous_expired_product = $previous_expired_product->select(\DB::raw('SUM(quantity - old_balance_before_recount) as total_quantity'))->first();



        $inbounds->where(['item_id' => $item_id])
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            ->where(function ($q) {
                $q->whereRaw('confirmed_by IS NOT NULL');
                $q->orWhere(function ($p) {
                    $p->whereRaw('confirmed_by IS NULL');
                    // $p->where('supplied', '>', 0);
                    $p->whereRaw('supplied + expired > 0');
                });
            });
        // if ($warehouse_id != 'all') {
        $inbounds->where('warehouse_id', $warehouse_id);
        // }
        $inbounds = $inbounds->select(\DB::raw('SUM(quantity - old_balance_before_recount) as total_inbound'))
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
        $expired_products = $expired_products->select(\DB::raw('SUM(quantity - old_balance_before_recount) as total_expired'))
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
            $product_type = $request->product_type;
            $category_ids = Category::where('group_name', $product_type)->pluck('id');
            // $item_ids = Item::whereIn('category_id', $category_ids)->pluck('id');
            if ($is_download == 'yes') {
                $items = Item::join('categories', 'categories.id', '=', 'items.category_id')
                    ->whereIn('category_id', $category_ids)
                    ->orderBy('items.name')
                    ->select('items.id', 'items.name', 'package_type')
                    ->get();
            } else {
                $items = Item::join('categories', 'categories.id', '=', 'items.category_id')
                    ->whereIn('category_id', $category_ids)
                    ->orderBy('items.name')
                    ->select('items.id', 'items.name', 'package_type')
                    ->paginate($request->limit);
            }
        }



        $warehouse_id = $request->warehouse_id;
        if ($warehouse_id === 'all') {
            $warehouses = Warehouse::get();
        } else {
            // we do it this way to get a collection even though it is single
            $warehouses = Warehouse::where('id', $warehouse_id)->get();
        }
        $items_in_stock = [];
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

                $brought_forward = (int) $quantity_in_stock - (int) $quantity_supplied - (int) $transfer_item_quantity_supplied - (int) $expired_quantity;

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
            $items_in_stock = array_merge($items_in_stock, $products);
        }
        return response()->json(compact('items', 'items_in_stock'), 200);
    }

    public function reservedProductTransactions(ItemStockSubBatch $item_in_stock)
    {
        $item_stock_sub_batch_id = $item_in_stock->id;
        $invoice_batches = InvoiceItemBatch::where('item_stock_sub_batch_id', $item_stock_sub_batch_id)->where('quantity', '>', 0)->get();

        $transfer_invoice_batches = TransferRequestItemBatch::where('item_stock_sub_batch_id', $item_stock_sub_batch_id)->where('quantity', '>', 0)->get();
        $tranactions = [];
        if ($invoice_batches->isNotEmpty()) {
            foreach ($invoice_batches as $invoice_batch) {
                //$running_balance -= $outbound->quantity_supplied;
                if ($invoice_batch->invoiceItem) {
                    # code...

                    $waybill_items = $invoice_batch->invoiceItem->waybillItems;
                    $tranactions[] = [
                        'invoice_no' => $invoice_batch->invoice->invoice_number,
                        'waybill_no' => $waybill_items[0]->waybill->waybill_no,
                        'quantity' => $invoice_batch->quantity,
                        'mode' => 'warehouse to customer',
                        'customer' => $invoice_batch->invoice->customer->user->name,
                        'date' => $invoice_batch->created_at,
                    ];
                }
            }
        }
        if ($transfer_invoice_batches->isNotEmpty()) {
            foreach ($transfer_invoice_batches as $transfer_invoice_batch) {
                if ($transfer_invoice_batch->transferRequestItem) {
                    $waybill_items = $transfer_invoice_batch->transferRequestItem->waybillItems;
                    $tranactions[] = [
                        'invoice_no' => $transfer_invoice_batch->transferRequest->request_number,
                        'waybill_no' => (count($waybill_items) > 0) ? $waybill_items[0]->waybill->transfer_request_waybill_no : '',
                        'quantity' => $transfer_invoice_batch->quantity,
                        'mode' => 'warehouse to warehouse',
                        'customer' => $transfer_invoice_batch->transferRequest->requestWarehouse->name,
                        'date' => $transfer_invoice_batch->created_at,
                    ];
                }
            }
        }
        usort($tranactions, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $tranactions;
    }

    public function allWaybilledInvoices(Request $request)
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
            $invoiceItemQuery = $invoiceItemQuery->where('waybill_items.created_at', '>=', $date_from);
            $invoiceItemQuery = $invoiceItemQuery->where('waybill_items.created_at', '<=', $date_to);
        }
        $invoiceItemQuery->orderBy('waybill_items.id', 'DESC');
        // $invoice_items = $invoiceItemQuery->get();
        $is_download = 'no';
        if (isset($request->is_download)) {
            $is_download = $request->is_download;
        }
        if ($is_download == 'yes') {
            $invoice_items = $invoiceItemQuery->get();
        } else {
            $invoice_items = $invoiceItemQuery->paginate($request->limit);
        }
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
        $invoiceItemQuery->orderBy('invoice_items.id', 'DESC');
        $is_download = 'no';
        if (isset($request->is_download)) {
            $is_download = $request->is_download;
        }
        if ($is_download == 'yes') {
            $invoice_items = $invoiceItemQuery->get();
        } else {
            $invoice_items = $invoiceItemQuery->paginate($request->limit);
        }
        $start_date = date('d-m-Y H:i:s', strtotime($date_from));
        $end_date = date('d-m-Y H:i:s', strtotime($date_to));
        return response()->json(compact('start_date', 'end_date', 'invoice_items'), 200);
    }
    public function unsuppliedInvoices(Request $request)
    {
        set_time_limit(0);
        $invoiceItemQuery = InvoiceItem::query();

        $invoiceItemQuery = $invoiceItemQuery->join('invoices', 'invoice_items.invoice_id', 'invoices.id')
            ->join('customers', 'invoices.customer_id', 'customers.id')
            ->join('users', 'customers.user_id', 'users.id')
            ->join('items', 'invoice_items.item_id', 'items.id')
            ->join('warehouses', 'invoice_items.warehouse_id', 'warehouses.id')
            // ->rightJoin('invoice_item_batches', 'invoice_items.id', 'invoice_item_batches.invoice_item_id')
            // ->join('waybill_items', 'waybill_items.invoice_item_id', 'invoice_items.id')

            // ->where('supply_status', '!=', 'Complete')
            ->where('invoices.status', '=', 'auditor approved')
            ->orWhere('invoices.status', '=', 'partially supplied')
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

        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.created_at', '>=', $date_from);
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.created_at', '<=', $date_to);
        }
        $is_download = 'no';
        if (isset($request->is_download)) {
            $is_download = $request->is_download;
        }

        if ($is_download == 'yes') {
            $invoice_items = $invoiceItemQuery->get();
        } else {
            $invoice_items = $invoiceItemQuery->paginate($request->limit);
        }
        return response()->json(compact('invoice_items'), 200);
    }
    // public function unsuppliedInvoices2(Request $request)
    // {
    //     set_time_limit(0);
    //     $warehouse_id = $request->warehouse_id;
    //     $date_from = Carbon::now()->startOfMonth();
    //     $date_to = Carbon::now()->endOfMonth();
    //     $panel = 'month';
    //     if (isset($request->from, $request->to, $request->panel)) {
    //         $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
    //         $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
    //         $panel = $request->panel;
    //     }
    //     $condition = [];
    //     if (isset($request->warehouse_id) && $request->warehouse_id !== 'all') {
    //         $condition = ['warehouse_id' => $request->warehouse_id];
    //     }

    //     $is_download = 'no';
    //     if (isset($request->is_download)) {
    //         $is_download = $request->is_download;
    //     }
    //     $invoiceItemQuery = InvoiceItem::with('warehouse', 'invoice.customer.user', 'item', 'firstWaybillItem')
    //         ->where($condition)
    //         // ->where('supply_status', '!=', 'Complete')
    //         ->whereRaw('quantity - quantity_supplied - quantity_reversed > 0')
    //         // ->where(function ($q) {
    //         //     $q->where('supply_status', '!=', 'Complete');
    //         //     $q->orWhere(function ($p) {
    //         //         $p->whereRaw('quantity - quantity_supplied > 0');
    //         //     });
    //         // })
    //         ->where('updated_at', '>=', $date_from)
    //         ->where('updated_at', '<=', $date_to);

    //     if ($is_download == 'yes') {
    //         $invoice_items = $invoiceItemQuery->get();
    //     } else {
    //         $invoice_items = $invoiceItemQuery->paginate(50);
    //     }
    //     return response()->json(compact('invoice_items'), 200);
    // }

    public function allUntreatedInvoices(Request $request)
    {
        set_time_limit(0);
        $warehouse_id = $request->warehouse_id;
        $panel = 'month';
        $condition = [];
        if (isset($request->warehouse_id) && $request->warehouse_id !== 'all') {
            $condition = ['warehouse_id' => $request->warehouse_id];
        }
        $invoiceItemQuery = InvoiceItem::with('warehouse', 'invoice.customer.user', 'item')
            ->where($condition)
            ->where('quantity_supplied', '=', 0)
            ->whereRaw('quantity - quantity_supplied - quantity_reversed > 0');

        if (isset($request->invoice_no) && $request->invoice_no !== '') {
            $invoice_no = $request->invoice_no;
            $invoiceItemQuery->whereHas('invoice', function ($q) use ($invoice_no) {
                $q->where('invoice_number', $invoice_no);
            });
        }
        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $invoiceItemQuery = $invoiceItemQuery->where('created_at', '>=', $date_from);
            $invoiceItemQuery = $invoiceItemQuery->where('created_at', '<=', $date_to);
        }
        $is_download = 'no';
        if (isset($request->is_download)) {
            $is_download = $request->is_download;
        }

        if ($is_download == 'yes') {
            $invoice_items = $invoiceItemQuery->get();
        } else {
            $invoice_items = $invoiceItemQuery->paginate(50);
        }
        return response()->json(compact('invoice_items'), 200);
    }

    public function allPartiallyTreatedInvoices(Request $request)
    {
        set_time_limit(0);
        $warehouse_id = $request->warehouse_id;
        $panel = 'month';
        $condition = [];
        if (isset($request->warehouse_id) && $request->warehouse_id !== 'all') {
            $condition = ['invoice_items.warehouse_id' => $request->warehouse_id];
        }
        $invoiceItemQuery = InvoiceItemBatch::groupBy('invoice_item_id')
            ->with('invoiceItem.warehouse', 'invoice.customer.user', 'invoiceItem.item')
            ->join('invoice_items', 'invoice_items.id', '=', 'invoice_item_batches.invoice_item_id')
            ->where($condition)
            ->where('invoice_item_batches.quantity', '>', 0)
            ->select('invoice_item_batches.*');

        if (isset($request->invoice_no) && $request->invoice_no !== '') {
            $invoice_no = $request->invoice_no;
            $invoiceItemQuery->whereHas('invoice', function ($q) use ($invoice_no) {
                $q->where('invoice_number', $invoice_no);
            });
        }
        if (isset($request->from, $request->to)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.created_at', '>=', $date_from);
            $invoiceItemQuery = $invoiceItemQuery->where('invoice_items.created_at', '<=', $date_to);
        }
        $is_download = 'no';
        if (isset($request->is_download)) {
            $is_download = $request->is_download;
        }

        if ($is_download == 'yes') {
            $invoice_item_batches = $invoiceItemQuery->get();
        } else {
            $invoice_item_batches = $invoiceItemQuery->paginate(50);
        }
        return response()->json(compact('invoice_item_batches'), 200);
    }
}
