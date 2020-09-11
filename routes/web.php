<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/refresh', 'HomeController@refresh');
Route::get('/login', 'AuthController@login');
Route::get('/callback', 'AuthController@callback');
Route::get('/logout', 'AuthController@logout');
Route::get('/calendar', 'CalendarController@calendar');

Route::get('/subscribe', 'AuthController@subscribe');
Route::get('/testSubscribe', 'AuthController@testSubscribe');
Route::get('/testWebhooks', 'AuthController@testWebhooks');
Route::post('/notify', 'AuthController@notify');

// Route::any('/{any}', 'HomeController@index');

Route::any('/{any}', 'HomeController@index')->where('any', '.*')->name('react');

