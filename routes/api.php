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
use App\Http\Controllers\Api\HostController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\PrivateMessageController;

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

// Public image access routes (no auth required)
Route::get('users/{user}/profile-picture', [UserController::class, 'getProfilePictureUrl']);
Route::get('communities/{community}/icon', [CommunityController::class, 'getIcon']);
Route::get('events/{event}/thumbnail', [EventController::class, 'getThumbnail']);

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
        
        // My events and QR code routes (must be before /{user} routes)
        Route::get('/my-events', [UserController::class, 'getMyEvents']);
        Route::get('/my-qr-code', [UserController::class, 'getMyQRCode']);
        Route::get('/my-matchmaking-status', [UserController::class, 'getMyMatchmakingStatus']);
        Route::get('/my-match-history', [UserController::class, 'getMyMatchHistory']);
        
        // Profile management
        Route::get('/profile', [UserController::class, 'getProfile']);
        Route::post('/profile', [UserController::class, 'updateProfile']);
        Route::get('/profile-picture', [UserController::class, 'getProfilePicture']);
        Route::post('/upload-profile-picture', [UserController::class, 'uploadProfilePicture']);
        Route::delete('/profile-picture', [UserController::class, 'deleteProfilePicture']);
        
        // Public profile access
        Route::get('/{userId}/profile', [UserController::class, 'getPublicProfile']);
        
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
        Route::get('/recommendations', [EventController::class, 'getRecommendations']);
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
        
        // QR-based check-in (host only)
        Route::post('/{event}/check-in-qr', [EventController::class, 'checkInParticipantByQR']);
        Route::post('/{event}/bulk-check-in', [EventController::class, 'bulkCheckInParticipants']);
        Route::get('/{event}/check-in-stats', [EventController::class, 'getCheckInStats']);
        
        // Event thumbnail management (host only)
        Route::post('/{event}/upload-thumbnail', [EventController::class, 'uploadThumbnail']);
        Route::delete('/{event}/delete-thumbnail', [EventController::class, 'deleteThumbnail']);
    });

    // Communities routes
    Route::prefix('communities')->group(function () {
        Route::get('/', [CommunityController::class, 'index']);
        Route::get('/my-communities', [CommunityController::class, 'getMyCommunities']);
        Route::post('/', [CommunityController::class, 'store']);
        Route::get('/{community}', [CommunityController::class, 'show']);
        Route::put('/{community}', [CommunityController::class, 'update']);
        Route::delete('/{community}', [CommunityController::class, 'destroy']);
        
        // Community ratings
        Route::post('/{community}/rate', [CommunityController::class, 'rateCommunity']);
        Route::get('/{community}/ratings', [CommunityController::class, 'getRatings']);
        Route::get('/{community}/user-past-events', [CommunityController::class, 'getUserPastEvents']);
        
        // Community events
        Route::get('/{community}/events', [CommunityController::class, 'getEvents']);
        
        // Community membership
        Route::get('/{community}/members', [CommunityController::class, 'getMembers']);
        Route::post('/{community}/join', [CommunityController::class, 'joinCommunity']);
        Route::delete('/{community}/leave', [CommunityController::class, 'leaveCommunity']);
        
        // Community messages/chat
        Route::get('/{community}/messages', [CommunityController::class, 'getMessages']);
        Route::post('/{community}/messages', [CommunityController::class, 'sendMessage']);
        
        // Community icon management (host only)
        Route::post('/{community}/upload-icon', [CommunityController::class, 'uploadIcon']);
        Route::delete('/{community}/delete-icon', [CommunityController::class, 'deleteIcon']);

        // Member level and ranking routes
        Route::patch('/{community}/members/{user}/level', [CommunityController::class, 'updateMemberLevel']);
        Route::post('/{community}/members/{user}/points', [CommunityController::class, 'addMemberPoints']);
        Route::get('/{community}/rankings', [CommunityController::class, 'getMemberRankings']);
        Route::post('/{community}/rankings/refresh', [CommunityController::class, 'refreshRankings']);
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

    // Location Routes
    Route::prefix('location')->group(function () {
        // Existing routes
        Route::get('/preferred-areas', [LocationController::class, 'getUserPreferredAreas']);
        Route::post('/preferred-areas', [LocationController::class, 'addPreferredArea']);
        Route::put('/preferred-areas/{area}', [LocationController::class, 'updatePreferredArea']);
        Route::delete('/preferred-areas/{area}', [LocationController::class, 'deletePreferredArea']);

        // New location tracking routes
        Route::post('/location/current', [LocationController::class, 'updateCurrentLocation']);
        Route::get('/location/current', [LocationController::class, 'getCurrentLocation']);

        // Existing search routes
        Route::post('/search/events', [LocationController::class, 'searchEventsByLocation']);
        Route::post('/search/communities', [LocationController::class, 'searchCommunitiesByLocation']);
        Route::post('/calculate-distance', [LocationController::class, 'calculateDistance']);
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

    // Friend System routes
    Route::prefix('friends')->group(function () {
        // Friends list and management
        Route::get('/', [FriendController::class, 'getFriends']);
        Route::delete('/{friendId}', [FriendController::class, 'removeFriend']);
        
        // Friend requests
        Route::post('/request', [FriendController::class, 'sendFriendRequest']);
        Route::get('/requests/pending', [FriendController::class, 'getPendingRequests']);
        Route::get('/requests/sent', [FriendController::class, 'getSentRequests']);
        Route::post('/requests/{requestId}/accept', [FriendController::class, 'acceptFriendRequest']);
        Route::post('/requests/{requestId}/reject', [FriendController::class, 'rejectFriendRequest']);
        Route::delete('/requests/{requestId}', [FriendController::class, 'cancelFriendRequest']);
        
        // Friendship status and search
        Route::get('/status/{userId}', [FriendController::class, 'getFriendshipStatus']);
        Route::get('/search', [FriendController::class, 'searchUsers']);
    });

    // Private Messaging routes
    Route::prefix('messages')->group(function () {
        // Conversations
        Route::get('/conversations', [PrivateMessageController::class, 'getConversations']);
        Route::get('/conversations/{userId}', [PrivateMessageController::class, 'getConversation']);
        Route::delete('/conversations/{userId}', [PrivateMessageController::class, 'deleteConversation']);
        
        // Messages
        Route::post('/send', [PrivateMessageController::class, 'sendMessage']);
        Route::post('/{messageId}/read', [PrivateMessageController::class, 'markAsRead']);
        Route::post('/read-all/{userId}', [PrivateMessageController::class, 'markAllAsRead']);
        Route::delete('/{messageId}', [PrivateMessageController::class, 'deleteMessage']);
        
        // Unread count
        Route::get('/unread-count', [PrivateMessageController::class, 'getUnreadCount']);
    });

    // Host Routes
    Route::middleware(['auth:sanctum', 'role:host'])->prefix('host')->group(function () {
        Route::get('/dashboard/stats', [HostController::class, 'getDashboardStats']);
        Route::get('/analytics', [HostController::class, 'getHostAnalytics']);
    });

    // Host Matchmaking Routes (legacy - kept for compatibility)
    Route::middleware(['auth:sanctum', 'role:host'])->prefix('host/matchmaking')->group(function () {
        Route::get('/{eventId}/participants', [MatchmakingController::class, 'getEventParticipants']);
        Route::get('/{eventId}/matches', [MatchmakingController::class, 'getEventMatches']);
        Route::post('/{eventId}/override', [MatchmakingController::class, 'overrideMatch']);
        Route::post('/{eventId}/matches/{matchId}/lock', [MatchmakingController::class, 'toggleMatchLock']);
    });

    // Host Community Player Management Routes
    Route::middleware(['auth:sanctum', 'role:host'])->prefix('host/communities')->group(function () {
        Route::get('/{communityId}/players', [CommunityController::class, 'getCommunityPlayers']);
        Route::post('/{communityId}/players/{playerId}/level', [CommunityController::class, 'updatePlayerLevel']);
        Route::post('/{communityId}/players/{playerId}/status', [CommunityController::class, 'updatePlayerStatus']);
    });

    // Host Management Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        // Host Dashboard
        Route::get('/host/dashboard/stats', 'Api\HostController@getDashboardStats');
        Route::get('/host/analytics', 'Api\HostController@getHostAnalytics');
        
        // Venue Management
        Route::get('/host/venues', 'Api\HostController@getVenues');
        Route::post('/host/venues', 'Api\HostController@createVenue');
        Route::put('/host/venues/{venueId}', 'Api\HostController@updateVenue');
        Route::delete('/host/venues/{venueId}', 'Api\HostController@deleteVenue');
        Route::get('/host/venues/{venueId}/stats', 'Api\HostController@getVenueStats');
        Route::get('/host/venues/{venueId}/matchmaking-status', 'Api\HostController@getVenueMatchmakingStatus');
        
        // Court Management
        Route::get('/host/venues/{venue}/courts', 'Api\HostController@getCourts');
        Route::put('/host/courts/{courtId}/status', 'Api\HostController@updateCourtStatus');
        Route::post('/host/courts/{courtId}/assign-match', 'Api\HostController@assignMatch');
        
        // Community Management
        Route::get('/host/communities/{communityId}/stats', 'Api\HostController@getCommunityStats');
        Route::put('/host/communities/{communityId}/settings', 'Api\HostController@updateCommunitySettings');
        Route::post('/host/communities/{communityId}/members/{memberId}/manage', 'Api\HostController@manageMemberRequest');
        
        // Guest Player Management
        Route::get('/host/events/{event}/guest-players', 'Api\HostController@listGuestPlayers');
        Route::post('/host/events/{event}/guest-players', 'Api\HostController@addGuestPlayer');
        Route::put('/host/events/{event}/guest-players/{guestPlayer}', 'Api\HostController@updateGuestPlayer');
        Route::delete('/host/events/{event}/guest-players/{guestPlayer}', 'Api\HostController@removeGuestPlayer');

        // QR Code Check-in
        Route::post('/host/events/{event}/check-in/qr', 'Api\HostController@processQRCheckIn');
        Route::post('/host/events/{event}/generate-qr', 'Api\HostController@generateCheckInQR');
    });

    // Matchmaking Management (New standardized routes using Event model dependency injection)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/matchmaking/{event}/status', [MatchmakingController::class, 'getStatus']);
        Route::post('/matchmaking/{event}/fair-matches', [MatchmakingController::class, 'createFairMatches']);
        Route::post('/matchmaking/{event}/override-player', [MatchmakingController::class, 'overridePlayer']);
        Route::post('/matchmaking/{event}/assign-court', [MatchmakingController::class, 'assignCourt']);
    });
}); 