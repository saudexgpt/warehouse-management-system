<?php

namespace App\Console\Commands;

use App\Models\Invoice\DispatchedProduct;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Transfers\TransferRequestDispatchedProduct;
use Illuminate\Console\Command;

class StabilizeStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:stabilize-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function doStabilizeStock()
    {

        ItemStockSubBatch::chunkById(200, function ($items_in_stock) {
            foreach ($items_in_stock as $item_in_stock) {
                $supplied1 = DispatchedProduct::where('item_stock_sub_batch_id', $item_in_stock->id)
                    ->sum('quantity_supplied');
                $supplied2 = TransferRequestDispatchedProduct::where('item_stock_sub_batch_id', $item_in_stock->id)
                    ->sum('quantity_supplied');
                $item_in_stock->in_transit = 0;
                $item_in_stock->supplied = $supplied1 + $supplied2;
                $item_in_stock->total_sold = $supplied1;
                $item_in_stock->total_transferred = $supplied2;
                $item_in_stock->total_out = $supplied1 + $supplied2;
                $item_in_stock->save();
            }
        }, $column = 'id');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->doStabilizeStock();
    }
}
