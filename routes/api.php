<?php

use App\Http\Controllers\DiscountController;
use Illuminate\Support\Facades\Route;

Route::post('check-customer', [DiscountController::class, 'checkCustomer'])->name('checkCustomer');
