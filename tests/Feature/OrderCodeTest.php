<?php

use App\Actions\Kitchen\AssignOrderToBatches;
use App\Actions\Notification\BuildOrderReceiptMessage;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(10, 0));
});

test('order codes use the short A{YYMMDD}-{NNN} format and count up within a day', function () {
    $first = Order::factory()->create(['branch_id' => $this->branch->id]);
    $second = Order::factory()->create(['branch_id' => $this->branch->id]);

    expect($first->code)->toBe('A261011-001')
        ->and($second->code)->toBe('A261011-002')
        ->and($second->queueNumber())->toBe('002');
});

test('the sequence restarts every day', function () {
    Order::factory()->count(2)->create(['branch_id' => $this->branch->id]);

    $this->travelTo(now()->addDay());

    expect(Order::factory()->create(['branch_id' => $this->branch->id])->code)->toBe('A261012-001');
});

test('old format orders of the same day keep the queue number unique', function () {
    Order::factory()->create(['branch_id' => $this->branch->id, 'code' => 'AMR-20261011-007']);

    $next = Order::factory()->create(['branch_id' => $this->branch->id]);

    expect($next->code)->toBe('A261011-008');
});

test('queue number is the three digits after the last dash for both formats', function () {
    expect(Order::queueNumberOf('A261011-021'))->toBe('021')
        ->and(Order::queueNumberOf('AMR-20261011-021'))->toBe('021')
        ->and(Order::queueNumberOf(null))->toBe('');
});

test('both the new and old code formats can be looked up by customers', function () {
    $new = Order::factory()->create(['branch_id' => $this->branch->id]);
    $old = Order::factory()->create(['branch_id' => $this->branch->id, 'code' => 'AMR-20261010-004']);

    $this->getJson(route('v1.orders.show', $new->code))
        ->assertSuccessful()
        ->assertJsonPath('data.code', 'A261011-001')
        ->assertJsonPath('data.queue_number', '001');

    $this->getJson(route('v1.orders.show', $old->code))
        ->assertSuccessful()
        ->assertJsonPath('data.queue_number', '004');
});

test('staff orders and kitchen batches expose the queue number', function () {
    $kitchen = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'kitchen']);
    $product = Product::factory()->create(['branch_id' => $this->branch->id]);
    $order = Order::factory()->create(['branch_id' => $this->branch->id, 'payment_status' => 'paid', 'payment_method' => 'cash']);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'batch_id' => null]);
    app(AssignOrderToBatches::class)->handle($order->refresh());

    Sanctum::actingAs($kitchen);

    $this->getJson(route('v1.kitchen.orders'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.queue_number', '001');

    $this->getJson(route('v1.kitchen.batches'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.tables.0.queue_number', '001')
        ->assertJsonPath('data.0.tables.0.order_code', 'A261011-001');
});

test('the receipt shows the full code and the queue number', function () {
    $order = Order::factory()->create(['branch_id' => $this->branch->id, 'customer_name' => 'Budi']);

    $message = app(BuildOrderReceiptMessage::class)->handle($order);

    expect($message)->toContain('Kode pesanan: *A261011-001*')
        ->and($message)->toContain('Nomor antrean: *001*');
});
