<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FichaController;
use App\Http\Controllers\ConfigController;

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

Route::get('/', function () {
    return view('ficha.ficha');
});

Route::post('/ficha', [FichaController::class, 'montarFicha']);

Route::get('/configs', [ConfigController::class, 'mostrarConfigs']);
Route::get('/configs/edit', [ConfigController::class, 'edit']);
Route::post('/configs/save', [ConfigController::class, 'save']);