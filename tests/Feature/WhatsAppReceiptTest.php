<?php

use App\Actions\Notification\BuildOrderReceiptMessage;
use App\Models\Branch;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\Payment;
use App\Models\Table;
use App\Services\Fonnte\FonnteClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.tripay', [
        'base_url' => 'https://tripay.test/api',
        'merchant_code' => 'T0001',
        'api_key' => 'api-key',
        'private_key' => 'private-key',
        'payment_method' => 'QRIS',
        'expiry_minutes' => 15,
        'return_url' => null,
    ]);
    config()->set('services.fonnte', ['base_url' => 'https://fonnte.test', 'token' => 'fonnte-token']);

    $branch = Branch::factory()->create(['name' => 'RM Ayam Bakar Ammar']);
    $table = Table::factory()->create(['branch_id' => $branch->id, 'table_number' => 5]);

    $this->order = Order::factory()->create([
        'branch_id' => $branch->id,
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'payment_method' => 'qris',
        'payment_status' => 'unpaid',
        'customer_name' => 'Budi',
        'customer_phone' => '081234567890',
        'total_amount' => 68000,
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => $this->order->id,
        'product_name' => 'Ayam Bakar',
        'qty' => 2,
        'price' => 28000,
        'notes' => 'jangan pedas',
    ]);
    OrderItemAddon::factory()->create(['order_item_id' => $item->id, 'name' => 'Lv 2', 'price' => 3000]);
    OrderItemAddon::factory()->create(['order_item_id' => $item->id, 'name' => 'Extra Sambal', 'price' => 3000]);

    $this->payment = Payment::create([
        'order_id' => $this->order->id,
        'merchant_ref' => $this->order->code,
        'reference' => 'REF-1',
        'amount' => 68000,
        'status' => 'unpaid',
        'created_at' => now(),
    ]);

    $this->paidCallback = function (string $status = 'PAID') {
        $raw = json_encode(['merchant_ref' => $this->order->code, 'status' => $status]);

        return $this->call('POST', route('v1.payments.tripay.callback'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => hash_hmac('sha256', $raw, 'private-key'),
            'HTTP_X_CALLBACK_EVENT' => 'payment_status',
            'HTTP_ACCEPT' => 'application/json',
        ], $raw);
    };
});

test('paid callback sends the receipt to whatsapp through fonnte', function () {
    Http::fake(['fonnte.test/send' => Http::response(['status' => true, 'id' => ['1'], 'process' => 'pending'])]);

    ($this->paidCallback)()->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'https://fonnte.test/send'
        && $request->hasHeader('Authorization', 'fonnte-token')
        && $request['target'] === '6281234567890'
        && str_contains($request['message'], $this->order->code)
        && str_contains($request['message'], 'Rp68.000'));

    $this->assertDatabaseHas('notifications', [
        'order_id' => $this->order->id,
        'channel' => 'whatsapp',
        'recipient' => '081234567890',
        'status' => 'sent',
    ]);
});

test('receipt is sent only once even if tripay repeats the callback', function () {
    Http::fake(['fonnte.test/send' => Http::response(['status' => true])]);

    ($this->paidCallback)();
    ($this->paidCallback)();

    expect(Notification::count())->toBe(1);
    Http::assertSentCount(1);
});

test('failed fonnte request is recorded as failed and can be retried', function () {
    Http::fake(['fonnte.test/send' => Http::sequence()
        ->push(['status' => false, 'reason' => 'device disconnected'])
        ->push(['status' => true]),
    ]);

    ($this->paidCallback)()->assertSuccessful(); // pembayaran tetap sukses walau WA gagal

    expect($this->order->refresh()->payment_status)->toBe('paid');
    $notification = Notification::first();
    expect($notification->status)->toBe('failed')->and($notification->retry_count)->toBe(1);

    $this->artisan('notifications:retry-failed')->assertSuccessful();

    expect($notification->refresh()->status)->toBe('sent');
});

test('nothing is sent when fonnte is not configured or payment is not paid', function () {
    Http::fake();
    config()->set('services.fonnte.token', null);

    ($this->paidCallback)()->assertSuccessful();
    expect(Notification::count())->toBe(0);

    config()->set('services.fonnte.token', 'fonnte-token');
    ($this->paidCallback)('EXPIRED');
    Http::assertNothingSent();
});

test('receipt message lists items, options, notes and total', function () {
    $message = app(BuildOrderReceiptMessage::class)->handle($this->order);

    expect($message)
        ->toContain('Halo Budi')
        ->toContain('*RM Ayam Bakar Ammar*')
        ->toContain($this->order->code)
        ->toContain('Makan di Tempat · Meja 5')
        ->toContain('• 2x Ayam Bakar (Lv 2, Extra Sambal)')
        ->toContain('Catatan: jangan pedas')
        ->toContain('Rp68.000')
        ->toContain('QRIS (Lunas)');
});

test('phone numbers are normalized to the 62 format', function (string $input) {
    expect(app(FonnteClient::class)->normalizePhone($input))->toBe('6281234567890');
})->with(['08123 4567 890', '+6281234567890', '6281234567890', '0812-3456-7890']);
