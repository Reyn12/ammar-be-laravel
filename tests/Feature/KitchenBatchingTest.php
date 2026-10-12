<?php

use App\Actions\Kitchen\AssignOrderToBatches;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shift;
use App\Models\StaffEvent;
use App\Models\Table;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->koki = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'kitchen']);
    $this->kasir = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']);
    $category = Category::factory()->create(['name' => 'Makanan']);
    $this->ayam = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $category->id, 'name' => 'Ayam Bakar']);
    $this->es = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $category->id, 'name' => 'Es Teh']);

    // Pesanan lunas dengan item-item {product => qty}
    $this->paidOrder = function (array $lines, array $attributes = []) {
        $order = Order::factory()->create(array_merge([
            'branch_id' => $this->branch->id,
            'order_type' => 'takeaway',
            'payment_status' => 'paid',
            'payment_method' => 'qris',
        ], $attributes));

        foreach ($lines as $line) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $line[0]->id,
                'product_name' => $line[0]->name,
                'qty' => $line[1],
                'status' => 'pending',
                'batch_id' => null,
            ]);
        }

        app(AssignOrderToBatches::class)->handle($order->refresh());

        return $order->refresh();
    };
});

test('items of the same product from different orders share one batch', function () {
    $a = ($this->paidOrder)([[$this->ayam, 2], [$this->es, 1]]);
    $b = ($this->paidOrder)([[$this->ayam, 3]]);

    expect(Batch::count())->toBe(2);

    $ayamBatch = Batch::where('product_id', $this->ayam->id)->first();
    expect($ayamBatch->order_count)->toBe(2)
        ->and($ayamBatch->total_qty)->toBe(5)
        ->and($ayamBatch->status)->toBe('open')
        ->and($ayamBatch->items()->pluck('order_id')->unique()->sort()->values()->all())->toBe([$a->id, $b->id]);
});

test('two lines of the same product in one order count as a single note', function () {
    ($this->paidOrder)([[$this->ayam, 1], [$this->ayam, 2]]);

    $batch = Batch::first();
    expect(Batch::count())->toBe(1)
        ->and($batch->order_count)->toBe(1)
        ->and($batch->total_qty)->toBe(3);
});

test('a batch has no note limit and later orders keep joining the same batch', function () {
    foreach (range(1, 7) as $i) {
        ($this->paidOrder)([[$this->ayam, 1]]);
    }

    $batch = Batch::first();
    expect(Batch::count())->toBe(1)
        ->and($batch->order_count)->toBe(7)
        ->and($batch->total_qty)->toBe(7)
        ->and($batch->status)->toBe('open');
});

test('assigning twice does not duplicate items in batches', function () {
    $order = ($this->paidOrder)([[$this->ayam, 2]]);

    app(AssignOrderToBatches::class)->handle($order);

    expect(Batch::first()->order_count)->toBe(1)->and(Batch::count())->toBe(1);
});

test('confirming cash puts the order into the kitchen batches and announces it', function () {
    Shift::create(['user_id' => $this->kasir->id, 'start_time' => now(), 'status' => 'active', 'starting_cash' => 0]);
    $order = Order::factory()->create(['branch_id' => $this->branch->id, 'payment_method' => 'cash', 'payment_status' => 'unpaid', 'total_amount' => 30000]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->ayam->id, 'product_name' => 'Ayam Bakar', 'qty' => 1, 'batch_id' => null]);

    Sanctum::actingAs($this->kasir);
    $this->postJson(route('v1.staff.orders.confirm-cash', $order))->assertSuccessful();

    expect(Batch::first()->order_count)->toBe(1);
    $this->assertDatabaseHas('staff_events', ['type' => StaffEvent::ORDER_PAID, 'order_id' => $order->id, 'branch_id' => $this->branch->id]);
});

test('kitchen batches endpoint lists pending notes with table labels', function () {
    $table = Table::factory()->create(['branch_id' => $this->branch->id, 'table_number' => 12]);
    $dineIn = ($this->paidOrder)([[$this->ayam, 2]], ['order_type' => 'dine_in', 'table_id' => $table->id]);
    $takeaway = ($this->paidOrder)([[$this->ayam, 3]]);

    Sanctum::actingAs($this->koki);

    $response = $this->getJson(route('v1.kitchen.batches'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product_name', 'Ayam Bakar')
        ->assertJsonPath('data.0.category_name', 'Makanan')
        ->assertJsonPath('data.0.total_qty', 5)
        ->assertJsonPath('data.0.status', 'pending');

    $tables = collect($response->json('data.0.tables'))->keyBy('order_id');
    expect($tables[$dineIn->id]['table_label'])->toBe('Meja 12')
        ->and($tables[$dineIn->id]['qty'])->toBe(2)
        ->and($tables[$takeaway->id]['table_label'])->toBe('Takeaway')
        ->and($tables[$dineIn->id]['order_code'])->toBe($dineIn->code);
});

test('processing selected notes shrinks the batch and a full process removes it', function () {
    $a = ($this->paidOrder)([[$this->ayam, 2]]);
    $b = ($this->paidOrder)([[$this->ayam, 3]]);
    Sanctum::actingAs($this->koki);

    $this->postJson(route('v1.kitchen.batches.process', Batch::first()), ['order_ids' => [$a->id]])->assertSuccessful();

    $this->getJson(route('v1.kitchen.batches'))
        ->assertJsonPath('data.0.total_qty', 3)
        ->assertJsonCount(1, 'data.0.tables')
        ->assertJsonPath('data.0.tables.0.order_id', $b->id);

    expect($a->refresh()->status)->toBe('cooking')->and($b->refresh()->status)->toBe('pending');

    $this->postJson(route('v1.kitchen.batches.process', Batch::first()))->assertSuccessful();

    $this->getJson(route('v1.kitchen.batches'))->assertJsonCount(0, 'data');
    expect(Batch::first()->status)->toBe('cooking');

    $this->postJson(route('v1.kitchen.batches.process', Batch::first()))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Tidak ada item yang bisa diproses di batch ini.');
});

test('processing some notes shrinks the batch and new notes still join it', function () {
    $orders = collect(range(1, 7))->map(fn () => ($this->paidOrder)([[$this->ayam, 1]]));
    Sanctum::actingAs($this->koki);

    $this->postJson(route('v1.kitchen.batches.process', Batch::first()), ['order_ids' => [$orders[0]->id, $orders[1]->id]])->assertSuccessful();

    expect(Batch::first()->status)->toBe('open')->and(Batch::first()->order_count)->toBe(5);

    ($this->paidOrder)([[$this->ayam, 1]]);

    expect(Batch::count())->toBe(1)->and(Batch::first()->order_count)->toBe(6);
});

test('item status moves forward and the order completes when everything is served', function () {
    $order = ($this->paidOrder)([[$this->ayam, 1], [$this->es, 1]]);
    [$ayamItem, $esItem] = [$order->items()->orderBy('id')->first(), $order->items()->orderByDesc('id')->first()];
    Sanctum::actingAs($this->koki);

    $this->patchJson(route('v1.order-items.status', $ayamItem), ['status' => 'ready'])
        ->assertUnprocessable(); // tidak boleh melompati cooking

    $this->patchJson(route('v1.order-items.status', $ayamItem), ['status' => 'cooking'])->assertSuccessful();
    $this->patchJson(route('v1.order-items.status', $ayamItem), ['status' => 'cooking'])->assertSuccessful(); // idempoten
    expect($order->refresh()->status)->toBe('cooking');

    $this->patchJson(route('v1.order-items.status', $ayamItem), ['status' => 'ready'])->assertSuccessful();
    expect($order->refresh()->status)->toBe('cooking'); // es teh belum disajikan

    $this->patchJson(route('v1.order-items.status', $ayamItem), ['status' => 'cooking'])->assertUnprocessable(); // tidak boleh mundur

    $this->patchJson(route('v1.order-items.status', $esItem), ['status' => 'cooking'])->assertSuccessful();
    $this->patchJson(route('v1.order-items.status', $esItem), ['status' => 'ready'])->assertSuccessful();

    expect($order->refresh()->status)->toBe('completed')
        ->and(Batch::where('product_id', $this->ayam->id)->first()->status)->toBe('done');

    expect(StaffEvent::where('type', StaffEvent::KITCHEN_UPDATED)->count())->toBeGreaterThanOrEqual(4)
        ->and(StaffEvent::where('type', StaffEvent::ORDER_READY)->count())->toBe(1);
});

test('kitchen orders only contain active paid orders', function () {
    $active = ($this->paidOrder)([[$this->ayam, 1]]);
    ($this->paidOrder)([[$this->ayam, 1]], ['status' => 'completed']);
    ($this->paidOrder)([[$this->ayam, 1]], ['payment_status' => 'unpaid']);
    Order::factory()->create(['payment_status' => 'paid', 'status' => 'pending']); // cabang lain

    Sanctum::actingAs($this->koki);

    $ids = collect($this->getJson(route('v1.kitchen.orders'))->assertSuccessful()->json('data'))->pluck('id');

    expect($ids->all())->toBe([$active->id]);
});

test('kitchen endpoints are limited to kitchen staff of the same branch', function () {
    $order = ($this->paidOrder)([[$this->ayam, 1]]);
    $item = $order->items()->first();

    $this->getJson(route('v1.kitchen.batches'))->assertUnauthorized();

    Sanctum::actingAs($this->kasir);
    $this->getJson(route('v1.kitchen.batches'))->assertForbidden();
    $this->patchJson(route('v1.order-items.status', $item), ['status' => 'cooking'])->assertForbidden();

    app('auth')->forgetGuards();
    Sanctum::actingAs(User::factory()->create(['role' => 'kitchen'])); // cabang lain
    $this->patchJson(route('v1.order-items.status', $item), ['status' => 'cooking'])->assertNotFound();
    $this->postJson(route('v1.kitchen.batches.process', Batch::first()))->assertNotFound();
});

test('unpaid orders cannot be cooked', function () {
    $order = ($this->paidOrder)([[$this->ayam, 1]], ['payment_status' => 'unpaid']);
    Sanctum::actingAs($this->koki);

    $this->patchJson(route('v1.order-items.status', $order->items()->first()), ['status' => 'cooking'])->assertUnprocessable();
});
