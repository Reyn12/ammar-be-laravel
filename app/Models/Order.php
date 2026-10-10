<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'code',
    'shift_id',
    'table_id',
    'customer_id',
    'branch_id',
    'customer_name',
    'customer_phone',
    'customer_email',
    'order_type',
    'status',
    'payment_method',
    'payment_status',
    'total_amount',
    'tax_amount',
    'cash_received',
    'confirmed_by',
    'paid_at',
    'expires_at',
])]
class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->code ??= static::generateCode();
        });
    }

    /** Format AMR-YYYYMMDD-NNN, urutan reset tiap hari. */
    public static function generateCode(): string
    {
        $datePart = now()->format('Ymd');
        $sequence = static::where('code', 'like', "AMR-{$datePart}-%")->count() + 1;

        do {
            $code = sprintf('AMR-%s-%03d', $datePart, $sequence++);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
