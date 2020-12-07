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

Route::get('/test', 'HomeController@test');
Route::get('/download', 'HomeController@download');
Route::match(['get', 'post'], '/update', 'HomeController@update');
Route::get('/welcome', 'HomeController@welcome');
Route::get('/login', 'AuthController@login');
Route::get('/callback', 'AuthController@callback');
Route::get('/logout', 'AuthController@logout');
Route::get('/calendar', 'CalendarController@calendar');

Route::get('/subscribe', 'AuthController@subscribe');
Route::get('/resubscribe', 'HomeController@resubscribe');
Route::post('/notify', 'AuthController@notify');
Route::get('/getToken', 'AuthController@getValidationToken');

// Route::any('/{any}', 'HomeController@index');

Route::any('/{any}', 'HomeController@index')->where('any', '^(?!api).*')->name('react');

