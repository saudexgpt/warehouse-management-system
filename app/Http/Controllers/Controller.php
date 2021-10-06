<?php

namespace App\Http\Controllers;

use App\ActivityLog;
use App\Customer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\UserResource;
use App\IssueTicket;
use App\Laravue\Models\Role;
use App\Laravue\Models\User;
use App\Models\Invoice\DispatchedProduct;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItemBatch;
use App\Models\Invoice\InvoiceStatus;
use App\Models\Invoice\WaybillItem;
use App\Models\Logistics\AutomobileEngineer;
use App\Models\Logistics\VehicleType;
use App\Models\Order\OrderStatus;
use App\Models\Setting\Currency;
use App\Models\Setting\CustomerType;
use App\Models\Setting\Setting;
use App\Models\Setting\Tax;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Warehouse\Warehouse;
use Notification;
use App\Notifications\AuditTrail;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $user;

    public function uploadFile(Request $request)
    {
        if ($request->file('avatar') != null && $request->file('avatar')->isValid()) {
            $mime = $request->file('avatar')->getClientMimeType();

            if ($mime == 'image/png' || $mime == 'image/jpeg' || $mime == 'image/jpg' || $mime == 'image/gif') {
                $name = time() . "." . $request->file('avatar')->guessClientExtension();
                $folder = "items";
                $avatar = $request->file('avatar')->storeAs($folder, $name, 'public');

                return response()->json(['avatar' => 'storage/' . $avatar], 200);
            }
        }
    }
    public function __construct()
    {
        // $this->middleware('guest')->except('logout');
        $this->checkForNegativeTransitProduct();
        $this->resetPartialInvoices();
    }
    public function resolveIncompleteSupplies()
    {
        $dispatch_products = DispatchedProduct::groupBy('waybill_item_id')->select('*',  \DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->where('created_at', '>=', '2021-05-01')->get();

        foreach ($dispatch_products as $dispatch_product) {
            if ((int) $dispatch_product->total_quantity_supplied < $dispatch_product->waybillItem->quantity) {

                $waybill_item = $dispatch_product->waybillItem;
                $item_id = $waybill_item->invoiceItem->item_id;
                $invoice_item = $waybill_item->invoiceItem;
                $balance = $dispatch_product->waybillItem->quantity - (int) $dispatch_product->total_quantity_supplied;
                echo $dispatch_product;
                // $stock = ItemStockSubBatch::where(['warehouse_id' => $dispatch_product->warehouse_id, 'item_id' => $item_id])->where('balance', '>=', $balance)->first();
                // if ($stock) {
                //     $invoice_item_batch = new InvoiceItemBatch();
                //     $invoice_item_batch->invoice_id = $invoice_item->invoice_id;
                //     $invoice_item_batch->invoice_item_id = $invoice_item->id;
                //     $invoice_item_batch->item_stock_sub_batch_id = $stock->id;
                //     $invoice_item_batch->to_supply = $balance;
                //     $invoice_item_batch->quantity = 0;
                //     $invoice_item_batch->save();

                //     if ($dispatch_product->status === 'on transit') {
                //         $stock->in_transit += $balance;
                //     } else {
                //         $stock->supplied += $balance;
                //     }
                //     $stock->balance -= $balance;
                //     $stock->save();

                //     $new_dispatch_product = new DispatchedProduct();
                //     $new_dispatch_product->warehouse_id = $dispatch_product->warehouse_id;
                //     $new_dispatch_product->item_stock_sub_batch_id = $stock->id;
                //     $new_dispatch_product->waybill_id = $waybill_item->waybill_id;
                //     $new_dispatch_product->waybill_item_id = $waybill_item->id;
                //     $new_dispatch_product->quantity_supplied = $balance;
                //     $new_dispatch_product->remitted = 1;
                //     $new_dispatch_product->instant_balance = $stock->balance;
                //     $new_dispatch_product->status = $dispatch_product->status;
                //     $new_dispatch_product->save();

                //     echo 'done';
                // } else {
                //     $stocks = ItemStockSubBatch::where(['warehouse_id' => $dispatch_product->warehouse_id, 'item_id' => $item_id])->whereRaw('balance - reserved_for_supply > 0')->get();
                //     $quantity = $balance;
                //     foreach ($stocks as $stock) {

                //         $real_balance = $stock->balance - $stock->reserved_for_supply;
                //         if ($quantity > 0) {
                //             # code...

                //             if ($quantity >= $real_balance) {
                //                 $invoice_item_batch = new InvoiceItemBatch();
                //                 $invoice_item_batch->invoice_id = $invoice_item->invoice_id;
                //                 $invoice_item_batch->invoice_item_id = $invoice_item->id;
                //                 $invoice_item_batch->item_stock_sub_batch_id = $stock->id;
                //                 $invoice_item_batch->to_supply = $real_balance;
                //                 $invoice_item_batch->quantity = 0;
                //                 $invoice_item_batch->save();

                //                 if ($dispatch_product->status === 'on transit') {
                //                     $stock->in_transit += $real_balance;
                //                 } else {
                //                     $stock->supplied += $real_balance;
                //                 }
                //                 $stock->balance -= $real_balance;
                //                 $stock->save();

                //                 $new_dispatch_product = new DispatchedProduct();
                //                 $new_dispatch_product->warehouse_id = $dispatch_product->warehouse_id;
                //                 $new_dispatch_product->item_stock_sub_batch_id = $stock->id;
                //                 $new_dispatch_product->waybill_id = $waybill_item->waybill_id;
                //                 $new_dispatch_product->waybill_item_id = $waybill_item->id;
                //                 $new_dispatch_product->quantity_supplied = $real_balance;
                //                 $new_dispatch_product->remitted = 1;
                //                 $new_dispatch_product->instant_balance = $stock->balance;
                //                 $new_dispatch_product->status = $dispatch_product->status;
                //                 $new_dispatch_product->save();

                //                 $quantity -= $real_balance;
                //             } else {
                //                 $invoice_item_batch = new InvoiceItemBatch();
                //                 $invoice_item_batch->invoice_id = $invoice_item->invoice_id;
                //                 $invoice_item_batch->invoice_item_id = $invoice_item->id;
                //                 $invoice_item_batch->item_stock_sub_batch_id = $stock->id;
                //                 $invoice_item_batch->to_supply = $quantity;
                //                 $invoice_item_batch->quantity = 0;
                //                 $invoice_item_batch->save();

                //                 if ($dispatch_product->status === 'on transit') {
                //                     $stock->in_transit += $quantity;
                //                 } else {
                //                     $stock->supplied += $quantity;
                //                 }
                //                 $stock->balance -= $quantity;
                //                 $stock->save();

                //                 $new_dispatch_product = new DispatchedProduct();
                //                 $new_dispatch_product->warehouse_id = $dispatch_product->warehouse_id;
                //                 $new_dispatch_product->item_stock_sub_batch_id = $stock->id;
                //                 $new_dispatch_product->waybill_id = $waybill_item->waybill_id;
                //                 $new_dispatch_product->waybill_item_id = $waybill_item->id;
                //                 $new_dispatch_product->quantity_supplied = $quantity;
                //                 $new_dispatch_product->remitted = 1;
                //                 $new_dispatch_product->instant_balance = $stock->balance;
                //                 $new_dispatch_product->status = $dispatch_product->status;
                //                 $new_dispatch_product->save();

                //                 $quantity -= $quantity;
                //             }
                //         }
                //     }
                // }
            }
        }
        // WaybillItem::chunk(200, function ($waybill_items) {
        //     foreach ($waybill_items as $waybill_item) {
        //         $dispatch_products = DispatchedProduct::groupBy('waybill_item_id')->where('waybill_item_id', $waybill_item->id)->select('*', \DB::raw('SUM(quantity_supplied) as total_quantity_supplied'))->get();
        //         print_r($dispatch_products);
        //     }
        // });
    }
    private function checkForNegativeTransitProduct()
    {

        $items_in_stock = ItemStockSubBatch::where('in_transit', '<', 0)->get();
        if ($items_in_stock->isNotEmpty()) {
            foreach ($items_in_stock as $item_in_stock) {
                $in_transit = $item_in_stock->in_transit;
                $item_in_stock->in_transit = 0;
                $item_in_stock->supplied += $in_transit;
                $item_in_stock->save();
            }
        }
    }
    private function resetPartialInvoices()
    {

        $invoices = Invoice::where(['full_waybill_generated' => '0', 'status' => 'delivered'])->get();
        if ($invoices->isNotEmpty()) {
            foreach ($invoices as $invoice) {
                $invoice->status = 'partially supplied';
                $invoice->save();
            }
        }
    }
    private function resetSuppliedInvoices()
    {

        $invoices = Invoice::where(['full_waybill_generated' => '1', 'status' => 'partially supplied'])->get();
        if ($invoices->isNotEmpty()) {
            foreach ($invoices as $invoice) {
                $incomplete_invoice_item = $invoice->invoiceItems()->whereIn('supply_status', ['Partial', 'Pending'])->get();
                if ($incomplete_invoice_item->isEmpty()) {
                    $invoice->status = 'delivered';
                    $invoice->save();
                }
            }
        }
    }

    public function setUser()
    {
        $this->user  = new UserResource(Auth::user());
    }

    public function getUser()
    {
        $this->setUser();

        return $this->user;
    }
    public function fetchCustomers()
    {
        $customers = Customer::with(['user', 'type'])->get();
        $customer_types = CustomerType::get();
        return response()->json(compact('customers', 'customer_types'), 200);
    }
    public function fetchNecessayParams()
    {
        $user = $this->getUser();
        $all_warehouses = Warehouse::with(['itemStocks.item.taxes', 'itemStocks.item.price', 'vehicles'])->where('enabled', 1)->get();
        if ($user->isAdmin() || $user->isAssistantAdmin()) {
            $warehouses = $all_warehouses;
        } else {
            $warehouses = $user->warehouses()->with(['itemStocks.item.taxes', 'itemStocks.item.price', 'vehicles'])->where('enabled', 1)->get();
        }
        $items = Item::with(['taxes', 'price'])->orderBy('name')->get();
        $currencies = Currency::get();
        $taxes = Tax::get();
        //$order_statuses = OrderStatus::get();
        $invoice_statuses = InvoiceStatus::get();
        $company_name = $this->settingValue('company_name');
        $company_contact = $this->settingValue('company_contact');
        $currency = $this->settingValue('currency');
        $product_expiry_date_alert = $this->settingValue('product_expiry_date_alert_in_months');
        $vehicle_types = VehicleType::get();
        $automobile_engineers = AutomobileEngineer::get();
        $engine_types = ['Diesel', 'Petrol', 'Kerosene'];
        $expense_types = ['Insurance', 'Maintenance / Repairs', 'Fuel'];
        $package_types = ['Bottles', 'Boxes', 'Bundles', 'Cartons', 'Clips', 'Packets', 'Pieces', 'Rolls', 'Tins'];
        $product_return_reasons = ['Product short-dated', 'Mass return - expired', 'Mass return - unexpired', 'Rep. resignation/sack - expired', 'Rep. resignation/sack - unexpired', 'Spillage', 'Others'];
        $teams = ['Allied', 'Bull', 'Confectionaries', 'Cosmestics', 'Eagle', 'Falcons', 'Funbact', 'Jaguar', 'Lion', 'REP', 'Stallion'];
        $dispatch_companies = ['GREENLIFE LOGISTICS', 'COURIER SERVICE', 'FOB (Free On Board)'];
        $all_roles = Role::orderBy('name')->select('name')->get();
        $default_roles = Role::where('role_type', 'default')->orderBy('name')->select('name')->get();

        return response()->json([
            'params' => compact('company_name', 'company_contact', 'all_warehouses', 'warehouses', 'items', 'currencies', 'taxes', /*'order_statuses',*/ 'invoice_statuses', 'currency', 'vehicle_types', 'engine_types', 'expense_types', 'package_types', 'automobile_engineers', 'all_roles', 'default_roles', 'product_return_reasons', 'product_expiry_date_alert', 'teams', 'dispatch_companies')
        ]);
    }
    public function settingValue($key)
    {
        return Setting::where('key', $key)->first()->value;
    }
    public function generalSettings()
    {
        $settings = Setting::get();
        return response()->json(compact('settings'), 200);
    }
    public function nextReceiptNo($key_prefix = 'invoice')
    {
        $prefix = $this->settingValue($key_prefix . '_number_prefix');
        $no_of_digits = $this->settingValue($key_prefix . '_number_digit');
        $next_no = $this->settingValue($key_prefix . '_number_next');

        $digit_of_next_no = strlen($next_no);
        $unused_digit = $no_of_digits - $digit_of_next_no;
        $zeros = '';
        for ($i = 1; $i <= $unused_digit; $i++) {
            $zeros .= '0';
        }

        $invoice_no = $prefix . $zeros . $next_no;
        return $invoice_no;
    }

    public function incrementReceiptNo($key_prefix = 'invoice')
    {
        $next_no = $this->settingValue($key_prefix . '_number_next');
        $setting = Setting::where('key', $key_prefix . '_number_next')->first();
        $setting->value = $next_no + 1;
        $setting->save();
        return $setting;
    }

    public function logUserActivity($title, $description, $roles = [])
    {
        // $user = $this->getUser();
        // if ($role) {
        //     $role->notify(new AuditTrail($title, $description));
        // }
        // return $user->notify(new AuditTrail($title, $description));
        // send notification to admin at all times
        $users = User::whereHas('roles', function ($query) {
            $query->where('name', '=', 'admin'); // this is the role id inside of this callback
        })->get();

        if (in_array('assistant admin', $roles)) {
            $assistant_admin = User::whereHas('roles', function ($query) {
                $query->where('name', '=', 'assistant admin'); // this is the role id inside of this callback
            })->get();
            $users = $users->merge($assistant_admin);
        }
        if (in_array('warehouse manager', $roles)) {
            $warehouse_managers = User::whereHas('roles', function ($query) {
                $query->where('name', '=', 'warehouse manager'); // this is the role id inside of this callback
            })->get();
            $users = $users->merge($warehouse_managers);
        }
        if (in_array('stock officer', $roles)) {
            $stock_officers = User::whereHas('roles', function ($query) {
                $query->where('name', '=', 'stock officer'); // this is the role id inside of this callback
            })->get();
            $users = $users->merge($stock_officers);
        }
        if (in_array('warehouse auditor', $roles)) {
            $auditors = User::whereHas('roles', function ($query) {
                $query->where('name', '=', 'warehouse auditor'); // this is the role id inside of this callback
            })->get();
            $users = $users->merge($auditors);
        }
        // var_dump($users);
        $notification = new AuditTrail($title, $description);
        return Notification::send($users->unique(), $notification);
        // $activity_log = new ActivityLog();
        // $activity_log->user_id = $user->id;
        // $activity_log->action = $action;
        // $activity_log->user_type = $user->roles[0]->name;
        // $activity_log->save();
    }
    public function getUniqueNo($prefix, $next_no)
    {
        $no_of_digits = 5;

        $digit_of_next_no = strlen($next_no);
        $unused_digit = $no_of_digits - $digit_of_next_no;
        $zeros = '';
        for ($i = 1; $i <= $unused_digit; $i++) {
            $zeros .= '0';
        }

        return $prefix . $zeros . $next_no;
    }
}
