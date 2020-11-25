<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/* Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
}); */

// Route::any('{any}', 'Api\IndexController@index')->where('any', '.*')->name('react');
// Route::get('/all', 'Api\IndexController@all');

// Route::middleware('cors')->group(function () {

    Route::get('/content/{id}', 'Api\IndexController@content');
    Route::get('/', 'Api\IndexController@index');
    // Route::post('/create', 'Api\IndexController@create');
    Route::post('/create', function (Request $request){
        return response($request->all());
    });
    Route::post('/upload', 'Api\IndexController@upload');
    Route::post('/delete', 'Api\IndexController@delete');
    Route::get('/{any}', 'Api\IndexController@index')->where('any', '.*');

// });

