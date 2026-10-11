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

    /**
     * Format A{YYMMDD}-{NNN}, mis. A261011-021. Urutan reset tiap hari dan 3 angka belakangnya
     * adalah nomor antrean yang dipanggil dapur. Kode format lama (AMR-YYYYMMDD-NNN) di hari yang
     * sama ikut dihitung supaya nomor antrean tidak ganda.
     */
    public static function generateCode(): string
    {
        $today = now();
        $prefix = 'A'.$today->format('ymd').'-';

        $lastSequence = static::where('code', 'like', $prefix.'%')
            ->orWhere('code', 'like', 'AMR-'.$today->format('Ymd').'-%')
            ->pluck('code')
            ->map(fn (string $code) => (int) static::queueNumberOf($code))
            ->max() ?? 0;

        $sequence = $lastSequence + 1;

        do {
            $code = sprintf('%s%03d', $prefix, $sequence++);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Nomor antrean = urutan di belakang kode ("A261011-021" -> "021"). */
    public static function queueNumberOf(?string $code): string
    {
        return $code === null ? '' : substr($code, (int) strrpos($code, '-') + 1);
    }

    public function queueNumber(): string
    {
        return static::queueNumberOf($this->code);
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
        return $this->belongsTo(User::class, 'confirmed_by')->withTrashed();
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
        return $this->belongsTo(Table::class)->withTrashed();
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
