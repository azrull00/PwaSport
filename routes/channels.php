<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Channel untuk chat event
Broadcast::channel('event-chat.{eventId}', function ($user, $eventId) {
    // User bisa join channel jika mereka participant dari event
    return \App\Models\EventParticipant::where('user_id', $user->id)
        ->where('event_id', $eventId)
        ->exists();
});

// Channel untuk chat community
Broadcast::channel('community-chat.{communityId}', function ($user, $communityId) {
    // User bisa join channel jika mereka member dari community
    return \App\Models\Community::where('id', $communityId)
        ->where(function($query) use ($user) {
            $query->where('creator_id', $user->id)
                  ->orWhereHas('members', function($memberQuery) use ($user) {
                      $memberQuery->where('user_id', $user->id);
                  });
        })->exists();
});

// Channel untuk private notifications (optional, jika dibutuhkan di masa depan)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Channel untuk event updates
Broadcast::channel('event-updates.{eventId}', function ($user, $eventId) {
    // User bisa mendengar update event jika mereka participant
    return \App\Models\EventParticipant::where('user_id', $user->id)
        ->where('event_id', $eventId)
        ->exists();
}); 