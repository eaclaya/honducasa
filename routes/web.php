<?php

use App\Http\Controllers\Admin\BlacklistController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\GoogleCallbackController;
use App\Http\Controllers\Auth\GoogleOneTapController;
use App\Http\Controllers\Auth\GoogleRedirectController;
use App\Http\Controllers\Auth\PendingAuthActionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationReportController;
use App\Http\Controllers\ConversationStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingPhotoEnhancementController;
use App\Http\Controllers\ListingPhotoEnhancementStatusController;
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
use App\Http\Middleware\EnsureTeamCanCreateListing;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/rentals', RentalSearchController::class)->name('rentals.index');
Route::get('/locations/search', LocationSearchController::class)
    ->middleware('throttle:60,1')
    ->name('locations.search');
Route::get('/places/search', PlaceSearchController::class)
    ->middleware('throttle:60,1')
    ->name('places.search');
Route::get('/properties/{property:slug}', [PropertyShowController::class, 'show'])->name('properties.show');
Route::get('/properties/{property:slug}/preview', [PropertyShowController::class, 'preview'])
    ->middleware(['auth', 'verified'])
    ->name('properties.preview');
Route::post('/locale/{locale}', LocaleController::class)->whereIn('locale', ['es', 'en'])->name('locale.update');

Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::post('auth/pending-action', PendingAuthActionController::class)->name('auth.pending-action.store');
    Route::get('auth/google/redirect', GoogleRedirectController::class)->name('auth.google.redirect');
    Route::get('auth/google/callback', GoogleCallbackController::class)->name('auth.google.callback');
    Route::post('auth/google/one-tap', GoogleOneTapController::class)->name('auth.google.one-tap');
});

Route::get('dashboard', UserDashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('user.dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('listings/start', [ListingController::class, 'create'])->middleware(EnsureTeamCanCreateListing::class)->name('listings.start');
    Route::post('listings/start', [ListingController::class, 'store'])->name('listings.start.store');
    Route::get('listings', [ListingController::class, 'index'])->name('personal-listings.index');
    Route::get('listings/create', [ListingController::class, 'create'])->middleware(EnsureTeamCanCreateListing::class)->name('personal-listings.create');
    Route::post('listings', [ListingController::class, 'store'])->name('personal-listings.store');
    Route::get('listings/{listing}/edit', [ListingController::class, 'editPersonal'])->name('personal-listings.edit');
    Route::match(['put', 'patch'], 'listings/{listing}', [ListingController::class, 'updatePersonal'])->name('personal-listings.update');
    Route::delete('listings/{listing}', [ListingController::class, 'destroyPersonal'])->name('personal-listings.destroy');
    Route::post('listings/uploads', [ListingUploadController::class, 'store'])->name('listings.uploads.store');
    Route::post('listings/uploads/{media}/enhance', ListingPhotoEnhancementController::class)
        ->middleware('throttle:5,1')
        ->name('listings.uploads.enhance');
    Route::get('listings/uploads/enhancements/{requestId}', ListingPhotoEnhancementStatusController::class)
        ->whereUuid('requestId')
        ->name('listings.uploads.enhancement-status');
    Route::delete('listings/uploads/{media}', [ListingUploadController::class, 'destroy'])->name('listings.uploads.destroy');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('listings/create', [ListingController::class, 'create'])
            ->middleware(EnsureTeamCanCreateListing::class)
            ->name('listings.create');
        Route::resource('listings', ListingController::class)->except(['show', 'create']);
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
});

/**
 * Platform administration console. Every route here is admin-only by virtue of
 * the group middleware, so controllers and form requests must not repeat the
 * check.
 */
Route::middleware(['auth', 'verified', EnsureUserIsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('blacklist', [BlacklistController::class, 'index'])->name('blacklist.index');
        Route::delete('blacklist/{user}', [BlacklistController::class, 'destroy'])->name('blacklist.destroy');

        Route::get('moderation', [ModerationController::class, 'index'])->name('moderation.index');
        Route::patch('moderation/{report}', [ModerationController::class, 'update'])->name('moderation.update');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/suspension', [AdminUserController::class, 'updateSuspension'])->name('users.suspension.update');
        Route::patch('users/{user}/admin-status', [AdminUserController::class, 'updateAdminStatus'])->name('users.admin-status.update');

        Route::get('teams', [AdminTeamController::class, 'index'])->name('teams.index');
        Route::patch('teams/{team}/suspension', [AdminTeamController::class, 'updateSuspension'])->name('teams.suspension.update');
        Route::patch('teams/{team}/restore', [AdminTeamController::class, 'restore'])->name('teams.restore')->withTrashed();
        Route::patch('teams/{team}/trial', [AdminTeamController::class, 'extendTrial'])->name('teams.trial.update');
        Route::post('teams/{team}/subscription/comp', [AdminTeamController::class, 'compSubscription'])->name('teams.subscription.comp');
        Route::delete('teams/{team}/subscription', [AdminTeamController::class, 'cancelSubscription'])->name('teams.subscription.cancel');

        Route::get('properties', [AdminPropertyController::class, 'index'])->name('properties.index');
        Route::patch('properties/{property}/status', [AdminPropertyController::class, 'updateStatus'])->name('properties.status.update');

        Route::get('subscription-plans', [SubscriptionPlanController::class, 'index'])->name('subscription-plans.index');
        Route::post('subscription-plans', [SubscriptionPlanController::class, 'store'])->name('subscription-plans.store');
        Route::patch('subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update'])->name('subscription-plans.update');
        Route::patch('subscription-plans/{subscriptionPlan}/active', [SubscriptionPlanController::class, 'updateActive'])->name('subscription-plans.active.update');
    });

require __DIR__.'/settings.php';
