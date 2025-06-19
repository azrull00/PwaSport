<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MatchHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'player1_id',
        'player2_id',
        'player1_score',
        'player2_score',
        'result',
        'winner_id',
        'match_date',
        'match_duration_minutes',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'match_date' => 'date',
        'player1_score' => 'integer',
        'player2_score' => 'integer',
        'match_duration_minutes' => 'integer',
    ];

    // Relationships
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function player1()
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2()
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Helper methods
    public function getOpponent($userId)
    {
        if ($this->player1_id == $userId) {
            return $this->player2;
        } elseif ($this->player2_id == $userId) {
            return $this->player1;
        }
        return null;
    }

    public function getUserScore($userId)
    {
        if ($this->player1_id == $userId) {
            return $this->player1_score;
        } elseif ($this->player2_id == $userId) {
            return $this->player2_score;
        }
        return null;
    }

    public function getOpponentScore($userId)
    {
        if ($this->player1_id == $userId) {
            return $this->player2_score;
        } elseif ($this->player2_id == $userId) {
            return $this->player1_score;
        }
        return null;
    }

    public function didUserWin($userId)
    {
        return $this->winner_id == $userId;
    }

    public function getResultForUser($userId)
    {
        if ($this->winner_id == $userId) {
            return 'win';
        } elseif ($this->winner_id && $this->winner_id != $userId) {
            return 'loss';
        } else {
            return 'draw';
        }
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('player1_id', $userId)
              ->orWhere('player2_id', $userId);
        });
    }

    public function scopeWins($query, $userId)
    {
        return $query->where('winner_id', $userId);
    }

    public function scopeLosses($query, $userId)
    {
        return $query->where('winner_id', '!=', $userId)
                     ->whereNotNull('winner_id')
                     ->where(function($q) use ($userId) {
                         $q->where('player1_id', $userId)
                           ->orWhere('player2_id', $userId);
                     });
    }

    public function scopeDraws($query, $userId)
    {
        return $query->where('result', 'draw')
                     ->where(function($q) use ($userId) {
                         $q->where('player1_id', $userId)
                           ->orWhere('player2_id', $userId);
                     });
    }
}
