<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'last_activity_at',
        'notes',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    // Relationships
    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeMembers($query)
    {
        return $query->where('role', 'member');
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', ['admin', 'moderator']);
    }

    // Helper methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isModerator()
    {
        return $this->role === 'moderator';
    }

    public function canManage()
    {
        return in_array($this->role, ['admin', 'moderator']);
    }

    public function updateLastActivity()
    {
        $this->update(['last_activity_at' => now()]);
    }
} 