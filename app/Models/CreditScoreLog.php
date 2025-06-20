<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditScoreLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'type',
        'old_score',
        'new_score',
        'change_amount',
        'description',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'old_score' => 'integer',
        'new_score' => 'integer',
        'change_amount' => 'integer',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
