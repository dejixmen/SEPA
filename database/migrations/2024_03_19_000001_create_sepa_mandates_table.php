<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sepa_mandates', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('phone')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('iban');
            $table->string('bic')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('EUR');
            $table->string('stripe_mandate_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_payment_method_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('not_charged');
            $table->dateTime('last_payment_date')->nullable();
            $table->string('last_payment_id')->nullable();
            $table->date('signed_date');
            $table->boolean('is_recurring')->default(true);
            $table->integer('billing_day')->nullable();
            $table->date('next_payment_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sepa_mandates');
    }
}; 