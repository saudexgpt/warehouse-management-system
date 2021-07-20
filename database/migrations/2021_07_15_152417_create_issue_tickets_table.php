<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIssueTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('issue_tickets', function (Blueprint $table) {
            $table->id();
            $table->integer('raised_by');
            $table->string('details');
            $table->string('table_name');
            $table->integer('table_id');
            $table->enum('status', ['pending', 'solved'])->default('pending');
            $table->integer('solved_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('issue_tickets');
    }
}
