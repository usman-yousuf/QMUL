<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CurrentEventsController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\PledgeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });


Route::get('/', function () {
    return view('index');
})->name('index');

Route::get('test', function () {
    return view('test');
})->name('test');

// Current Events Route
Route::get('current-events', [CurrentEventsController::class, 'index'])->name('current-events');

// Events Booking Routes
Route::match(['get', 'post'],'events/booking/{eventID}', [EventController::class, 'eventProcess'])->name('event.process');
Route::match(['get', 'post'], 'events/booking/{eventID}/{step}', [EventController::class, 'eventProcess'])->name('event.back');

//Donation Routes
Route::match(['get', 'post'], 'donation-view', [DonationController::class, 'processForm'])->name('donation');

// Pledge Donation Routes
Route::get( 'pledge/donation/view', [PledgeController::class, 'pledgeDonation'])->name('pledge.donation.view');
Route::post( 'pledge/donation', [PledgeController::class, 'pledgeDonation'])->name('pledge.donation');

// Payment Routes
Route::get('/hpp-request-donate', [PaymentController::class, 'hppRequestDonate'])->name('hpp.request.donate');
Route::get('/hpp-request', [PaymentController::class, 'hppRequest'])->name('hpp.request');


