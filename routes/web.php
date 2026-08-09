<?php

use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Auth\GoogleCallbackController;
use App\Http\Controllers\Auth\GoogleRedirectController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationReportController;
use App\Http\Controllers\ConversationStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingUploadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LocationSearchController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlaceSearchController;
use App\Http\Controllers\PropertyFavoriteController;
use App\Http\Controllers\PropertyShowController;
use App\Http\Controllers\RentalSearchController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UserDashboardController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('/rentals', RentalSearchController::class)->name('rentals.index');
Route::get('/locations/search', LocationSearchController::class)
    ->middleware('throttle:60,1')
    ->name('locations.search');
Route::get('/places/search', PlaceSearchController::class)
    ->middleware('throttle:60,1')
    ->name('places.search');
Route::get('/properties/{property:slug}', PropertyShowController::class)->name('properties.show');
Route::post('/locale/{locale}', LocaleController::class)->whereIn('locale', ['es', 'en'])->name('locale.update');

Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('auth/google/redirect', GoogleRedirectController::class)->name('auth.google.redirect');
    Route::get('auth/google/callback', GoogleCallbackController::class)->name('auth.google.callback');
});

Route::get('dashboard', UserDashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('user.dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('listings/start', [ListingController::class, 'create'])->name('listings.start');
    Route::post('listings/start', [ListingController::class, 'store'])->name('listings.start.store');
    Route::post('listings/uploads', [ListingUploadController::class, 'store'])->name('listings.uploads.store');
    Route::delete('listings/uploads/{media}', [ListingUploadController::class, 'destroy'])->name('listings.uploads.destroy');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::resource('listings', ListingController::class)->except('show');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('favorites', [PropertyFavoriteController::class, 'index'])->name('favorites.index');
    Route::post('properties/{property:slug}/favorite', [PropertyFavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('properties/{property:slug}/favorite', [PropertyFavoriteController::class, 'destroy'])->name('favorites.destroy');
    Route::resource('saved-searches', SavedSearchController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('messages/{conversation?}', [ConversationController::class, 'index'])->name('messages.show');
    Route::post('properties/{property:slug}/conversations', [ConversationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('conversations.store');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('messages.store');
    Route::patch('messages/{conversation}/status', ConversationStatusController::class)
        ->name('messages.status.update');
    Route::post('messages/{conversation}/reports', [ConversationReportController::class, 'store'])
        ->middleware('throttle:3,10')
        ->name('messages.reports.store');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read_all');
    Route::get('admin/moderation', [ModerationController::class, 'index'])->name('admin.moderation.index');
    Route::patch('admin/moderation/{report}', [ModerationController::class, 'update'])->name('admin.moderation.update');
});

require __DIR__.'/settings.php';
