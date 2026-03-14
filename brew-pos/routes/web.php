<?php

use Illuminate\Support\Facades\Route;

// Single-page app entry; all routes are handled by Vue router
Route::view('/{any?}', 'app')->where('any', '.*');
