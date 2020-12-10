<?php

namespace App\Http\Controllers;

use App\Models\Invoice\Invoice;
use App\Models\Invoice\Waybill;
use App\Models\Invoice\DeliveryTripExpense;
use App\Models\Invoice\DispatchedProduct;
use App\Models\Invoice\InvoiceItem;
use App\Models\Logistics\Vehicle;
use App\Models\Logistics\VehicleCondition;
use App\Models\Logistics\VehicleExpense;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStock;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Transfers\TransferRequestDispatchedProduct;
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

                'categories'    => $categories,
                'series'      => $series,
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
            $items_in_stock = ItemStockSubBatch::with('item')->where('warehouse_id', $warehouse_id)->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->orderBy('expiry_date')->get();
            return response()->json(compact('items_in_stock'));
        }
        if ($view_by === 'batch') {
            // $items_in_stock = ItemStockSubBatch::with(['item'])->where('warehouse_id', $warehouse_id)->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->orderBy('id', 'DESC')->get();
            $items_in_stock = ItemStockSubBatch::with(['item'])->groupBy('batch_no')->where('warehouse_id', $warehouse_id)->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->select('*', \DB::raw('SUM(quantity) as quantity'), \DB::raw('SUM(in_transit) as in_transit'), \DB::raw('SUM(supplied) as supplied'), \DB::raw('SUM(balance) as balance'))->get();
            return response()->json(compact('items_in_stock'));
        }
        if ($view_by === 'product') {
            $items_in_stock = ItemStockSubBatch::with(['item'])->groupBy('item_id')->where('warehouse_id', $warehouse_id)->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->where('confirmed_by', '!=', null)->select('*', \DB::raw('SUM(quantity) as quantity'), \DB::raw('SUM(in_transit) as in_transit'), \DB::raw('SUM(supplied) as supplied'), \DB::raw('SUM(balance) as balance'), \DB::raw('SUM(reserved_for_supply) as reserved_for_supply'))->get();
            return response()->json(compact('items_in_stock'));
        }
    }
    public function reportsOnVehiclesGraph(Request $request)
    {
        return response()->json(
            [
                'expenses'  => $this->expensesOnVehicles($request),
                'vehicles'  => $this->getCurrentVehicleCondition($request),

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
            'categories'    => $categories,
            'series'      => $series,
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
            'categories'    => $categories,
            'series'      => $series,
            'title' => 'Total Delivery Cost on Waybills in ' . $warehouse->name . $extra_title,
            'subtitle' => $subtitle,
            'plot_band' => $plot_band,

        ];
    }
    public function outbounds(Request $request)
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
        $invoice_items = InvoiceItem::with(['warehouse', 'invoice.customer.user', 'item', 'waybillItems.waybill.dispatcher.vehicle.vehicleDrivers.driver.user', 'batches.itemStockBatch'])->where(['warehouse_id' => $warehouse_id])->where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->orderBy('id', 'DESC')->get();


        return response()->json(compact('invoice_items'));
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
        $file_name = 'gpl_db_backup_' . $date . '.sql';
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
        $total_stock_till_date = ItemStockSubBatch::groupBy('item_id')
            ->where('warehouse_id', $warehouse_id)
            ->where('item_id', $item_id)
            ->where('created_at', '<', $date_from)
            ->where('confirmed_by', '!=', null)
            ->select('*', \DB::raw('SUM(quantity) as quantity'))
            ->first();
        $previous_outbound = DispatchedProduct::join('item_stock_sub_batches', 'dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')->groupBy('item_stock_sub_batches.item_id')
            ->where('dispatched_products.warehouse_id', $warehouse_id)
            ->where('item_stock_sub_batches.item_id', $item_id)
            ->where('dispatched_products.created_at', '<', $date_from)
            ->select('dispatched_products.*', \DB::raw('SUM(quantity_supplied) as quantity_supplied'))
            ->orderby('dispatched_products.created_at')->first();

        $previous_transfer_outbound = TransferRequestDispatchedProduct::join('item_stock_sub_batches', 'transfer_request_dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')->groupBy('item_stock_sub_batches.item_id')
            ->where('supply_warehouse_id', $warehouse_id)
            ->where('item_stock_sub_batches.item_id', $item_id)
            ->where('transfer_request_dispatched_products.created_at', '<', $date_from)
            ->select('transfer_request_dispatched_products.*', \DB::raw('SUM(quantity_supplied) as quantity_supplied'))
            ->orderby('transfer_request_dispatched_products.created_at')->first();
        // return [
        //     'total' => $total_stock_till_date,
        //     'previous_outbound' => $previous_outbound,
        //     'today_stock' => $total_inbound_for_duration,
        // ];
        $inbounds = ItemStockSubBatch::where(['item_id' => $item_id, 'warehouse_id' => $warehouse_id])
            ->where('created_at', '>=', $date_from)
            ->where('created_at', '<=', $date_to)
            ->orderby('created_at')
            ->get();
        $bincards = [];
        $quantity_in_stock = ($total_stock_till_date) ? $total_stock_till_date->quantity : 0;
        $quantity_supplied = ($previous_outbound) ? $previous_outbound->quantity_supplied : 0;
        $transfer_item_quantity_supplied = ($previous_transfer_outbound) ? $previous_transfer_outbound->quantity_supplied : 0;

        $brought_forward = (int)$quantity_in_stock - (int) $quantity_supplied - (int) $transfer_item_quantity_supplied;
        foreach ($inbounds as $inbound) {
            //$running_balance += $inbound->quantity;
            $bincards[]  = [
                'type' => 'in_bound',
                'date' => $inbound->created_at,
                'invoice_no' => '',
                'waybill_grn' => $inbound->goods_received_note,
                'quantity_transacted' => $inbound->quantity,
                'in' => $inbound->quantity,
                'out' => '',
                'balance' => 0, // initially set to zero
                'packaging' => $inbound->item->package_type,
                'physical_quantity' => '',
                'sign' => '',
            ];
        }
        // $outbounds = DispatchedProduct::join('item_stock_sub_batches', 'dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')
        //     ->where('item_stock_sub_batches.item_id', $item_id)
        //     ->where('dispatched_products.created_at', '>=', $date_from)
        //     ->where('dispatched_products.created_at', '<=', $date_to)
        //     ->select('dispatched_products.*')->orderby('dispatched_products.created_at')->get();
        $outbounds = DispatchedProduct::join('item_stock_sub_batches', 'dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')
            ->groupBy('waybill_id')
            ->where('dispatched_products.warehouse_id', $warehouse_id)
            ->where('item_stock_sub_batches.item_id', $item_id)
            ->where('dispatched_products.created_at', '>=', $date_from)
            ->where('dispatched_products.created_at', '<=', $date_to)
            ->select('dispatched_products.*', \DB::raw('SUM(quantity_supplied) as quantity_supplied'))->orderby('dispatched_products.created_at')->get();
        foreach ($outbounds as $outbound) {
            //$running_balance -= $outbound->quantity_supplied;
            $bincards[] = [
                'type' => 'out_bound',
                'date' => $outbound->created_at,
                'invoice_no' => $outbound->waybillItem->invoice->invoice_number,
                'waybill_grn' => $outbound->waybill->waybill_no,
                'quantity_transacted' => $outbound->quantity_supplied,
                'in' => '',
                'out' => $outbound->quantity_supplied,
                'balance' => 0, // initially set to zero
                'packaging' => $outbound->itemStock->item->package_type,
                'physical_quantity' => '',
                'sign' => '',
            ];
        }
        $outbounds2 = TransferRequestDispatchedProduct::join('item_stock_sub_batches', 'transfer_request_dispatched_products.item_stock_sub_batch_id', '=', 'item_stock_sub_batches.id')
            ->groupBy('transfer_request_waybill_id')
            ->where('supply_warehouse_id', $warehouse_id)
            ->where('item_stock_sub_batches.item_id', $item_id)
            ->where('transfer_request_dispatched_products.created_at', '>=', $date_from)
            ->where('transfer_request_dispatched_products.created_at', '<=', $date_to)
            ->select('transfer_request_dispatched_products.*', \DB::raw('SUM(quantity_supplied) as quantity_supplied'))->orderby('transfer_request_dispatched_products.created_at')->get();
        foreach ($outbounds2 as $outbound) {
            //$running_balance -= $outbound->quantity_supplied;
            $bincards[] = [
                'type' => 'out_bound',
                'date' => $outbound->created_at,
                'invoice_no' => $outbound->transferWaybillItem->invoice->request_number,
                'waybill_grn' => $outbound->transferWaybill->transfer_request_waybill_no,
                'quantity_transacted' => $outbound->quantity_supplied,
                'in' => '',
                'out' => $outbound->quantity_supplied,
                'balance' => 0, // initially set to zero
                'packaging' => $outbound->itemStock->item->package_type,
                'physical_quantity' => '',
                'sign' => '',
            ];
        }
        usort($bincards, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        $date_from_formatted = date('Y-m-d', strtotime($date_from));
        $date_to_formatted = date('Y-m-d', strtotime($date_to));
        return  response()->json(compact('bincards', 'brought_forward', 'date_from_formatted', 'date_to_formatted'), 200);
    }
}
