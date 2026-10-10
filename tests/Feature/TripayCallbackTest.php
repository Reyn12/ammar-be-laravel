<?php

use App\Models\Order;
use App\Models\Payment;

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

    $this->order = Order::factory()->create([
        'payment_method' => 'qris',
        'payment_status' => 'unpaid',
        'total_amount' => 50000,
        'expires_at' => now()->addMinutes(15),
    ]);
    $this->payment = Payment::create([
        'order_id' => $this->order->id,
        'merchant_ref' => $this->order->code,
        'reference' => 'REF-1',
        'amount' => 50000,
        'status' => 'unpaid',
        'created_at' => now(),
    ]);

    $this->callback = function (array $body, ?string $signature = null, string $event = 'payment_status') {
        $raw = json_encode($body);
        $signature ??= hash_hmac('sha256', $raw, 'private-key');

        return $this->call('POST', route('v1.payments.tripay.callback'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => $signature,
            'HTTP_X_CALLBACK_EVENT' => $event,
            'HTTP_ACCEPT' => 'application/json',
        ], $raw);
    };
});

test('valid paid callback marks payment and order as paid', function () {
    ($this->callback)(['merchant_ref' => $this->order->code, 'status' => 'PAID', 'amount_received' => 49000])
        ->assertSuccessful()
        ->assertJson(['success' => true]);

    expect($this->payment->refresh()->status)->toBe('paid')
        ->and($this->payment->amount_received)->toBe(49000)
        ->and($this->order->refresh()->payment_status)->toBe('paid')
        ->and($this->order->paid_at)->not->toBeNull();
});

test('callback with invalid signature is rejected', function () {
    ($this->callback)(['merchant_ref' => $this->order->code, 'status' => 'PAID'], 'salah')
        ->assertForbidden();

    expect($this->order->refresh()->payment_status)->toBe('unpaid');
});

test('expired callback cancels the order', function () {
    ($this->callback)(['merchant_ref' => $this->order->code, 'status' => 'EXPIRED'])->assertSuccessful();

    expect($this->order->refresh())
        ->payment_status->toBe('expired')
        ->status->toBe('cancelled');
});

test('paid payment is not downgraded by a late expired callback', function () {
    ($this->callback)(['merchant_ref' => $this->order->code, 'status' => 'PAID'])->assertSuccessful();
    ($this->callback)(['merchant_ref' => $this->order->code, 'status' => 'EXPIRED'])->assertSuccessful();

    expect($this->order->refresh()->payment_status)->toBe('paid');
});

test('callback with unknown event or payment is rejected', function () {
    ($this->callback)(['merchant_ref' => $this->order->code, 'status' => 'PAID'], null, 'other_event')->assertStatus(400);
    ($this->callback)(['merchant_ref' => 'AMR-X', 'status' => 'PAID'])->assertNotFound();
});
