<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'start_time', 'end_time', 'status', 'starting_cash', 'expected_cash', 'actual_cash'])]
class Shift extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    /**
     * Tambahkan hitungan transaksi dan total tunai (hanya pesanan yang sudah lunas).
     * Hasil: cash_order_count, qris_order_count, cash_total.
     */
    public function scopeWithTotals(Builder $query): void
    {
        $paid = fn (string $method) => fn ($orders) => $orders
            ->where('payment_method', $method)
            ->where('payment_status', 'paid');

        $query->withCount([
            'orders as cash_order_count' => $paid('cash'),
            'orders as qris_order_count' => $paid('qris'),
        ])->withSum(['orders as cash_total' => $paid('cash')], 'total_amount');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
