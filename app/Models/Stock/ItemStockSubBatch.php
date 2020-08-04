<?php

namespace App\Models\Stock;

use App\Models\Invoice\DispatchedProduct;
use App\Models\Invoice\InvoiceItem;
use App\Models\Invoice\InvoiceItemBatch;
use App\Laravue\Models\User;
use App\Models\Warehouse\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ItemStockSubBatch extends Model
{
    //
    // protected static function booted()
    // {
    //     static::addGlobalScope('confirmed_by', function (Builder $builder) {
    //         $builder->where('confirmed_by', '!=', null);
    //     });
    // }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    // public function itemStock()
    // {
    //     return $this->belongsTo(ItemStock::class);
    // }
    public function stocker()
    {
        return $this->belongsTo(User::class, 'stocked_by', 'id');
    }
    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by', 'id');
    }
    public function fetchBalanceOfItemsInStock($warehouse_id, $item_id)
    {
        $initial_balance = 0;
        $item = ItemStockSubBatch::groupBy('item_id')->where(['warehouse_id' => $warehouse_id, 'item_id' => $item_id])
            ->select(\DB::raw('SUM(balance) as item_balance'))->first();

        if ($item) {
            return $item->item_balance;
        }
        return $initial_balance;
    }
    public function sendItemInStockForDelivery($waybill_items)
    {
        foreach ($waybill_items as $waybill_item) {

            $warehouse_id = $waybill_item->warehouse_id;
            $waybill_quantity = $waybill_item->quantity;
            $invoice_item_id = $waybill_item->invoice_item_id;
            $invoice_item_batches = InvoiceItemBatch::with('itemStockBatch.itemStock')->where('invoice_item_id', $invoice_item_id)->where('quantity', '>', '0')->get();
            // $items_in_stock= ItemStock::where(['warehouse_id' => $warehouse_id, 'item_id' => $waybill_item->item_id])
            //     ->where('balance', '>', '0')->orderBy('id')->get();
            // $item_stock_sub_batches = ItemStockSubBatch::with('itemStock')->where(['warehouse_id' => $warehouse_id, 'item_id' => $waybill_item->item_id])
            //     ->where('balance', '>', '0')->orderBy('id')->get();
            if ($invoice_item_batches->count() > 0) {
                foreach ($invoice_item_batches as $invoice_item_batch) :

                    $for_supply = $invoice_item_batch->quantity;

                    if ($waybill_quantity <= $for_supply) {
                        $invoice_item_batch->quantity -= $waybill_quantity;
                        $invoice_item_batch->save();

                        $invoice_item_batch->itemStockBatch->reserved_for_supply -= $waybill_quantity;
                        $invoice_item_batch->itemStockBatch->in_transit += $waybill_quantity;
                        $invoice_item_batch->itemStockBatch->balance -=  $waybill_quantity;

                        if ($invoice_item_batch->itemStockBatch->save()) {
                            //// also update item_stocks table/////////
                            // $invoice_item_batch->itemStockBatch->itemStock->in_transit +=  $waybill_quantity;
                            // $invoice_item_batch->itemStockBatch->itemStock->balance -=  $waybill_quantity;
                            // $invoice_item_batch->itemStockBatch->itemStock->save();

                            $dispatched_product = new DispatchedProduct();
                            $dispatched_product->warehouse_id = $warehouse_id;
                            $dispatched_product->item_stock_sub_batch_id = $invoice_item_batch->itemStockBatch->id;
                            $dispatched_product->waybill_id = $waybill_item->waybill_id;
                            $dispatched_product->quantity_supplied = $waybill_quantity;
                            $dispatched_product->status = 'on transit';
                            $dispatched_product->save();
                        }
                        $quantity = 0; //we have sent all items for delivery
                        break;
                    } else {
                        $invoice_item_batch->quantity -= $for_supply;
                        $invoice_item_batch->save();

                        $invoice_item_batch->itemStockBatch->reserved_for_supply -= $for_supply;
                        $invoice_item_batch->itemStockBatch->in_transit += $for_supply;
                        $invoice_item_batch->itemStockBatch->balance -=  $for_supply;

                        if ($invoice_item_batch->itemStockBatch->save()) {
                            //// also update item_stocks table/////////
                            // $invoice_item_batch->itemStockBatch->itemStock->in_transit +=  $for_supply;
                            // $invoice_item_batch->itemStockBatch->itemStock->balance -=  $for_supply;
                            // $invoice_item_batch->itemStockBatch->itemStock->save();

                            $dispatched_product = new DispatchedProduct();
                            $dispatched_product->warehouse_id = $warehouse_id;
                            $dispatched_product->item_stock_sub_batch_id =
                                $invoice_item_batch->itemStockBatch->id;
                            $dispatched_product->waybill_id = $waybill_item->waybill_id;
                            $dispatched_product->quantity_supplied = $for_supply;
                            $dispatched_product->status = 'on transit';
                            $dispatched_product->save();
                        }

                        $waybill_quantity -= $for_supply;
                    }
                endforeach;
            }
        }
    }
    public function confirmItemInStockAsSupplied($dispatch_products)
    {
        foreach ($dispatch_products as $dispatch_product) {

            $dispatch_product->status = 'delivered';
            $dispatch_product->save();
            $item_stock_sub_batch = ItemStockSubBatch::with('itemStock')->find($dispatch_product->item_stock_sub_batch_id);

            $item_stock_sub_batch->in_transit -= $dispatch_product->quantity_supplied;
            $item_stock_sub_batch->supplied += $dispatch_product->quantity_supplied;
            $item_stock_sub_batch->save();
        }
    }
}
