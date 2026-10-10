<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'midtrans_order_id',
    'transaction_id',
    'payment_type',
    'qr_url',
    'gross_amount',
    'transaction_status',
    'paid_at',
    'expires_at',
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
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
