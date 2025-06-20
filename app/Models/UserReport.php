<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'report_type',
        'related_type',
        'related_id',
        'description',
        'evidence',
        'status',
        'priority',
        'assigned_admin_id',
        'assigned_to', // Alias for assigned_admin_id
        'admin_notes',
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'resolved_at' => 'datetime',
    ];

    // Accessor and mutator for backward compatibility with 'assigned_to'
    public function getAssignedToAttribute()
    {
        return $this->assigned_admin_id;
    }

    public function setAssignedToAttribute($value)
    {
        $this->attributes['assigned_admin_id'] = $value;
    }

    // Relationships
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    /**
     * Get the related model instance using polymorphic relationship style
     */
    public function relatedModel()
    {
        return $this->morphTo('related', 'related_type', 'related_id');
    }

    /**
     * Get the event if related_type is event
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'related_id')->where('related_type', 'event');
    }

    /**
     * Get the community if related_type is community
     */
    public function community()
    {
        return $this->belongsTo(Community::class, 'related_id')->where('related_type', 'community');
    }

    /**
     * Get the match history if related_type is match
     */
    public function match()
    {
        return $this->belongsTo(MatchHistory::class, 'related_id')->where('related_type', 'match');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeAssignedTo($query, $adminId)
    {
        return $query->where('assigned_admin_id', $adminId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_admin_id');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('report_type', $type);
    }

    // Helper methods
    public function assign($adminId)
    {
        $this->update([
            'assigned_admin_id' => $adminId,
            'status' => 'under_review'
        ]);

        // Log admin activity
        AdminActivity::logActivity(
            $adminId,
            'report_assigned',
            'report',
            $this->id,
            "Report assigned to admin"
        );
    }

    public function resolve($resolution, $adminId)
    {
        $oldStatus = $this->status;
        
        $this->update([
            'status' => 'resolved',
            'resolution' => $resolution,
            'resolved_at' => now(),
            'assigned_admin_id' => $adminId
        ]);

        // Log admin activity
        AdminActivity::logActivity(
            $adminId,
            'report_resolved',
            'report',
            $this->id,
            "Report resolved: {$resolution}",
            ['status' => $oldStatus],
            ['status' => 'resolved', 'resolution' => $resolution]
        );
    }

    public function dismiss($reason, $adminId)
    {
        $oldStatus = $this->status;
        
        $this->update([
            'status' => 'dismissed',
            'admin_notes' => $reason,
            'resolved_at' => now(),
            'assigned_admin_id' => $adminId
        ]);

        // Log admin activity
        AdminActivity::logActivity(
            $adminId,
            'report_dismissed',
            'report',
            $this->id,
            "Report dismissed: {$reason}",
            ['status' => $oldStatus],
            ['status' => 'dismissed', 'reason' => $reason]
        );
    }

    public function escalate($adminId)
    {
        $this->update([
            'status' => 'escalated',
            'priority' => 'urgent'
        ]);

        // Log admin activity
        AdminActivity::logActivity(
            $adminId,
            'report_escalated',
            'report',
            $this->id,
            "Report escalated to urgent priority"
        );
    }
}
