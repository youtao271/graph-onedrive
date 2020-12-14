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

Route::middleware('cors')->group(function () {

    Route::prefix('disc')->group(function () {
        Route::get('/content', 'Api\IndexController@content');
        Route::get('/', 'Api\IndexController@index');
        Route::post('/create', 'Api\IndexController@create');
        Route::post('/upload', 'Api\IndexController@upload');
        Route::post('/move', 'Api\IndexController@move');
        Route::post('/download', 'Api\IndexController@download');
        Route::match(['get', 'post'], '/update', 'Api\IndexController@update');
        Route::post('/password', 'Api\IndexController@password');
        Route::post('/delete', 'Api\IndexController@delete');
    });

    Route::prefix('diss')->group(function () {
        Route::get('/list', 'Api\QQMusicController@list');
        Route::get('/info', 'Api\QQMusicController@info');
    });


    Route::get('/{any}', 'Api\IndexController@index')->where('any', '.*');

});

