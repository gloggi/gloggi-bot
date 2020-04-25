<?php

use Illuminate\Support\Facades\Route;

Route::post('message', 'ApiController@message');

Route::get('schleck', 'SchleckController@notify');
