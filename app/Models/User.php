<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'subscription_tier',
        'phone_number',
        'credit_score',
        'email_verified_at',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be appended to the model.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'is_host'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_host' => 'boolean',
        ];
    }

    /**
     * Get the is_host attribute.
     *
     * @return bool
     */
    public function getIsHostAttribute(): bool
    {
        return $this->hasRole('host');
    }

    // Relationships
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function sportRatings()
    {
        return $this->hasMany(UserSportRating::class);
    }

    public function hostedCommunities()
    {
        return $this->hasMany(Community::class, 'host_user_id');
    }

    public function hostedEvents()
    {
        return $this->hasMany(Event::class, 'host_id');
    }

    public function eventParticipations()
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function participatedEvents()
    {
        return $this->belongsToMany(Event::class, 'event_participants')
            ->withPivot('status', 'queue_position', 'registered_at', 'checked_in_at')
            ->withTimestamps();
    }

    public function events()
    {
        return $this->participatedEvents();
    }

    public function matchHistory()
    {
        return $this->hasMany(MatchHistory::class, 'player1_id')
            ->orWhere('player2_id', $this->id);
    }

    public function matchesAsPlayer1()
    {
        return $this->hasMany(MatchHistory::class, 'player1_id');
    }

    public function matchesAsPlayer2()
    {
        return $this->hasMany(MatchHistory::class, 'player2_id');
    }

    public function allMatches()
    {
        return MatchHistory::where('player1_id', $this->id)
            ->orWhere('player2_id', $this->id);
    }

    public function matches()
    {
        return $this->allMatches();
    }

    public function hasActiveMatch()
    {
        return MatchHistory::where(function($query) {
            $query->where('player1_id', $this->id)
                  ->orWhere('player2_id', $this->id);
        })->whereIn('match_status', ['scheduled', 'ongoing'])->exists();
    }

    public function givenRatings()
    {
        return $this->hasMany(PlayerRating::class, 'rating_user_id');
    }

    public function receivedRatings()
    {
        return $this->hasMany(PlayerRating::class, 'rated_user_id');
    }

    public function communityRatings()
    {
        return $this->hasMany(CommunityRating::class);
    }

    public function creditScoreLogs()
    {
        return $this->hasMany(CreditScoreLog::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->whereNull('read_at');
    }

    public function readNotifications()
    {
        return $this->hasMany(Notification::class)->whereNotNull('read_at');
    }

    public function blockingUsers()
    {
        return $this->hasMany(UserBlock::class, 'blocking_user_id');
    }

    public function blockedByUsers()
    {
        return $this->hasMany(UserBlock::class, 'blocked_user_id');
    }

    // New Geo-Location & Admin relationships
    public function preferredAreas()
    {
        return $this->hasMany(UserPreferredArea::class);
    }

    public function adminActivities()
    {
        return $this->hasMany(AdminActivity::class, 'admin_id');
    }

    public function reportsMade()
    {
        return $this->hasMany(UserReport::class, 'reporter_id');
    }

    public function reportsReceived()
    {
        return $this->hasMany(UserReport::class, 'reported_user_id');
    }

    // Community relationships
    public function communityMemberships()
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function activeCommunityMemberships()
    {
        return $this->hasMany(CommunityMember::class)->active();
    }

    public function joinedCommunities()
    {
        return $this->belongsToMany(Community::class, 'community_members')
            ->withPivot('role', 'status', 'joined_at', 'last_activity_at')
            ->withTimestamps();
    }

    public function activeCommunities()
    {
        return $this->belongsToMany(Community::class, 'community_members')
            ->wherePivot('status', 'active')
            ->withPivot('role', 'status', 'joined_at', 'last_activity_at')
            ->withTimestamps();
    }

    public function communityMessages()
    {
        return $this->hasMany(CommunityMessage::class);
    }

    public function assignedReports()
    {
        return $this->hasMany(UserReport::class, 'assigned_admin_id');
    }

    // Friendship relationships
    public function friendships()
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    public function reverseFriendships()
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    public function friends()
    {
        // Get all accepted friendships where user is either user_id or friend_id
        $userFriends = $this->friendships()->accepted()->with('friend');
        $reverseFriends = $this->reverseFriendships()->accepted()->with('user');
        
        return $userFriends->get()->pluck('friend')
            ->merge($reverseFriends->get()->pluck('user'))
            ->unique('id');
    }

    public function sentFriendRequests()
    {
        return $this->hasMany(FriendRequest::class, 'sender_id');
    }

    public function receivedFriendRequests()
    {
        return $this->hasMany(FriendRequest::class, 'receiver_id');
    }

    public function pendingFriendRequests()
    {
        return $this->receivedFriendRequests()->pending();
    }

    // Private Messages relationships
    public function sentMessages()
    {
        return $this->hasMany(PrivateMessage::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(PrivateMessage::class, 'receiver_id');
    }

    public function unreadMessages()
    {
        return $this->receivedMessages()->unread();
    }

    // Helper methods
    public function getSportRating($sportId)
    {
        return $this->sportRatings()->where('sport_id', $sportId)->first();
    }

    public function hasBlockedUser($userId)
    {
        return $this->blockingUsers()->where('blocked_user_id', $userId)->exists();
    }

    public function isBlockedByUser($userId)
    {
        return $this->blockedByUsers()->where('blocking_user_id', $userId)->exists();
    }

    // Friendship helper methods
    public function isFriendsWith($userId)
    {
        return Friendship::areFriends($this->id, $userId);
    }

    public function hasPendingFriendRequestFrom($userId)
    {
        return FriendRequest::hasPendingRequest($userId, $this->id);
    }

    public function hasSentFriendRequestTo($userId)
    {
        return FriendRequest::hasPendingRequest($this->id, $userId);
    }

    public function getFriendshipStatusWith($userId)
    {
        return Friendship::getFriendshipStatus($this->id, $userId);
    }

    public function getFriendRequestStatusWith($userId)
    {
        // Check if this user sent a request to the other user
        $sentRequest = FriendRequest::getRequestStatus($this->id, $userId);
        if ($sentRequest) {
            return ['type' => 'sent', 'status' => $sentRequest];
        }

        // Check if this user received a request from the other user
        $receivedRequest = FriendRequest::getRequestStatus($userId, $this->id);
        if ($receivedRequest) {
            return ['type' => 'received', 'status' => $receivedRequest];
        }

        return null;
    }

    public function canSendFriendRequestTo($userId)
    {
        // Can't send to self
        if ($this->id == $userId) {
            return false;
        }

        // Can't send if already friends
        if ($this->isFriendsWith($userId)) {
            return false;
        }

        // Can't send if there's already a pending request (either direction)
        if ($this->hasPendingFriendRequestFrom($userId) || $this->hasSentFriendRequestTo($userId)) {
            return false;
        }

        // Can't send if user is blocked
        if ($this->hasBlockedUser($userId) || $this->isBlockedByUser($userId)) {
            return false;
        }

        return true;
    }
}
