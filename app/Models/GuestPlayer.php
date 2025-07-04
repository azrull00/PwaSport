namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class GuestPlayer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'temporary_id',
        'added_by',
        'event_id',
        'sport_id',
        'skill_level',
        'estimated_mmr',
        'is_active',
        'valid_until',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_until' => 'datetime',
        'estimated_mmr' => 'integer',
        'skill_level' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($guestPlayer) {
            // Generate temporary ID if not set
            if (!$guestPlayer->temporary_id) {
                $guestPlayer->temporary_id = 'GUEST_' . uniqid();
            }

            // Set expiration date if not set (event end + 1 day)
            if (!$guestPlayer->expires_at) {
                $event = Event::find($guestPlayer->event_id);
                if ($event) {
                    $guestPlayer->expires_at = Carbon::parse($event->end_time)->addDay();
                }
            }
        });
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function matchHistories()
    {
        return $this->hasMany(MatchHistory::class, 'guest_player_id');
    }

    public function eventParticipations()
    {
        return $this->hasMany(EventParticipant::class, 'guest_player_id');
    }

    // Convert guest player data to match registered player format for matchmaking
    public function toMatchmakingFormat()
    {
        return [
            'id' => 'guest_' . $this->id,
            'name' => $this->name . ' (Guest)',
            'mmr' => $this->estimated_mmr,
            'skill_level' => $this->skill_level,
            'win_rate' => null, // Guests don't have win rates
            'is_guest' => true,
            'matches_played' => 0,
        ];
    }

    // Scope for active guest players in an event
    public function scopeActiveInEvent($query, $eventId)
    {
        return $query->where('event_id', $eventId)
            ->where('is_active', true)
            ->where('valid_until', '>', now());
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    public function markAsCheckedIn()
    {
        $this->checked_in_at = Carbon::now();
        $this->save();
    }
} 