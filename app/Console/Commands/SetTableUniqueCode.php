<?php

namespace App\Console\Commands;

use App\Customer;
use App\Models\Invoice\DispatchedProduct;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\WaybillItem;
use App\Models\Stock\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetTableUniqueCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:set-table-unique-code';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private function updateCustomerCode()
    {
        Customer::where('code', null)
            ->chunk(200, function ($customers) {
                foreach ($customers as $customer) {
                    $customer_id = $customer->id;
                    $digit_of_next_no = strlen($customer_id);
                    $unused_digit = 6 - $digit_of_next_no;
                    $zeros = '';
                    for ($i = 1; $i <= $unused_digit; $i++) {
                        $zeros .= '0';
                    }

                    $code = 'GSR' . $zeros . $customer_id;
                    $customer->code = $code;
                    $customer->save();
                }
            });
    }
    private function updateProductCode()
    {
        Item::where('code', null)
            ->chunk(200, function ($items) {
                foreach ($items as $item) {
                    $item_id = $item->id;
                    $digit_of_next_no = strlen($item_id);
                    $unused_digit = 5 - $digit_of_next_no;
                    $zeros = '';
                    for ($i = 1; $i <= $unused_digit; $i++) {
                        $zeros .= '0';
                    }

                    $code = 'PDT' . $zeros . $item_id;
                    $item->code = $code;
                    $item->save();
                }
            });
    }
    public function updateRepsTable()
    {
        $reps = Customer::join('users', 'users.id', 'customers.user_id')
            ->where('users.email', 'NOT LIKE', 'default' . '%')
            ->where('customers.type', 'reps')
            ->select('customers.id as customer_id', 'users.id as user_id', 'customers.code', 'users.name', 'users.email')
            ->get();
        foreach ($reps as $rep) {

            $user = DB::table('reps')->where('email', $rep->email)->first();
            if (!$user) {
                DB::table('reps')->insert([
                    'codes' => addSingleElementToString('', $rep->code, ','),
                    'name' => $rep->name,
                    'email' => strtolower($rep->email),
                    'user_id' => $rep->user_id,
                    'customer_id' => $rep->customer_id,
                ]);
            } else {

                DB::table('reps')->where('email', $rep->email)->update(['codes' => addSingleElementToString($user->codes, $rep->code, ',')]);
            }
        }
    }
    public function createDispatchIdForDispatchedProducts()
    {
        DispatchedProduct::where('dispatch_id', null)
            ->chunk(200, function ($items) {
                foreach ($items as $item) {
                    $code = $item->invoice_id . '-' . $item->waybill_id . '-' . $item->waybill_item_id . '-' . $item->item_stock_sub_batch_id . '-' . $item->quantity_supplied;

                    $item->dispatch_id = $code;
                    $item->save();
                }
            });
    }
    public function setWaybilledInvoices()
    {
        Invoice::with('waybillItems')->where(
            'waybill_generated',
            0
        )
            ->chunkById(200, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $waybillItems = $invoice->waybillItems;
                    if ($waybillItems->count() > 0) {

                        $invoice->waybill_generated = 1;
                        $invoice->save();
                    }
                }

            }, $column = 'id');
    }
    /**
     * Execute the console command.
     *
     *
     */
    public function handle()
    {
        $this->updateCustomerCode();
        $this->updateProductCode();
        $this->updateRepsTable();
        $this->createDispatchIdForDispatchedProducts();
        $this->setWaybilledInvoices();
    }
}
