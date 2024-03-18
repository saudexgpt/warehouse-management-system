<?php

namespace App\Console\Commands;

use App\Models\Invoice\InvoiceItem;
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
        InvoiceItem::with('invoice')
            ->groupBy('invoice_id')
            ->where('supply_status', 'Pending')
            ->select('*', \DB::raw('SUM(amount) as total_amount'))->chunkById(200, function (Collection $invoice_items) {
                foreach ($invoice_items as $invoice_item) {
                    $invoice = $invoice_item->invoice;
                    $invoice->subtotal = $invoice_item->total_amount;
                    $invoice->amount = $invoice_item->total_amount;
                    $invoice->save();
                }
            }, $column = 'id');
    }
    public function handle()
    {
        //
        $this->stabilize();
    }
}
