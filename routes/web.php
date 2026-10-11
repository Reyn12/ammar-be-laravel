<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard owner (SPA kecil yang memakai API /api/v1 dengan login owner)
Route::view('/owner', 'owner')->name('owner.dashboard');
