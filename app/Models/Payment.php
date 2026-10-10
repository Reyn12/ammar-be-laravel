<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'merchant_ref',
    'reference',
    'method',
    'qr_url',
    'qr_string',
    'checkout_url',
    'amount',
    'fee_amount',
    'amount_received',
    'status',
    'paid_at',
    'expires_at',
    'callback_payload',
])]
class Payment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'callback_payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
