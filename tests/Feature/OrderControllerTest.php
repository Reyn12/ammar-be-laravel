<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Config;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;
use App\Models\Table;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.tripay', [
        'base_url' => 'https://tripay.test/api',
        'merchant_code' => 'T0001',
        'api_key' => 'api-key',
        'private_key' => 'private-key',
        'payment_method' => 'QRIS',
        'expiry_minutes' => 15,
        'return_url' => 'https://app.test/payment',
    ]);

    $this->branch = Branch::factory()->create();
    $this->category = Category::factory()->create();
    $this->table = Table::factory()->create(['branch_id' => $this->branch->id, 'table_number' => 7]);

    $this->ayam = Product::factory()->create([
        'branch_id' => $this->branch->id,
        'category_id' => $this->category->id,
        'name' => 'Ayam Bakar',
        'price' => 28000,
    ]);
    $this->pedas = ProductAddonGroup::factory()->create([
        'product_id' => $this->ayam->id,
        'name' => 'Level Pedas',
        'is_required' => true,
        'min_qty' => 1,
        'max_qty' => 1,
    ]);
    $this->lv0 = ProductAddon::factory()->create(['group_id' => $this->pedas->id, 'name' => 'Lv 0', 'price' => 0]);
    $this->lv2 = ProductAddon::factory()->create(['group_id' => $this->pedas->id, 'name' => 'Lv 2', 'price' => 3000]);

    $this->extra = ProductAddonGroup::factory()->create([
        'product_id' => $this->ayam->id,
        'name' => 'Tambahan',
        'is_required' => false,
        'min_qty' => 0,
        'max_qty' => 2,
    ]);
    $this->sambal = ProductAddon::factory()->create(['group_id' => $this->extra->id, 'name' => 'Extra Sambal', 'price' => 3000]);

    $this->payload = function (array $override = []) {
        $payload = array_replace_recursive([
            'branch_id' => $this->branch->id,
            'order_type' => 'dine_in',
            'table_token' => $this->table->qr_token,
            'payment_method' => 'cash',
            'customer' => ['name' => 'Budi', 'phone' => '0812-3456-7890', 'email' => null],
            'items' => [[
                'product_id' => $this->ayam->id,
                'qty' => 2,
                'notes' => 'jangan pedas',
                'addon_ids' => [$this->lv2->id, $this->sambal->id],
            ]],
        ], $override);

        // daftar item diganti utuh, bukan digabung per elemen
        if (isset($override['items'])) {
            $payload['items'] = $override['items'];
        }

        return $payload;
    };
});

test('cash order is created with server side prices', function () {
    $this->postJson(route('v1.orders.store'), ($this->payload)())
        ->assertCreated()
        ->assertJsonPath('data.payment_method', 'cash')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.table_number', 7)
        // (28000 + 3000 + 3000) x 2
        ->assertJsonPath('data.total_amount', 68000)
        ->assertJsonPath('data.items.0.notes', 'jangan pedas')
        ->assertJsonCount(2, 'data.items.0.addons')
        ->assertJsonPath('data.payment', null)
        ->assertJsonMissingPath('data.customer_phone');

    $order = Order::first();
    expect($order->code)->toMatch('/^A\d{6}-001$/')
        ->and($order->customer_phone)->toBe('081234567890')
        ->and($order->expires_at)->toBeNull();
});

test('takeaway order does not need a table', function () {
    $payload = ($this->payload)(['order_type' => 'takeaway']);
    unset($payload['table_token']);

    $this->postJson(route('v1.orders.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.table_number', null);
});

test('dine in order requires a valid table token', function () {
    $this->postJson(route('v1.orders.store'), ($this->payload)(['table_token' => 'salah']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('table_token');

    $this->table->update(['is_active' => false]);

    $this->postJson(route('v1.orders.store'), ($this->payload)())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('table_token');
});

test('tax from branch config is added to the total', function () {
    Config::create(['branch_id' => $this->branch->id, 'key' => 'tax', 'value' => '10']);

    $this->postJson(route('v1.orders.store'), ($this->payload)())
        ->assertCreated()
        ->assertJsonPath('data.tax_amount', 6800)
        ->assertJsonPath('data.total_amount', 74800);
});

test('required variant must be chosen and addons must belong to the product', function () {
    $this->postJson(route('v1.orders.store'), ($this->payload)(['items' => [['product_id' => $this->ayam->id, 'qty' => 1, 'addon_ids' => []]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.addon_ids');

    $foreign = ProductAddon::factory()->create();

    $this->postJson(route('v1.orders.store'), ($this->payload)(['items' => [['product_id' => $this->ayam->id, 'qty' => 1, 'addon_ids' => [$this->lv0->id, $foreign->id]]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.addon_ids');
});

test('unavailable products and products from another branch are rejected', function () {
    $this->ayam->update(['is_available' => false]);

    $this->postJson(route('v1.orders.store'), ($this->payload)())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.product_id');

    $other = Product::factory()->create(['category_id' => $this->category->id]);

    $this->postJson(route('v1.orders.store'), ($this->payload)(['items' => [['product_id' => $other->id, 'qty' => 1, 'addon_ids' => []]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.product_id');
});

test('customer data is validated', function () {
    $this->postJson(route('v1.orders.store'), ($this->payload)(['customer' => ['phone' => '12345', 'email' => 'bukan-email']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer.phone', 'customer.email']);
});

test('same product can appear twice with different options', function () {
    $this->postJson(route('v1.orders.store'), ($this->payload)(['items' => [
        ['product_id' => $this->ayam->id, 'qty' => 1, 'addon_ids' => [$this->lv0->id]],
        ['product_id' => $this->ayam->id, 'qty' => 1, 'addon_ids' => [$this->lv2->id]],
    ]]))
        ->assertCreated()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.total_amount', 59000);
});

test('qris order creates a tripay transaction', function () {
    Http::fake([
        'tripay.test/api/transaction/create' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'T0001REF',
                'payment_method' => 'QRIS',
                'qr_url' => 'https://tripay.test/qr.png',
                'qr_string' => '000201...',
                'checkout_url' => 'https://tripay.test/checkout/T0001REF',
                'fee_customer' => 0,
                'expired_time' => now()->addMinutes(15)->timestamp,
            ],
        ]),
    ]);

    $this->postJson(route('v1.orders.store'), ($this->payload)(['payment_method' => 'qris']))
        ->assertCreated()
        ->assertJsonPath('data.payment_method', 'qris')
        ->assertJsonPath('data.payment.qr_url', 'https://tripay.test/qr.png')
        ->assertJsonPath('data.payment.total_to_pay', 68000)
        ->assertJsonPath('data.payment.status', 'unpaid');

    Http::assertSent(function ($request) {
        $order = Order::first();
        $expected = hash_hmac('sha256', 'T0001'.$order->code.'68000', 'private-key');

        return $request->url() === 'https://tripay.test/api/transaction/create'
            && $request['merchant_ref'] === $order->code
            && $request['signature'] === $expected
            && $request['amount'] == 68000
            && $request->hasHeader('Authorization', 'Bearer api-key');
    });

    $this->assertDatabaseHas('payments', ['reference' => 'T0001REF', 'status' => 'unpaid', 'amount' => 68000]);
});

test('failed tripay request rolls back the order', function () {
    Http::fake(['tripay.test/*' => Http::response(['success' => false, 'message' => 'Channel tidak aktif'], 400)]);

    $this->postJson(route('v1.orders.store'), ($this->payload)(['payment_method' => 'qris']))
        ->assertStatus(502)
        ->assertJsonPath('message', 'Channel tidak aktif');

    expect(Order::count())->toBe(0);
});

test('qris is unavailable when tripay is not configured', function () {
    config()->set('services.tripay.api_key', null);

    $this->postJson(route('v1.orders.store'), ($this->payload)(['payment_method' => 'qris']))
        ->assertStatus(503);

    expect(Order::count())->toBe(0);
});

test('order detail is found by code and hides personal data', function () {
    $this->postJson(route('v1.orders.store'), ($this->payload)());
    $order = Order::first();

    $this->getJson(route('v1.orders.show', $order->code))
        ->assertSuccessful()
        ->assertJsonPath('data.code', $order->code)
        ->assertJsonMissingPath('data.customer_name');

    $this->getJson(route('v1.orders.show', 'AMR-00000000-000'))->assertNotFound();
});

test('overdue qris order is expired when opened', function () {
    Http::fake(['tripay.test/api/transaction/create' => Http::response([
        'success' => true,
        'data' => ['reference' => 'REF1', 'qr_url' => 'x', 'expired_time' => now()->addMinutes(15)->timestamp],
    ])]);

    $this->postJson(route('v1.orders.store'), ($this->payload)(['payment_method' => 'qris']));
    $order = Order::first();
    $order->update(['expires_at' => now()->subMinute()]);

    Http::fake(['tripay.test/api/transaction/detail*' => Http::response(['success' => true, 'data' => ['status' => 'UNPAID']])]);

    $this->getJson(route('v1.orders.show', $order->code))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.payment_status', 'expired')
        ->assertJsonPath('data.payment.qr_url', null);
});

test('opening a qris order picks up the paid status from tripay', function () {
    Http::fake(['tripay.test/api/transaction/create' => Http::response([
        'success' => true,
        'data' => ['reference' => 'REF2', 'qr_url' => 'x', 'expired_time' => now()->addMinutes(15)->timestamp],
    ])]);

    $this->postJson(route('v1.orders.store'), ($this->payload)(['payment_method' => 'qris']));
    $order = Order::first();

    Http::fake(['tripay.test/api/transaction/detail*' => Http::response([
        'success' => true,
        'data' => ['status' => 'PAID', 'amount_received' => 68000],
    ])]);

    $this->getJson(route('v1.orders.show', $order->code))
        ->assertSuccessful()
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment.status', 'paid');
});

test('table token endpoint returns the table number', function () {
    $this->getJson(route('v1.tables.show', $this->table->qr_token))
        ->assertSuccessful()
        ->assertJsonPath('data.table_number', 7);

    $this->getJson(route('v1.tables.show', 'tidak-ada'))->assertNotFound();
});
