<?php

use Illuminate\Support\Facades\Route;

Route::post('message', 'ApiController@message');
Route::get('report', 'ApiController@report')->name('report');
Route::get('report/{id}', 'ApiController@detail')->name('detail');

//Route::get('schleck', 'SchleckController@notify');
