<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'action_type',
        'target_type',
        'target_id',
        'description',
        'old_data',
        'new_data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    // Relationships
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get the target model instance
     */
    public function target()
    {
        switch ($this->target_type) {
            case 'user':
                return $this->belongsTo(User::class, 'target_id');
            case 'event':
                return $this->belongsTo(Event::class, 'target_id');
            case 'community':
                return $this->belongsTo(Community::class, 'target_id');
            case 'match':
                return $this->belongsTo(MatchHistory::class, 'target_id');
            case 'report':
                return $this->belongsTo(UserReport::class, 'target_id');
            default:
                return null;
        }
    }

    // Scopes
    public function scopeByAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeByActionType($query, $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    public function scopeByTargetType($query, $targetType)
    {
        return $query->where('target_type', $targetType);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public static function logActivity($adminId, $actionType, $targetType, $targetId, $description, $oldData = null, $newData = null)
    {
        return self::create([
            'admin_id' => $adminId,
            'action_type' => $actionType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'description' => $description,
            'old_data' => $oldData,
            'new_data' => $newData,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
