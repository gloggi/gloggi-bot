<?php

use Illuminate\Support\Facades\Route;

Route::post('message', 'ApiController@message');
Route::get('report', 'ApiController@report');

//Route::get('schleck', 'SchleckController@notify');
