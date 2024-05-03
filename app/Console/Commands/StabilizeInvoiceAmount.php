<?php

namespace App\Console\Commands;

use App\Models\Invoice\InvoiceItem;
use App\Models\Invoice\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Console\Command;

class StabilizeInvoiceAmount extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:stabilize-amount';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command stabilizes invoice total amount from invoice items.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    private function stabilize()
    {
        InvoiceItem::groupBy('invoice_id')
            ->where('supply_status', 'Pending')
            ->select('*', \DB::raw('SUM(amount) as total_amount'))->chunk(200, function ($invoice_items) {
                foreach ($invoice_items as $invoice_item) {
                    $invoice = Invoice::find($invoice_item->invoice_id);
                    if ($invoice) {

                        $invoice->subtotal = $invoice_item->total_amount;
                        $invoice->amount = $invoice_item->total_amount;
                        $invoice->save();
                    }
                }
            });
    }
    public function handle()
    {
        //
        $this->stabilize();
    }
}
