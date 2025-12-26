<?php

use Daugt\Commerce\Controllers\CartController;
use Illuminate\Support\Facades\Route;

Route::post('/cart/add', [CartController::class, 'add'])->name('daugt-commerce.cart.add');
Route::post('/cart/remove', [CartController::class, 'remove'])->name('daugt-commerce.cart.remove');
