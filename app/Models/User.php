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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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
        return $this->hasMany(Event::class, 'host_user_id');
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

    public function matchHistory()
    {
        return $this->hasMany(MatchHistory::class, 'player1_id')
            ->orWhere('player2_id', $this->id);
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

    public function assignedReports()
    {
        return $this->hasMany(UserReport::class, 'assigned_admin_id');
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
}
