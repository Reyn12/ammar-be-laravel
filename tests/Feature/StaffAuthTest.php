<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->kasir = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'cashier',
        'username' => 'kasir1',
        'password' => 'rahasia123',
    ]);
});

function loginAs(string $username = 'kasir1', string $password = 'rahasia123')
{
    return test()->postJson(route('v1.auth.login'), compact('username', 'password'));
}

test('staff can log in and receives a token with the envelope format', function () {
    loginAs()
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.username', 'kasir1')
        ->assertJsonPath('data.user.role', 'cashier')
        ->assertJsonPath('data.user.roles.0.name', 'cashier')
        ->assertJsonMissingPath('data.user.password')
        ->assertJsonStructure(['data' => ['access_token', 'expires_in']]);
});

test('wrong credentials return the same generic error', function () {
    loginAs('kasir1', 'salah')
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.username.0', 'Username atau password salah.');

    loginAs('tidak-ada', 'rahasia123')
        ->assertUnprocessable()
        ->assertJsonPath('errors.username.0', 'Username atau password salah.');
});

test('inactive staff cannot log in', function () {
    $this->kasir->update(['is_active' => false]);

    loginAs()->assertForbidden()->assertJsonPath('success', false);
});

test('token authenticates me and logout revokes it', function () {
    $token = loginAs()->json('data.access_token');
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->getJson(route('v1.auth.me'), $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.username', 'kasir1');

    $this->postJson(route('v1.auth.logout'), [], $headers)->assertSuccessful();

    expect($this->kasir->tokens()->count())->toBe(0);
});

test('protected endpoints return the envelope error when not logged in', function () {
    $this->getJson(route('v1.auth.me'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Sesi berakhir, silakan masuk lagi.');
});

test('change password validates the old password and keeps the current device logged in', function () {
    $token = loginAs()->json('data.access_token');
    loginAs(); // perangkat lain
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->postJson(route('v1.auth.change-password'), ['current_password' => 'salah', 'new_password' => 'baru12345'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.current_password.0', 'Password lama tidak sesuai.');

    $this->postJson(route('v1.auth.change-password'), ['current_password' => 'rahasia123', 'new_password' => 'rahasia123'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('new_password');

    $this->postJson(route('v1.auth.change-password'), ['current_password' => 'rahasia123', 'new_password' => 'baru12345'], $headers)
        ->assertSuccessful();

    expect($this->kasir->tokens()->count())->toBe(1);
    loginAs('kasir1', 'baru12345')->assertSuccessful();
});

test('role middleware only lets the allowed roles through', function () {
    Route::middleware(['auth:sanctum', 'role:owner'])->get('/api/v1/_owner-only', fn () => ['ok' => true]);

    $kasirToken = loginAs()->json('data.access_token');
    $owner = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'owner', 'username' => 'owner1', 'password' => 'rahasia123']);
    $ownerToken = loginAs('owner1')->json('data.access_token');

    $this->getJson('/api/v1/_owner-only', ['Authorization' => "Bearer {$kasirToken}"])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    // guard menyimpan user dari request sebelumnya selama satu test
    app('auth')->forgetGuards();

    $this->getJson('/api/v1/_owner-only', ['Authorization' => "Bearer {$ownerToken}"])->assertSuccessful();
});

test('unknown api resources return a friendly not found envelope', function () {
    $this->getJson(route('v1.orders.show', 'AMR-00000000-000'))
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Data tidak ditemukan.');
});
