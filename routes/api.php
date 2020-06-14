<?php
use Illuminate\Http\Request;
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
// API Group Routes
Route::group(['prefix' => 'v1'], function () {
    /*
     * Guest area
     */
    Route::post('auth/login', 'AuthController@login')->name('login');
    Route::post('auth/register', 'AuthController@register');
    Route::post('auth/forgot', 'AuthController@forgot');

    Route::get('file/show/{hash}', 'FileController@show');
    Route::get('file/get/{hash}', 'FileController@get');
    Route::get('file/download/{hash}', 'FileController@download');
    Route::get('directory/download/{id}', 'DirectoryController@downloadZip');

    /*
     * Authenticated area
     */
    Route::group(['middleware' => 'auth:api'], function () {
        Route::get('user/me', 'UserController@me');
        Route::delete('user/delete', 'UserController@delete');
        Route::get('user/files', 'UserController@files');
        Route::put('user/password/{user}', 'UserController@changePassword');
        Route::resource('user', 'UserController', ['except' => ['create', 'store', 'edit']]);

        Route::get('file/detail/{hash}', 'FileController@detail');
        Route::put('file/share', 'FileController@share');
        Route::post('file/chunk', 'FileController@chunk');
        Route::post('file/upload', 'FileController@upload');
        Route::delete('file/delete/{hash}', 'FileController@delete');
        Route::put('file/rename', 'FileController@rename');
        Route::put('file/move', 'FileController@move');

        Route::put('multiple/move', 'MultipleController@move');
        Route::put('multiple/delete', 'MultipleController@delete');

        Route::post('directory/create', 'DirectoryController@create');
        Route::post('directory/show', 'DirectoryController@show');
        Route::put('directory/delete', 'DirectoryController@delete');
        Route::put('directory/rename', 'DirectoryController@rename');
        Route::put('directory/move', 'DirectoryController@move');
        Route::post('directory/download', 'DirectoryController@saveZip');

    });
});
