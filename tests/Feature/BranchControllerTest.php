<?php

use App\Models\Branch;
use App\Models\BranchOpeningHour;

test('branch detail returns store info and opening hours', function () {
    $branch = Branch::factory()->create([
        'description' => 'Rumah makan ayam bakar',
        'phone' => '081234567890',
    ]);

    foreach (range(1, 7) as $day) {
        BranchOpeningHour::create([
            'branch_id' => $branch->id,
            'day_of_week' => $day,
            'open_time' => '00:00',
            'close_time' => '23:59',
        ]);
    }

    $this->getJson(route('v1.branches.show', $branch))
        ->assertSuccessful()
        ->assertJsonPath('data.name', $branch->name)
        ->assertJsonPath('data.phone', '081234567890')
        ->assertJsonPath('data.order_types', ['dine_in', 'takeaway'])
        ->assertJsonPath('data.is_open_now', true)
        ->assertJsonCount(7, 'data.opening_hours')
        ->assertJsonPath('data.opening_hours.0.day_of_week', 1)
        ->assertJsonPath('data.opening_hours.0.open_time', '00:00');
});

test('branch is closed when today is marked closed', function () {
    $branch = Branch::factory()->create();

    foreach (range(1, 7) as $day) {
        BranchOpeningHour::create([
            'branch_id' => $branch->id,
            'day_of_week' => $day,
            'is_closed' => true,
        ]);
    }

    $this->getJson(route('v1.branches.show', $branch))
        ->assertSuccessful()
        ->assertJsonPath('data.is_open_now', false);
});

test('unknown branch returns 404', function () {
    $this->getJson(route('v1.branches.show', 999))->assertNotFound();
});
