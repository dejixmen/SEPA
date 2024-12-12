<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SepaMandate extends Model
{
    protected $fillable = [
        'reference',
        'customer_name',
        'customer_email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'country',
        'iban',
        'bic',
        'amount',
        'currency',
        'stripe_mandate_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'status',
        'payment_status',
        'last_payment_date',
        'last_payment_id',
        'signed_date',
        'is_recurring',
        'billing_day',
        'next_payment_date',
    ];

    protected $casts = [
        'signed_date' => 'date',
        'last_payment_date' => 'datetime',
        'next_payment_date' => 'date',
        'amount' => 'decimal:2',
        'is_recurring' => 'boolean',
        'billing_day' => 'integer',
    ];
} 