<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('code');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2);
            $table->decimal('finnal_amount', 15, 2);
            $table->string('payment_method');
            $table->decimal('shipping', 15, 2);
            $table->string('o_name');
            $table->string('o_address');
            $table->string('o_phone');
            $table->string('o_mail');
            $table->integer('stt_track');
            $table->integer('stt_payment');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
}
