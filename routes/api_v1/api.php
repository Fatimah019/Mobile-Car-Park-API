<?php

use Illuminate\Support\Facades\Route;

/*
 * API V1 Routes
 */

Route::prefix('auth')->namespace('Auth')->group(function () {
    Route::post('phone-registration-status', 'RegistrationStatusController@withPhone');
    Route::post('request-otp', 'OTPController@send');
    Route::post('verify-otp', 'OTPController@verify');
    Route::prefix('register')->group( function () {
        Route::post('admin', 'RegisterController@admin')->middleware('admin');
        Route::post('partner', 'RegisterController@partner');
        Route::post('user', 'RegisterController@user');
    });

    Route::prefix('login')->group( function () {
        Route::post('/', 'LoginController@adminAndPartner');
        Route::post('user', 'LoginController@user');
        Route::post('facebook', 'FacebookLoginController');
    });
});

Route::prefix('user')->middleware('auth')->group( function () {
    Route::get('/', 'UserProfileController@show');
    Route::put('/', 'UserProfileController@update');
    Route::patch('/settings', 'UserProfileController@manageProfile');
});

Route::prefix('vehicles')->middleware('auth')->group( function () {
    Route::get('/', 'VehiclesController@index');
    Route::post('/', 'VehiclesController@store');
    Route::put('{id}', 'VehiclesController@update');
    Route::delete('{id}', 'VehiclesController@delete');
});

Route::group(['prefix' => 'park', 'middleware' => 'auth'], function () {
    Route::group(['middleware' => 'admin'], function () {
        Route::get('active', 'CarParkController@showActive');
        Route::get('inactive', 'CarParkController@showInActive');
    	Route::post('/', 'CarParkController@store');
    	Route::put('{id}', 'CarParkController@update');
        Route::get('/history', 'CarParkHistoryController');
    });

    Route::get('/history/{id?}', 'CarParkHistoryController');
    Route::post('/book/{id}', 'CarParkBookingController');
    Route::put('/book/{id}', 'CarParkBookingController@update');

	Route::get('/', 'CarParkController@apiIndex');
    Route::get('all', 'CarParkController@index');
    Route::get('{id}', 'CarParkController@show');
});
