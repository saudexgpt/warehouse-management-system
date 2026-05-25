<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_movements', function (Blueprint $table) {

            $table->id();

            $table->foreignId('warehouse_id')
                ->constrained();

            $table->foreignId('item_id')
                ->constrained();

            $table->foreignId('item_stock_sub_batch_id')
                ->nullable()
                ->constrained();

            /**
             * Related Documents
             */
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained();

            $table->foreignId('invoice_item_id')
                ->nullable()
                ->constrained();

            $table->foreignId('waybill_id')
                ->nullable()
                ->constrained();

            $table->foreignId('waybill_item_id')
                ->nullable()
                ->constrained();

            /**
             * Movement Details
             */
            $table->enum('movement_type', [
                'stock_in',
                'dispatch',
                'return',
                'transfer_in',
                'transfer_out',
                'adjustment',
                'damage',
                'expired',
                'reserved',
                'released',
            ]);

            /**
             * Quantities
             */
            $table->decimal('quantity', 20, 2);

            $table->decimal('balance_before', 20, 2);

            $table->decimal('balance_after', 20, 2);

            /**
             * Batch Information
             */
            $table->string('batch_no')->nullable();

            $table->date('expiry_date')->nullable();

            /**
             * Metadata
             */
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users');

            $table->timestamps();

            /**
             * Indexes
             */
            $table->index([
                'warehouse_id',
                'item_id',
                'movement_type'
            ]);

            $table->index([
                'item_stock_sub_batch_id'
            ]);

            $table->index([
                'waybill_id'
            ]);

            $table->index([
                'invoice_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_movements');
    }
};
