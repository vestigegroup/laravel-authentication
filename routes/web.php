<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'AuthController@login');
Route::post('/', 'AuthController@postLogin');

Route::get('/register', 'AuthController@register');
Route::post('/register', 'AuthController@postRegister');

Route::get('/verify/{email}', 'AuthController@verify');
Route::post('/verify/{email}', 'AuthController@postVerify');

Route::get('/logout', 'AuthController@logout');

Route::group(['middleware' => 'auth'], function() {
	Route::get('/main', 'MainController@index');
});