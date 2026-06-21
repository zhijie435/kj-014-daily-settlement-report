<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/customer-groups', function () {
    return view('customer-groups.index');
})->name('customer-groups.index');
