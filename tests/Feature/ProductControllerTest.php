<?php

use App\Actions\Product\GetDummyProducts;

test('dummy products endpoint returns successful response', function () {
    $this->getJson(route('v1.products.dummy'))
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'price', 'category', 'is_available'],
            ],
            'message',
        ]);
});

test('dummy products returns 5 products', function () {
    $this->getJson(route('v1.products.dummy'))
        ->assertSuccessful()
        ->assertJsonCount(5, 'data');
});

test('get dummy products action returns collection with correct keys', function () {
    $action = new GetDummyProducts;

    $products = $action->handle();

    expect($products)->toHaveCount(5)
        ->and($products->first())->toHaveKeys(['id', 'name', 'price', 'category', 'is_available']);
});
