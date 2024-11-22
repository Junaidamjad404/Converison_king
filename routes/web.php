<?php

use App\Http\Controllers\CouponController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware(['verify.shopify'])->name('home');
Route::post('store/coupon', [CouponController::class, 'store'])->middleware(['verify.shopify'])->name('store.coupon');

Route::get('test', function () {
    $user = User::first();
    dd($user->api()->rest('get', '/admin/api/2024-10/shop.json'));
})->middleware(['verify.shopify'])->name('test');
