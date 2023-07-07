<?php

use App\Http\Livewire\Dumpinger;
use App\Http\Livewire\Parser;
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

Route::get('/', Parser::class);
Route::get('/parser', Parser::class);
Route::get('/dumpinger', Dumpinger::class);

