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

Route::get('/', 'HomeController@welcome');
Route::get('/login', 'AuthController@signin');
Route::get('/callback', 'AuthController@callback');
Route::get('/logout', 'AuthController@signout');
Route::get('/calendar', 'CalendarController@calendar');

