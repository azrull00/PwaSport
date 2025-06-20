<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id',
        'user_id',
        'message',
        'message_type',
        'file_path',
        'file_name',
        'file_size',
        'is_edited',
        'edited_at',
        'is_deleted',
        'deleted_at',
        'metadata',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
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
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('message_type', $type);
    }

    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // Helper methods
    public function isImage()
    {
        return $this->message_type === 'image';
    }

    public function isFile()
    {
        return $this->message_type === 'file';
    }

    public function isText()
    {
        return $this->message_type === 'text';
    }

    public function isSystem()
    {
        return $this->message_type === 'system';
    }

    public function canEdit(User $user)
    {
        return $this->user_id === $user->id && !$this->is_deleted;
    }

    public function canDelete(User $user)
    {
        // User can delete their own message, or community admin/moderator can delete any message
        if ($this->user_id === $user->id) {
            return true;
        }

        $membership = CommunityMember::where('community_id', $this->community_id)
            ->where('user_id', $user->id)
            ->first();

        return $membership && $membership->canManage();
    }

    public function markAsEdited()
    {
        $this->update([
            'is_edited' => true,
            'edited_at' => now()
        ]);
    }

    public function markAsDeleted()
    {
        $this->update([
            'is_deleted' => true,
            'deleted_at' => now()
        ]);
    }

    public function getFileUrl()
    {
        if (!$this->file_path) {
            return null;
        }

        return asset('storage/' . $this->file_path);
    }

    public function getFileSizeFormatted()
    {
        if (!$this->file_size) {
            return null;
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
} 