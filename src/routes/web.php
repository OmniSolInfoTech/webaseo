<?php

use Osit\Webaseo\Controllers\WebaseoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/**
 * GET route for Webaseo report
 */
Route::get('/webaseo/report', [WebaseoController::class, "run"])->name('/webaseo/report');