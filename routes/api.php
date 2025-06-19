<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SportController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\MatchHistoryController;
use App\Http\Controllers\Api\PlayerRatingController;
use App\Http\Controllers\Api\CreditScoreController;
use App\Http\Controllers\Api\RealTimeController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\MatchmakingController;
use App\Http\Controllers\Api\VenueController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Public sports routes
Route::get('sports', [SportController::class, 'index']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
    });

    // User routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/blocked', [UserController::class, 'getBlockedUsers']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        
        // User blocking
        Route::post('/{user}/block', [UserController::class, 'blockUser']);
        Route::delete('/{user}/unblock', [UserController::class, 'unblockUser']);
        
        // User sport ratings
        Route::get('/{user}/sport-ratings', [UserController::class, 'getSportRatings']);
        Route::put('/{user}/sport-ratings/{sport}', [UserController::class, 'updateSportRating']);
        
        // User match history
        Route::get('/{user}/match-history', [UserController::class, 'getMatchHistory']);
    });

    // Events routes
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{event}', [EventController::class, 'show']);
        Route::put('/{event}', [EventController::class, 'update']);
        Route::delete('/{event}', [EventController::class, 'destroy']);
        
        // Event participation
        Route::post('/{event}/join', [EventController::class, 'joinEvent']);
        Route::delete('/{event}/leave', [EventController::class, 'leaveEvent']);
        Route::get('/{event}/participants', [EventController::class, 'getParticipants']);
        
        // Event management (host only)
        Route::put('/{event}/participants/{participant}/confirm', [EventController::class, 'confirmParticipant']);
        Route::put('/{event}/participants/{participant}/reject', [EventController::class, 'rejectParticipant']);
        Route::post('/{event}/check-in/{participant}', [EventController::class, 'checkInParticipant']);
    });

    // Communities routes
    Route::prefix('communities')->group(function () {
        Route::get('/', [CommunityController::class, 'index']);
        Route::post('/', [CommunityController::class, 'store']);
        Route::get('/{community}', [CommunityController::class, 'show']);
        Route::put('/{community}', [CommunityController::class, 'update']);
        Route::delete('/{community}', [CommunityController::class, 'destroy']);
        
        // Community ratings
        Route::post('/{community}/rate', [CommunityController::class, 'rateCommunity']);
        Route::get('/{community}/ratings', [CommunityController::class, 'getRatings']);
        
        // Community events
        Route::get('/{community}/events', [CommunityController::class, 'getEvents']);
    });

    // Notifications routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/stats', [NotificationController::class, 'getStats']);
        Route::get('/preferences', [NotificationController::class, 'getPreferences']);
        Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/read', [NotificationController::class, 'deleteAllRead']);
        Route::get('/{notification}', [NotificationController::class, 'show']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/{notification}/unread', [NotificationController::class, 'markAsUnread']);
    });

    // Match History routes
    Route::prefix('matches')->group(function () {
        Route::get('/', [MatchHistoryController::class, 'index']);
        Route::post('/', [MatchHistoryController::class, 'store']);
        Route::get('/stats/{user?}', [MatchHistoryController::class, 'getStats']);
        Route::get('/{match}', [MatchHistoryController::class, 'show']);
        Route::put('/{match}', [MatchHistoryController::class, 'update']);
        Route::delete('/{match}', [MatchHistoryController::class, 'destroy']);
    });

    // Player Ratings routes
    Route::prefix('ratings')->group(function () {
        Route::get('/', [PlayerRatingController::class, 'index']);
        Route::post('/', [PlayerRatingController::class, 'store']);
        Route::get('/stats/{user}', [PlayerRatingController::class, 'getUserStats']);
        Route::get('/{rating}', [PlayerRatingController::class, 'show']);
        Route::put('/{rating}', [PlayerRatingController::class, 'update']);
        Route::delete('/{rating}', [PlayerRatingController::class, 'destroy']);
        Route::post('/{rating}/report', [PlayerRatingController::class, 'reportRating']);
    });

    // Credit Score routes
    Route::prefix('credit-score')->group(function () {
        Route::get('/', [CreditScoreController::class, 'index']);
        Route::get('/summary', [CreditScoreController::class, 'getSummary']);
        Route::get('/restrictions', [CreditScoreController::class, 'getRestrictions']);
        Route::post('/cancel-event', [CreditScoreController::class, 'processCancellationPenalty']);
        Route::post('/no-show', [CreditScoreController::class, 'processNoShowPenalty']);
        Route::post('/completion-bonus', [CreditScoreController::class, 'processEventCompletionBonus']);
    });

    // Real-time routes
    Route::prefix('realtime')->group(function () {
        Route::post('/chat/send', [RealTimeController::class, 'sendChatMessage']);
        Route::post('/event/broadcast', [RealTimeController::class, 'broadcastEventUpdate']);
        Route::post('/notification/send', [RealTimeController::class, 'sendNotification']);
        Route::post('/auth', [RealTimeController::class, 'authenticate']);
        Route::get('/online-users', [RealTimeController::class, 'getOnlineUsers']);
    });

    // Sports routes (protected)
    Route::prefix('sports')->group(function () {
        Route::get('/{sport}', [SportController::class, 'show']);
        Route::get('/{sport}/events', [SportController::class, 'getEvents']);
        Route::get('/{sport}/communities', [SportController::class, 'getCommunities']);
    });

    // Location & Geo Services routes
    Route::prefix('location')->group(function () {
        // Preferred Areas Management
        Route::get('/preferred-areas', [LocationController::class, 'getUserPreferredAreas']);
        Route::post('/preferred-areas', [LocationController::class, 'addPreferredArea']);
        Route::put('/preferred-areas/{area}', [LocationController::class, 'updatePreferredArea']);
        Route::delete('/preferred-areas/{area}', [LocationController::class, 'deletePreferredArea']);
        
        // Location-based Search (200km radius)
        Route::post('/search/events', [LocationController::class, 'searchEventsByLocation']);
        Route::post('/search/communities', [LocationController::class, 'searchCommunitiesByLocation']);
        
        // Distance Calculation
        Route::post('/calculate-distance', [LocationController::class, 'calculateDistance']);
        
        // Events in Preferred Areas
        Route::get('/preferred-areas/events', [LocationController::class, 'getEventsInPreferredAreas']);
    });

    // User Reporting & Dispute System
    Route::prefix('reports')->group(function () {
        Route::post('/', [ReportController::class, 'submitReport']);
        Route::get('/my-reports', [ReportController::class, 'getMyReports']);
        Route::get('/against-me', [ReportController::class, 'getReportsAgainstMe']);
        Route::get('/stats', [ReportController::class, 'getReportStats']);
        Route::get('/{reportId}', [ReportController::class, 'getReportDetails']);
        Route::put('/{reportId}', [ReportController::class, 'updateReport']);
        Route::delete('/{reportId}', [ReportController::class, 'cancelReport']);
    });

    // Admin Management routes (admin role required)
    Route::prefix('admin')->group(function () {
        // Dashboard & Analytics
        Route::get('/dashboard', [AdminController::class, 'getDashboard']);
        Route::get('/analytics', [AdminController::class, 'getSystemAnalytics']);
        
        // User Management
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::get('/users/{userId}', [AdminController::class, 'getUserDetails']);
        Route::post('/users/{userId}/toggle-status', [AdminController::class, 'toggleUserStatus']);
        Route::post('/users/{userId}/adjust-credit', [AdminController::class, 'adjustCreditScore']);
        
        // Dispute & Report Management
        Route::get('/reports', [AdminController::class, 'getReports']);
        Route::post('/reports/{reportId}/assign', [AdminController::class, 'assignReport']);
        Route::post('/reports/{reportId}/resolve', [AdminController::class, 'resolveReport']);
        
        // Platform Match History & Analytics
        Route::get('/matches/history', [AdminController::class, 'getPlatformMatchHistory']);
        
        // Admin Activity Logs
        Route::get('/activities', [AdminController::class, 'getAdminActivities']);
    });

    // Matchmaking System
    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::get('matchmaking/{eventId}', [MatchmakingController::class, 'getMatchmakingStatus']);
        Route::post('matchmaking/{eventId}/generate', [MatchmakingController::class, 'generateEventMatchmaking']);
        Route::post('matchmaking/{eventId}/save', [MatchmakingController::class, 'saveMatchmaking']);
    });

    // Venue Management
    Route::group(['middleware' => 'auth:sanctum'], function () {
        // Public venue routes (authenticated users)
        Route::get('venues', [VenueController::class, 'index']);
        Route::get('venues/{id}', [VenueController::class, 'show']);
        Route::post('venues/{id}/check-availability', [VenueController::class, 'checkAvailability']);
        Route::get('venues/{id}/schedule', [VenueController::class, 'getSchedule']);
        
        // Venue management (host/admin only)
        Route::post('venues', [VenueController::class, 'store']);
        Route::put('venues/{id}', [VenueController::class, 'update']);
        Route::delete('venues/{id}', [VenueController::class, 'destroy']);
    });
}); 