<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MatchHistory extends Model
{
    use HasFactory;

    protected $table = 'match_history';

    protected $fillable = [
        'event_id',
        'sport_id',
        'player1_id',
        'player2_id',
        'result',
        'match_score',
        'player1_mmr_before',
        'player1_mmr_after',
        'player2_mmr_before',
        'player2_mmr_after',
        'recorded_by_host_id',
        'match_notes',
        'match_date',
        'court_number',
        'estimated_duration',
        'match_status',
    ];

    protected $casts = [
        'match_date' => 'datetime',
        'match_score' => 'array',
        'player1_mmr_before' => 'integer',
        'player1_mmr_after' => 'integer',
        'player2_mmr_before' => 'integer',
        'player2_mmr_after' => 'integer',
    ];

    // Relationships
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function player1()
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2()
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    public function recordedByHost()
    {
        return $this->belongsTo(User::class, 'recorded_by_host_id');
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
        $scores = $this->match_score ?? [];
        if ($this->player1_id == $userId) {
            return $scores['player1_score'] ?? null;
        } elseif ($this->player2_id == $userId) {
            return $scores['player2_score'] ?? null;
        }
        return null;
    }

    public function getOpponentScore($userId)
    {
        $scores = $this->match_score ?? [];
        if ($this->player1_id == $userId) {
            return $scores['player2_score'] ?? null;
        } elseif ($this->player2_id == $userId) {
            return $scores['player1_score'] ?? null;
        }
        return null;
    }

    public function didUserWin($userId)
    {
        if ($this->player1_id == $userId && $this->result === 'player1_win') {
            return true;
        } elseif ($this->player2_id == $userId && $this->result === 'player2_win') {
            return true;
        }
        return false;
    }

    public function getResultForUser($userId)
    {
        if ($this->result === 'draw') {
            return 'draw';
        } elseif ($this->didUserWin($userId)) {
            return 'win';
        } else {
            return 'loss';
        }
    }

    public function getWinnerId()
    {
        switch ($this->result) {
            case 'player1_win':
                return $this->player1_id;
            case 'player2_win':
                return $this->player2_id;
            default:
                return null; // draw or unknown
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
        return $query->where(function($q) use ($userId) {
            $q->where('player1_id', $userId)->where('result', 'player1_win')
              ->orWhere('player2_id', $userId)->where('result', 'player2_win');
        });
    }

    public function scopeLosses($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('player1_id', $userId)->where('result', 'player2_win')
              ->orWhere('player2_id', $userId)->where('result', 'player1_win');
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
