<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\Firebase\FirebaseTokenVerifier;
use App\Services\Firebase\InvalidFirebaseTokenException;
use Tests\Support\FirebaseTestToken;

function bearer(array $claims = [], ?string $otherKey = null): array
{
    return ['Authorization' => 'Bearer '.FirebaseTestToken::make($claims, $otherKey)];
}

test('verifier accepts a valid token and normalizes the email', function () {
    $profile = app(FirebaseTokenVerifier::class)->verify(FirebaseTestToken::make());

    expect($profile)->toMatchArray([
        'uid' => 'firebase-uid-1',
        'email' => 'budi@example.com',
        'name' => 'Budi Santoso',
    ]);
});

test('verifier rejects bad tokens', function (array $claims, ?string $otherKey) {
    expect(fn () => app(FirebaseTokenVerifier::class)->verify(FirebaseTestToken::make($claims, $otherKey)))
        ->toThrow(InvalidFirebaseTokenException::class);
})->with([
    'wrong audience' => [['aud' => 'project-lain'], null],
    'wrong issuer' => [['iss' => 'https://evil.example.com'], null],
    'expired' => [['exp' => time() - 60], null],
    'unverified email' => [['email_verified' => false], null],
    'missing email' => [['email' => null], null],
    'signed with another key' => [[], 'other'],
]);

test('me endpoint creates the customer from the google account', function () {
    $this->getJson(route('v1.me.show'), bearer())
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'budi@example.com')
        ->assertJsonPath('data.name', 'Budi Santoso');

    $this->assertDatabaseHas('customers', ['firebase_uid' => 'firebase-uid-1', 'email' => 'budi@example.com']);

    // login kedua tidak membuat duplikat
    $this->getJson(route('v1.me.show'), bearer())->assertSuccessful();
    expect(Customer::count())->toBe(1);
});

test('me endpoints require a valid token', function () {
    $this->getJson(route('v1.me.show'))->assertUnauthorized();
    $this->getJson(route('v1.me.orders'))->assertUnauthorized();
    $this->getJson(route('v1.me.show'), bearer(['aud' => 'lain']))->assertUnauthorized();
});

test('order history contains own orders and guest orders with the same email', function () {
    $this->getJson(route('v1.me.show'), bearer()); // buat customer
    $me = Customer::first();
    $other = Customer::factory()->create();

    $mine = Order::factory()->create(['customer_id' => $me->id]);
    $guestSameEmail = Order::factory()->create(['customer_id' => null, 'customer_email' => 'BUDI@example.com']);
    $someoneElse = Order::factory()->create(['customer_id' => $other->id, 'customer_email' => 'lain@example.com']);
    $guestOther = Order::factory()->create(['customer_id' => null, 'customer_email' => 'orang@example.com']);

    $codes = collect($this->getJson(route('v1.me.orders'), bearer())
        ->assertSuccessful()
        ->json('data'))->pluck('code');

    expect($codes)->toContain($mine->code, $guestSameEmail->code)
        ->not->toContain($someoneElse->code, $guestOther->code);
});

test('orders placed while logged in are linked to the customer', function () {
    config()->set('services.tripay.merchant_code', null);

    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id, 'category_id' => Category::factory()]);

    $payload = [
        'branch_id' => $branch->id,
        'order_type' => 'takeaway',
        'payment_method' => 'cash',
        'customer' => ['name' => 'Budi', 'phone' => '081234567890'],
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ];

    $this->postJson(route('v1.orders.store'), $payload, bearer())->assertCreated();
    expect(Order::first()->customer_id)->toBe(Customer::first()->id);

    // tamu tetap bisa memesan tanpa token
    $this->postJson(route('v1.orders.store'), $payload)->assertCreated();
    expect(Order::latest('id')->first()->customer_id)->toBeNull();

    // tetapi token yang salah ditolak, bukan dianggap tamu
    $this->postJson(route('v1.orders.store'), $payload, bearer(['aud' => 'lain']))->assertUnauthorized();
});
