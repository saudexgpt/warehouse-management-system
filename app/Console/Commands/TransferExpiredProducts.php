<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Laravue\Models\User;
use App\Models\Stock\ExpiredProduct;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Transfers\TransferRequestDispatchedProduct;

class TransFerExpiredProducts extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expired:product-transfer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command transfers all expired products from a warehouse stock';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    private function transferExpiredProduct($expired_item_in_stock)
    {
        $expired_product = new ExpiredProduct();
        $expired_product->warehouse_id = $expired_item_in_stock->warehouse_id;
        $expired_product->item_id = $expired_item_in_stock->item_id;
        $expired_product->batch_no = $expired_item_in_stock->batch_no;
        $expired_product->item_stock_sub_batch_id = $expired_item_in_stock->id;
        $expired_product->quantity = $expired_item_in_stock->balance;
        $expired_product->destroyed = 0;
        $expired_product->balance = $expired_item_in_stock->balance;
        $expired_product->expiry_date = $expired_item_in_stock->expiry_date;
        if ($expired_product->save()) {
            return true;
        }
        return false;
    }
    private function checkForExpiredProduct()
    {

        // $minimum_quantity_threshold = $item->minimum_quantity_threshold;
        $today = date('Y-m-d', strtotime('now'));
        $items_in_stock = ItemStockSubBatch::where('expiry_date', '<=', $today)->where('balance', '>', 0)->get();
        if ($items_in_stock->isNotEmpty()) {
            foreach ($items_in_stock as $item_in_stock) {
                if ($this->transferExpiredProduct($item_in_stock)) {
                    // make the balance zero
                    $balance = $item_in_stock->balance;
                    $item_in_stock->expired = $balance;
                    $item_in_stock->balance = 0;
                    $item_in_stock->save();
                }
            }
        }
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
    private function returnExpiredProducts()
    {
        set_time_limit(0);
        $dispatchedProducts = TransferRequestDispatchedProduct::whereRaw('transfer_request_waybill_item_id IN (378,393,395,396,462,468,469,470,471,472,473,516,517,521,529,530,533)')->get();
        if ($dispatchedProducts->isNotEmpty()) {
            foreach ($dispatchedProducts as $dispatchedProduct) {
                $item_in_stock = $dispatchedProduct->itemStock;
                $item_in_stock->balance += $dispatchedProduct->quantity_supplied;
                $item_in_stock->supplied -= $dispatchedProduct->quantity_supplied;
                $item_in_stock->save();

                // $dispatchedProduct->transferWaybill()->delete();
                // $waybill_item = $dispatchedProduct->transferWaybillItem;
                // $waybill_item->invoice()->delete();
                // $invoice_item = $waybill_item->invoiceItem;
                // $invoice_item->batches()->delete();
                // $invoice_item->delete();
                // $waybill_item->delete();
                $dispatchedProduct->delete();
            }
        }
    }
    public function handle()
    {
        //
        // $this->returnExpiredProducts();
        $this->checkForExpiredProduct();
        $this->checkForNegativeTransitProduct();
    }
}
