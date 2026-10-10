<?php

use App\Models\Branch;
use App\Models\Order;
use App\Models\StaffEvent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // satu koneksi singkat supaya tes cepat
    config()->set('staff_stream', ['duration' => 0.3, 'interval_ms' => 50, 'heartbeat' => 0.1]);

    $this->branch = Branch::factory()->create();
    $this->kasir = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']);
});

function streamBody(array $headers = []): string
{
    return test()->get(route('v1.stream.orders'), $headers)
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8')
        ->streamedContent();
}

test('stream replays events after Last-Event-ID for the own branch only', function () {
    $mine = Order::factory()->create(['branch_id' => $this->branch->id, 'customer_name' => 'Budi']);
    $other = Order::factory()->create();

    $first = StaffEvent::emit(StaffEvent::ORDER_CREATED, $mine);
    $second = StaffEvent::emit(StaffEvent::ORDER_PAID, $mine);
    StaffEvent::emit(StaffEvent::ORDER_PAID, $other);

    Sanctum::actingAs($this->kasir);

    $body = streamBody(['Last-Event-ID' => (string) ($first->id - 1)]);

    expect($body)->toContain("retry: 3000")
        ->toContain("id: {$first->id}\nevent: order.created")
        ->toContain("id: {$second->id}\nevent: order.paid")
        ->toContain('"customer_name":"Budi"')
        ->not->toContain('"order_id":'.$other->id);
});

test('a fresh connection does not replay old events but sends heartbeats', function () {
    StaffEvent::emit(StaffEvent::ORDER_PAID, Order::factory()->create(['branch_id' => $this->branch->id]));

    Sanctum::actingAs($this->kasir);

    $body = streamBody();

    expect($body)->not->toContain('event: order.paid')->toContain(': ping');
});

test('stream requires staff authentication', function () {
    $this->getJson(route('v1.stream.orders'))->assertUnauthorized();
});

test('old events can be pruned', function () {
    $order = Order::factory()->create(['branch_id' => $this->branch->id]);
    $old = StaffEvent::emit(StaffEvent::ORDER_PAID, $order);
    $old->forceFill(['created_at' => now()->subDays(2)])->save();
    StaffEvent::emit(StaffEvent::ORDER_PAID, $order);

    $this->artisan('staff-events:prune')->assertSuccessful();

    expect(StaffEvent::count())->toBe(1);
});
