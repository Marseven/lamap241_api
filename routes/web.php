<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Route::get('/', function () {
    return view('welcome');
});

// Route pour l'authentification des broadcasts
Broadcast::routes(['middleware' => ['auth:sanctum']]);
