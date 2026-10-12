<?php

use App\Models\Branch;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\StaffEvent;
use App\Models\User;
use App\Services\Firebase\StaffPushNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->cashier = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']);
    $this->kitchen = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'kitchen']);
    $this->owner = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'owner']);
});

/** Service account palsu dengan RSA key sungguhan supaya JWT bisa ditandatangani. */
function fakeFirebaseCredentials(): void
{
    openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048]), $privateKey);

    $path = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, json_encode([
        'project_id' => 'ammar-test',
        'client_email' => 'push@ammar-test.iam.gserviceaccount.com',
        'private_key' => $privateKey,
    ]));

    config()->set('services.firebase.credentials', $path);
    Cache::forget('firebase.fcm_access_token');
    test()->afterEach(fn () => @unlink($path));
}

function fakeFcm(): void
{
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.fake', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/ammar-test/messages/1']),
    ]);
}

function fcmRecipients(): array
{
    return Http::recorded()
        ->filter(fn ($pair) => str_contains($pair[0]->url(), 'fcm.googleapis.com'))
        ->map(fn ($pair) => $pair[0]->data()['message']['token'])
        ->values()
        ->all();
}

function emitFor(string $type, string $method, string $status = 'unpaid'): StaffEvent
{
    $order = Order::factory()->create([
        'branch_id' => test()->branch->id,
        'order_type' => 'takeaway',
        'payment_method' => $method,
        'payment_status' => $status,
        'customer_name' => 'Budi',
    ]);

    return StaffEvent::emit($type, $order);
}

test('staff can register a device token and re-registering moves it to the current user', function () {
    Sanctum::actingAs($this->cashier);
    $this->postJson(route('v1.devices.store'), ['token' => 'tablet-1'])
        ->assertCreated()
        ->assertJsonPath('success', true);

    Sanctum::actingAs($this->kitchen);
    $this->postJson(route('v1.devices.store'), ['token' => 'tablet-1', 'platform' => 'android'])->assertCreated();

    expect(DeviceToken::where('token', 'tablet-1')->count())->toBe(1)
        ->and(DeviceToken::first()->user_id)->toBe($this->kitchen->id);
});

test('device token requires authentication and a token value', function () {
    $this->postJson(route('v1.devices.store'), ['token' => 'x'])->assertUnauthorized();

    Sanctum::actingAs($this->cashier);
    $this->postJson(route('v1.devices.store'), [])->assertUnprocessable()->assertJsonValidationErrors('token');
});

test('staff can remove only their own device token', function () {
    DeviceToken::factory()->create(['user_id' => $this->cashier->id, 'token' => 'mine']);
    DeviceToken::factory()->create(['user_id' => $this->kitchen->id, 'token' => 'theirs']);

    Sanctum::actingAs($this->cashier);
    $this->deleteJson(route('v1.devices.destroy'), ['token' => 'mine'])->assertSuccessful();
    $this->deleteJson(route('v1.devices.destroy'), ['token' => 'theirs'])->assertSuccessful();

    expect(DeviceToken::pluck('token')->all())->toBe(['theirs']);
});

test('new cash order pushes to cashier and owner only', function () {
    fakeFirebaseCredentials();
    fakeFcm();
    DeviceToken::factory()->create(['user_id' => $this->cashier->id, 'token' => 'cashier-tablet']);
    DeviceToken::factory()->create(['user_id' => $this->kitchen->id, 'token' => 'kitchen-tablet']);
    DeviceToken::factory()->create(['user_id' => $this->owner->id, 'token' => 'owner-phone']);

    $event = emitFor(StaffEvent::ORDER_CREATED, 'cash');
    app(StaffPushNotifier::class)->notify($event);

    expect(fcmRecipients())->toEqualCanonicalizing(['cashier-tablet', 'owner-phone']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'projects/ammar-test/messages:send')
        && $request['message']['notification']['title'] === 'Pesanan tunai baru'
        && $request['message']['notification']['body'] === 'Takeaway · Budi'
        && $request['message']['android']['notification']['channel_id'] === 'order_masuk_v2'
        && $request['message']['android']['notification']['sound'] === 'notif_order_masuk'
        && $request['message']['data']['order_id'] === (string) $event->order_id);
});

test('paid qris order pushes to cashier, owner and kitchen', function () {
    fakeFirebaseCredentials();
    fakeFcm();
    DeviceToken::factory()->create(['user_id' => $this->cashier->id, 'token' => 'cashier-tablet']);
    DeviceToken::factory()->create(['user_id' => $this->kitchen->id, 'token' => 'kitchen-tablet']);
    DeviceToken::factory()->create(['user_id' => $this->owner->id, 'token' => 'owner-phone']);

    app(StaffPushNotifier::class)
        ->notify(emitFor(StaffEvent::ORDER_PAID, 'qris', 'paid'));

    expect(fcmRecipients())->toEqualCanonicalizing(['cashier-tablet', 'kitchen-tablet', 'owner-phone']);
});

test('paid cash order pushes to kitchen only', function () {
    fakeFirebaseCredentials();
    fakeFcm();
    DeviceToken::factory()->create(['user_id' => $this->cashier->id, 'token' => 'cashier-tablet']);
    DeviceToken::factory()->create(['user_id' => $this->kitchen->id, 'token' => 'kitchen-tablet']);

    app(StaffPushNotifier::class)
        ->notify(emitFor(StaffEvent::ORDER_PAID, 'cash', 'paid'));

    expect(fcmRecipients())->toBe(['kitchen-tablet']);
});

test('ready order pushes to cashier and owner only', function () {
    fakeFirebaseCredentials();
    fakeFcm();
    DeviceToken::factory()->create(['user_id' => $this->cashier->id, 'token' => 'cashier-tablet']);
    DeviceToken::factory()->create(['user_id' => $this->kitchen->id, 'token' => 'kitchen-tablet']);
    DeviceToken::factory()->create(['user_id' => $this->owner->id, 'token' => 'owner-phone']);

    app(StaffPushNotifier::class)
        ->notify(emitFor(StaffEvent::ORDER_READY, 'cash', 'paid'));

    expect(fcmRecipients())->toEqualCanonicalizing(['cashier-tablet', 'owner-phone']);
});

test('events that need no attention send nothing', function () {
    fakeFirebaseCredentials();
    fakeFcm();
    DeviceToken::factory()->create(['user_id' => $this->cashier->id]);

    $notifier = app(StaffPushNotifier::class);
    $notifier->notify(emitFor(StaffEvent::ORDER_CREATED, 'qris'));
    $notifier->notify(emitFor(StaffEvent::ORDER_CANCELLED, 'cash'));
    $notifier->notify(emitFor(StaffEvent::KITCHEN_UPDATED, 'cash', 'paid'));

    Http::assertNothingSent();
});

test('devices of other branches and inactive users are skipped', function () {
    fakeFirebaseCredentials();
    fakeFcm();
    $otherBranchCashier = User::factory()->create(['branch_id' => Branch::factory()->create()->id, 'role' => 'cashier']);
    $inactiveCashier = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier', 'is_active' => false]);
    DeviceToken::factory()->create(['user_id' => $otherBranchCashier->id, 'token' => 'other-branch']);
    DeviceToken::factory()->create(['user_id' => $inactiveCashier->id, 'token' => 'inactive']);
    DeviceToken::factory()->create(['user_id' => $this->cashier->id, 'token' => 'ok']);

    app(StaffPushNotifier::class)
        ->notify(emitFor(StaffEvent::ORDER_CREATED, 'cash'));

    expect(fcmRecipients())->toBe(['ok']);
});

test('a token rejected by FCM as unregistered is deleted', function () {
    fakeFirebaseCredentials();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.fake']),
        'fcm.googleapis.com/*' => Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND']], 404),
    ]);
    DeviceToken::factory()->create(['user_id' => $this->cashier->id, 'token' => 'stale']);

    app(StaffPushNotifier::class)
        ->notify(emitFor(StaffEvent::ORDER_CREATED, 'cash'));

    expect(DeviceToken::count())->toBe(0);
});

test('a temporary FCM failure keeps the token and does not throw', function () {
    fakeFirebaseCredentials();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.fake']),
        'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
    ]);
    DeviceToken::factory()->create(['user_id' => $this->cashier->id, 'token' => 'keep']);

    app(StaffPushNotifier::class)
        ->notify(emitFor(StaffEvent::ORDER_CREATED, 'cash'));

    expect(DeviceToken::count())->toBe(1);
});

test('push is skipped silently when firebase credentials are not configured', function () {
    config()->set('services.firebase.credentials', null);
    Http::fake();
    DeviceToken::factory()->create(['user_id' => $this->cashier->id]);

    app(StaffPushNotifier::class)
        ->notify(emitFor(StaffEvent::ORDER_CREATED, 'cash'));

    Http::assertNothingSent();
});

test('the access token is cached between pushes', function () {
    fakeFirebaseCredentials();
    fakeFcm();
    DeviceToken::factory()->create(['user_id' => $this->cashier->id]);

    $notifier = app(StaffPushNotifier::class);
    $notifier->notify(emitFor(StaffEvent::ORDER_CREATED, 'cash'));
    $notifier->notify(emitFor(StaffEvent::ORDER_CREATED, 'cash'));

    $oauthCalls = Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), 'oauth2.googleapis.com'));
    expect($oauthCalls)->toHaveCount(1);
});

test('staff can send a test push to their own devices only', function () {
    fakeFirebaseCredentials();
    fakeFcm();
    DeviceToken::factory()->create(['user_id' => $this->kitchen->id, 'token' => 'kitchen-tablet']);
    DeviceToken::factory()->create(['user_id' => $this->cashier->id, 'token' => 'cashier-tablet']);

    Sanctum::actingAs($this->kitchen);
    $this->postJson(route('v1.devices.test'))
        ->assertSuccessful()
        ->assertJsonPath('data.delivered', 1);

    expect(fcmRecipients())->toBe(['kitchen-tablet']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
        && $request['message']['data']['type'] === 'test');
});

test('test push explains why it cannot be sent', function () {
    Sanctum::actingAs($this->kitchen);

    config()->set('services.firebase.credentials', null);
    $this->postJson(route('v1.devices.test'))->assertStatus(503);

    fakeFirebaseCredentials();
    fakeFcm();
    $this->postJson(route('v1.devices.test'))->assertUnprocessable();
});

test('test push deletes a stale token and reports it', function () {
    fakeFirebaseCredentials();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.fake']),
        'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404),
    ]);
    DeviceToken::factory()->create(['user_id' => $this->kitchen->id, 'token' => 'stale']);

    Sanctum::actingAs($this->kitchen);
    $this->postJson(route('v1.devices.test'))->assertUnprocessable();

    expect(DeviceToken::count())->toBe(0);
});

test('test push reports a gateway error when FCM is down', function () {
    fakeFirebaseCredentials();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.fake']),
        'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
    ]);
    DeviceToken::factory()->create(['user_id' => $this->kitchen->id]);

    Sanctum::actingAs($this->kitchen);
    $this->postJson(route('v1.devices.test'))->assertStatus(502);
});
