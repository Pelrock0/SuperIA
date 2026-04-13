<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Auth\LoginController;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group(
    [
        'namespace' => 'Backpack\CRUD\app\Http\Controllers',
        'middleware' => config('backpack.base.web_middleware', 'web'),

    ],
    function () {

            Route::get('loginDoubleAuth', [LoginController::class, 'showLoginForm']);

    }
);

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => config('backpack.base.web_middleware', 'web'),
], function () {
    Route::post('login', [LoginController::class, 'login']);
   
});

// --------------------------
// GRUPO 1: ADMINISTRACIÓN (solo usuarios con rol ADMIN)
// --------------------------
Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () {

    // CRUDs de catálogos (solo admins)
    Route::crud('users', 'UserCrudController');
    Route::crud('ai-prompt', 'AiPromptCrudController');

    // Waitlist management
    Route::crud('waitlist-entry', 'WaitlistEntryCrudController');
    Route::post('waitlist-entry/{id}/invite', [\App\Http\Controllers\Admin\WaitlistEntryCrudController::class, 'invite'])
        ->name('waitlist-entry.invite');
    Route::post('waitlist-entry/export-csv', [\App\Http\Controllers\Admin\WaitlistEntryCrudController::class, 'exportCsv'])
        ->name('waitlist-entry.export-csv');

});

// --------------------------
// GRUPO 2: APLICACIÓN (Usuarios autenticados)
// --------------------------
Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        ['auth:backpack'] // Autenticación con el guard de Backpack, sin check de admin
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () {

    //------------------------------------
    // Dashboard (ruta principal después del login)
    //----------------------------------
    Route::get('dashboard', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('backpack.dashboard');
    Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard']);
    Route::crud('user', \App\Http\Controllers\Admin\UserCrudController::class);
    Route::crud('ai-usage', 'AiUsageCrudController');
    Route::crud('waitlist-entry', 'WaitlistEntryCrudController');
});
// this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
