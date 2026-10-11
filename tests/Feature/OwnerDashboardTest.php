<?php

test('halaman dashboard owner bisa dibuka', function () {
    $this->get('/owner')
        ->assertOk()
        ->assertSee('Dashboard Owner');
});
