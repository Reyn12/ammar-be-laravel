<?php

use App\Models\Branch;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->kasir = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier', 'name' => 'Rani']);
    $this->owner = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'owner']);
});

test('there is no active shift until the cashier opens one', function () {
    Sanctum::actingAs($this->kasir);

    $this->getJson(route('v1.shifts.active'))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null);

    $this->postJson(route('v1.shifts.open'), ['starting_cash' => 150000])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.starting_cash', 150000)
        ->assertJsonPath('data.user_name', 'Rani')
        ->assertJsonPath('data.expected_cash', 150000);

    $this->getJson(route('v1.shifts.active'))->assertJsonPath('data.status', 'active');
});

test('a cashier cannot open two shifts at once', function () {
    Sanctum::actingAs($this->kasir);
    $this->postJson(route('v1.shifts.open'), ['starting_cash' => 0])->assertCreated();

    $this->postJson(route('v1.shifts.open'), ['starting_cash' => 0])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Kamu masih punya shift yang aktif.');
});

test('expected cash is the starting cash plus paid cash orders of the shift', function () {
    Sanctum::actingAs($this->kasir);
    $shiftId = $this->postJson(route('v1.shifts.open'), ['starting_cash' => 100000])->json('data.id');

    Order::factory()->create(['branch_id' => $this->branch->id, 'shift_id' => $shiftId, 'payment_method' => 'cash', 'payment_status' => 'paid', 'total_amount' => 40000]);
    Order::factory()->create(['branch_id' => $this->branch->id, 'shift_id' => $shiftId, 'payment_method' => 'cash', 'payment_status' => 'paid', 'total_amount' => 25000]);
    Order::factory()->create(['branch_id' => $this->branch->id, 'shift_id' => $shiftId, 'payment_method' => 'qris', 'payment_status' => 'paid', 'total_amount' => 70000]);
    Order::factory()->create(['branch_id' => $this->branch->id, 'shift_id' => $shiftId, 'payment_method' => 'cash', 'payment_status' => 'unpaid', 'total_amount' => 99000]);

    $this->getJson(route('v1.shifts.active'))
        ->assertJsonPath('data.expected_cash', 165000)
        ->assertJsonPath('data.cash_order_count', 2)
        ->assertJsonPath('data.qris_order_count', 1);

    $this->postJson(route('v1.shifts.close', $shiftId), ['actual_cash' => 160000])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.expected_cash', 165000)
        ->assertJsonPath('data.actual_cash', 160000)
        ->assertJsonPath('data.difference', -5000);

    $this->getJson(route('v1.shifts.active'))->assertJsonPath('data', null);
    $this->postJson(route('v1.shifts.close', $shiftId), ['actual_cash' => 1])->assertUnprocessable();
});

test('a cashier cannot close someone elses shift', function () {
    $other = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']);
    $shift = Shift::create(['user_id' => $other->id, 'start_time' => now(), 'status' => 'active', 'starting_cash' => 0]);

    Sanctum::actingAs($this->kasir);

    $this->postJson(route('v1.shifts.close', $shift), ['actual_cash' => 0])->assertNotFound();
});

test('owner lists shifts and can force close an active one', function () {
    $shift = Shift::create(['user_id' => $this->kasir->id, 'start_time' => now(), 'status' => 'active', 'starting_cash' => 50000]);
    $foreign = Shift::create(['user_id' => User::factory()->create(['role' => 'cashier'])->id, 'start_time' => now(), 'status' => 'active', 'starting_cash' => 0]);

    Sanctum::actingAs($this->kasir);
    $this->getJson(route('v1.shifts.index'))->assertForbidden();

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->owner);

    $ids = collect($this->getJson(route('v1.shifts.index'))->assertSuccessful()->json('data'))->pluck('id');
    expect($ids)->toContain($shift->id)->not->toContain($foreign->id);

    $this->postJson(route('v1.shifts.force-close', $shift), ['actual_cash' => 50000])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.difference', 0);

    $this->postJson(route('v1.shifts.force-close', $foreign), ['actual_cash' => 0])->assertNotFound();
});
