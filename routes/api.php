<?php

use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\Auth\AccountDeletionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ComplementController;
use App\Http\Controllers\ListItemController;
use App\Http\Controllers\ProductSuggestionController;
use App\Http\Controllers\ReplenishmentController;
use App\Http\Controllers\SharedListController;
use App\Http\Controllers\ShareTokenController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\ListGenerationController;
use App\Http\Controllers\PriceEstimationController;
use App\Http\Controllers\WeeklySummaryController;
use App\Http\Controllers\Settings\WeeklySummaryEmailController;
use Illuminate\Support\Facades\Route;

// Waitlist
Route::post('/waitlist', [WaitlistController::class, 'store'])
    ->middleware('throttle:3,60');

// Auth (public)
Route::prefix('auth')->group(function () {
    Route::get('/invitation/{token}', [RegisterController::class, 'validateToken']);
    Route::post('/register', [RegisterController::class, 'register'])
        ->middleware('throttle:5,60');
    Route::get('/verify-email/{id}/{hash}', [RegisterController::class, 'verifyEmail'])
        ->name('auth.verify-email');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:10,60');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
        ->middleware('throttle:3,60');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
        ->middleware('throttle:5,60');
});

// Auth (protected)
Route::middleware(['auth:api', \App\Http\Middleware\JwtVersionCheck::class])->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout']);
    Route::post('/auth/refresh', [LoginController::class, 'refresh'])
        ->middleware('throttle:30,1');
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::get('/profile/my-data', [\App\Http\Controllers\Auth\DataExportController::class, 'show']);
    Route::get('/profile/export', [\App\Http\Controllers\Auth\DataExportController::class, 'export']);
    Route::put('/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:10,1');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])
        ->middleware('throttle:5,60');
    Route::get('/profile/history', [ProfileController::class, 'history']);
    Route::delete('/profile/history', [ProfileController::class, 'clearHistory']);
    Route::delete('/profile/history/{producto}', [ProfileController::class, 'forgetProduct'])
        ->where('producto', '.*');

    // Product suggestions (Epic 5A)
    Route::get('/suggestions', [ProductSuggestionController::class, 'index'])
        ->middleware('throttle:60,1');

    // Complementary suggestions (Epic 5B - HU-504)
    Route::get('/suggestions/complements', [ComplementController::class, 'index'])
        ->middleware('throttle:60,1');

    // Replenishment alerts (Epic 5B - HU-503)
    Route::get('/dashboard/replenishment', [ReplenishmentController::class, 'index']);
    Route::post('/replenishment/accept', [ReplenishmentController::class, 'accept']);
    Route::post('/replenishment/ignore', [ReplenishmentController::class, 'ignore']);
    Route::post('/replenishment/silence', [ReplenishmentController::class, 'silence']);
    Route::post('/auth/delete-account', [AccountDeletionController::class, 'destroy'])
        ->middleware('throttle:3,60');

    // Shopping Lists
    Route::get('/lists', [ShoppingListController::class, 'index']);
    Route::post('/lists', [ShoppingListController::class, 'store']);
    Route::get('/lists/{list}', [ShoppingListController::class, 'show']);
    Route::put('/lists/{list}', [ShoppingListController::class, 'update']);
    Route::patch('/lists/{list}/archive', [ShoppingListController::class, 'archive']);
    Route::patch('/lists/{list}/restore', [ShoppingListController::class, 'restore']);
    Route::delete('/lists/{list}', [ShoppingListController::class, 'destroy']);

    // List Items
    Route::get('/lists/{list}/items', [ListItemController::class, 'index']);
    Route::post('/lists/{list}/items', [ListItemController::class, 'store']);
    Route::delete('/lists/{list}/items/completed', [ListItemController::class, 'clearCompleted']);
    Route::put('/lists/{list}/items/{item}', [ListItemController::class, 'update']);
    Route::patch('/lists/{list}/items/{item}/toggle', [ListItemController::class, 'toggle']);
    Route::patch('/lists/{list}/items/{item}/increment-quantity', [ListItemController::class, 'incrementQuantity']);
    Route::delete('/lists/{list}/items/{item}', [ListItemController::class, 'destroy']);

    // Share tokens (owner side)
    Route::get('/lists/{list}/share', [ShareTokenController::class, 'index']);
    Route::post('/lists/{list}/share', [ShareTokenController::class, 'store'])
        ->middleware('throttle:10,60');
    Route::delete('/lists/{list}/share/{token}', [ShareTokenController::class, 'destroy']);

    // Collaboration views (owner side)
    Route::get('/lists/{list}/collaborators/count', [ShoppingListController::class, 'collaboratorsCount']);
    Route::get('/lists/{list}/collaborators', [ShoppingListController::class, 'collaborators']);
    Route::get('/lists/{list}/activity', [ShoppingListController::class, 'activityLog']);

    // Weekly summary (Epic 5C - HU-505)
    Route::get('/weekly-summary/latest', [WeeklySummaryController::class, 'latest']);
    Route::post('/weekly-summary/dismiss', [WeeklySummaryController::class, 'dismiss']);
    Route::post('/weekly-summary/{summary}/convert-to-list', [WeeklySummaryController::class, 'convertToList']);
    Route::post('/settings/weekly-summary-email', [WeeklySummaryEmailController::class, 'update']);

    // List generation (Epic 6 - HU-601 + HU-602)
    Route::post('/generate-list', [ListGenerationController::class, 'generate']);
    Route::post('/generate-list/confirm-new', [ListGenerationController::class, 'confirmNew']);
    Route::post('/generate-list/confirm-existing', [ListGenerationController::class, 'confirmExisting']);

    // Price estimation (Epic 7 - HU-701 + HU-702)
    Route::post('/lists/{list}/estimate-prices', [PriceEstimationController::class, 'estimate']);
    Route::post('/lists/{list}/confirm-prices', [PriceEstimationController::class, 'confirmPrices']);

    // History + Stats (Epic 9 - HU-901 + HU-902)
    Route::get('/history', [HistoryController::class, 'index']);
    Route::post('/lists/{list}/duplicate', [HistoryController::class, 'duplicate']);
    Route::get('/stats', [StatsController::class, 'index']);
});

// Shared list (public, token-based)
Route::middleware([\App\Http\Middleware\ValidateShareToken::class, 'throttle:60,1'])->group(function () {
    Route::get('/shared/{tokenParam}', [SharedListController::class, 'show']);
    Route::post('/shared/{tokenParam}/heartbeat', [SharedListController::class, 'heartbeat']);
    Route::get('/shared/{tokenParam}/save-status', [SharedListController::class, 'saveStatus']);
    Route::post('/shared/{tokenParam}/save', [SharedListController::class, 'saveToAccount']);
});

Route::middleware([\App\Http\Middleware\ValidateShareToken::class.':write', 'throttle:60,1'])->group(function () {
    Route::post('/shared/{tokenParam}/items', [SharedListController::class, 'storeItem']);
    Route::put('/shared/{tokenParam}/items/{item}', [SharedListController::class, 'updateItem']);
    Route::patch('/shared/{tokenParam}/items/{item}/toggle', [SharedListController::class, 'toggleItem']);
    Route::delete('/shared/{tokenParam}/items/{item}', [SharedListController::class, 'destroyItem']);
});
