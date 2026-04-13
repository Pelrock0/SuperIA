<?php

use App\Http\Controllers\Public\UnsubscribeWeeklySummaryController;
use Illuminate\Support\Facades\Route;

// Public signed routes (must be declared before the SPA catch-all)
Route::get('/unsubscribe/weekly-summary/{user}', [UnsubscribeWeeklySummaryController::class, 'handle'])
    ->middleware('signed')
    ->name('weekly-summary.unsubscribe');

// SPA catch-all — serves the React app for all non-API/non-admin routes
Route::get('/{any}', function () {
    return view('landing');
})->where('any', '^(?!api|admin|backpack|telescope|unsubscribe).*$');
