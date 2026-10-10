<?php

use App\Models\Branch;
use App\Models\Order;
use App\Models\Shift;
use App\Models\Table;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->owner = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'owner', 'username' => 'owner1']);
    Sanctum::actingAs($this->owner);
});

// ---------------------------------------------------------------- akun staf

test('owner lists cashiers and kitchen staff separately without passwords', function () {
    User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier', 'name' => 'Rani']);
    User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'kitchen', 'name' => 'Udin']);
    User::factory()->create(['role' => 'cashier', 'name' => 'Cabang Lain']);

    $cashiers = $this->getJson(route('v1.staff.users.index', ['role' => 'cashier']))
        ->assertSuccessful()
        ->assertJsonMissingPath('data.0.password')
        ->json('data');

    expect(collect($cashiers)->pluck('name')->all())->toBe(['Rani']);
    expect($this->getJson(route('v1.staff.users.index', ['role' => 'kitchen']))->json('data.0.name'))->toBe('Udin');
    $this->getJson(route('v1.staff.users.index', ['role' => 'owner']))->assertUnprocessable();
});

test('owner creates a cashier who can then log in', function () {
    $this->postJson(route('v1.staff.users.store'), [
        'username' => 'kasir.baru', 'name' => 'Kasir Baru', 'password' => 'rahasia123', 'role' => 'cashier',
    ])
        ->assertCreated()
        ->assertJsonPath('data.role', 'cashier')
        ->assertJsonPath('data.is_active', true);

    app('auth')->forgetGuards();
    $this->postJson(route('v1.auth.login'), ['username' => 'kasir.baru', 'password' => 'rahasia123'])->assertSuccessful();
});

test('creating accounts validates username, password and role', function () {
    User::factory()->create(['username' => 'dipakai']);

    $this->postJson(route('v1.staff.users.store'), ['username' => 'dipakai', 'name' => 'A', 'password' => '123456', 'role' => 'cashier'])
        ->assertUnprocessable()->assertJsonPath('errors.username.0', 'Username sudah dipakai.');

    $this->postJson(route('v1.staff.users.store'), ['username' => 'baru ada spasi', 'name' => 'Ab', 'password' => '123', 'role' => 'owner'])
        ->assertUnprocessable()->assertJsonValidationErrors(['username', 'password', 'role']);
});

test('updating an account can rename, deactivate and reset the password', function () {
    $kasir = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier', 'username' => 'kasir1', 'password' => 'lama123']);
    $kasir->createToken('hp');

    $this->putJson(route('v1.staff.users.update', $kasir), ['name' => 'Rani Baru', 'password' => null])->assertSuccessful()->assertJsonPath('data.name', 'Rani Baru');
    expect($kasir->tokens()->count())->toBe(1); // tanpa ganti password, sesi tetap

    $this->putJson(route('v1.staff.users.update', $kasir), ['password' => 'baru12345'])->assertSuccessful();
    expect($kasir->tokens()->count())->toBe(0); // password diganti, sesi lama berakhir

    $kasir->createToken('hp');
    $this->putJson(route('v1.staff.users.update', $kasir), ['is_active' => false])->assertSuccessful()->assertJsonPath('data.is_active', false);
    expect($kasir->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();
    $this->postJson(route('v1.auth.login'), ['username' => 'kasir1', 'password' => 'baru12345'])->assertForbidden();
});

test('owner accounts and other branches cannot be managed from the staff endpoints', function () {
    $otherOwner = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'owner']);
    $foreign = User::factory()->create(['role' => 'cashier']);

    $this->putJson(route('v1.staff.users.update', $otherOwner), ['name' => 'x'])->assertForbidden();
    $this->deleteJson(route('v1.staff.users.destroy', $this->owner))->assertForbidden();
    $this->putJson(route('v1.staff.users.update', $foreign), ['name' => 'x'])->assertNotFound();
    $this->deleteJson(route('v1.staff.users.destroy', $foreign))->assertNotFound();
});

test('deleting an account archives it, but not while a shift is active', function () {
    $kasir = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']);
    $shift = Shift::create(['user_id' => $kasir->id, 'start_time' => now(), 'status' => 'active', 'starting_cash' => 0]);

    $this->deleteJson(route('v1.staff.users.destroy', $kasir))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Akun ini masih punya shift aktif. Tutup shift-nya dulu.');

    $shift->update(['status' => 'closed']);
    $this->deleteJson(route('v1.staff.users.destroy', $kasir))->assertSuccessful();

    expect(User::find($kasir->id))->toBeNull()
        ->and(User::withTrashed()->find($kasir->id))->not->toBeNull()
        ->and($shift->refresh()->user->id)->toBe($kasir->id); // riwayat shift masih tahu siapa kasirnya
});

test('only the owner can manage accounts', function () {
    app('auth')->forgetGuards();
    Sanctum::actingAs(User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']));

    $this->getJson(route('v1.staff.users.index'))->assertForbidden();
    $this->postJson(route('v1.staff.users.store'), [])->assertForbidden();
});

// ---------------------------------------------------------------- meja

test('owner manages tables and receives the qr url for each', function () {
    config()->set('app.customer_url', 'https://web.test');

    $created = $this->postJson(route('v1.staff.tables.store'), ['table_number' => 12])
        ->assertCreated()
        ->assertJsonPath('data.table_number', 12)
        ->assertJsonPath('data.is_active', true)
        ->json('data');

    expect($created['qr_url'])->toBe("https://web.test/m/12?t={$created['qr_token']}");

    $this->getJson(route('v1.staff.tables.index'))->assertJsonCount(1, 'data');
    $this->putJson(route('v1.staff.tables.update', $created['id']), ['is_active' => false])->assertJsonPath('data.is_active', false);
    $this->putJson(route('v1.staff.tables.update', $created['id']), ['table_number' => 13])->assertJsonPath('data.table_number', 13);
});

test('duplicate table numbers are rejected', function () {
    Table::factory()->create(['branch_id' => $this->branch->id, 'table_number' => 5]);
    $other = Table::factory()->create(['branch_id' => $this->branch->id, 'table_number' => 6]);

    $this->postJson(route('v1.staff.tables.store'), ['table_number' => 5])->assertUnprocessable()->assertJsonValidationErrors('table_number');
    $this->putJson(route('v1.staff.tables.update', $other), ['table_number' => 5])->assertUnprocessable();
    $this->postJson(route('v1.staff.tables.store'), ['table_number' => 0])->assertUnprocessable();
});

test('regenerating the qr invalidates the old token for customers', function () {
    $table = Table::factory()->create(['branch_id' => $this->branch->id, 'table_number' => 3]);
    $old = $table->qr_token;

    $this->getJson(route('v1.tables.show', $old))->assertSuccessful();

    $new = $this->postJson(route('v1.staff.tables.regenerate-qr', $table))->assertSuccessful()->json('data.qr_token');

    expect($new)->not->toBe($old);
    $this->getJson(route('v1.tables.show', $old))->assertNotFound();
    $this->getJson(route('v1.tables.show', $new))->assertSuccessful()->assertJsonPath('data.table_number', 3);
});

test('deleting a table archives it and orders keep their table number', function () {
    $table = Table::factory()->create(['branch_id' => $this->branch->id, 'table_number' => 9]);
    $order = Order::factory()->create(['branch_id' => $this->branch->id, 'table_id' => $table->id, 'order_type' => 'dine_in', 'payment_status' => 'paid']);

    $this->deleteJson(route('v1.staff.tables.destroy', $table))->assertSuccessful();

    expect(Table::find($table->id))->toBeNull()
        ->and($order->refresh()->table_id)->toBe($table->id)
        ->and($order->table->table_number)->toBe(9);
    $this->getJson(route('v1.tables.show', $table->qr_token))->assertNotFound();
    $this->getJson(route('v1.staff.tables.index'))->assertJsonCount(0, 'data');
});

test('recreating an archived table number restores it with a fresh qr', function () {
    $table = Table::factory()->create(['branch_id' => $this->branch->id, 'table_number' => 4]);
    $oldToken = $table->qr_token;
    $table->delete();

    $this->postJson(route('v1.staff.tables.store'), ['table_number' => 4])
        ->assertCreated()
        ->assertJsonPath('data.id', $table->id)
        ->assertJsonPath('data.is_active', true);

    expect($table->refresh()->qr_token)->not->toBe($oldToken)->and(Table::count())->toBe(1);
});

test('staff table routes do not collide with the public token route', function () {
    $table = Table::factory()->create(['branch_id' => $this->branch->id, 'table_number' => 2]);

    $this->getJson(route('v1.tables.show', $table->qr_token))->assertSuccessful();
    $this->putJson(route('v1.staff.tables.update', $table), ['is_active' => true])->assertSuccessful();
});

test('only the owner can manage tables', function () {
    app('auth')->forgetGuards();
    Sanctum::actingAs(User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']));

    $this->getJson(route('v1.staff.tables.index'))->assertForbidden();
});
