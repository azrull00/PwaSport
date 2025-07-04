<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\User;

class CommunityPolicy
{
    public function manageCommunity(User $user, Community $community)
    {
        return $user->id === $community->host_id || 
               $community->members()->where('user_id', $user->id)
                   ->whereIn('role', ['admin', 'moderator'])
                   ->exists();
    }

    public function manageMemberLevels(User $user, Community $community)
    {
        // Only community host and admins can manage member levels
        return $user->id === $community->host_id || 
               $community->members()->where('user_id', $user->id)
                   ->where('role', 'admin')
                   ->exists();
    }

    public function updateCommunity(User $user, Community $community)
    {
        return $this->manageCommunity($user, $community);
    }

    public function deleteCommunity(User $user, Community $community)
    {
        return $user->id === $community->host_id;
    }

    public function createEvent(User $user, Community $community)
    {
        return $community->members()->where('user_id', $user->id)
            ->whereIn('role', ['admin', 'moderator', 'member'])
            ->where('status', 'active')
            ->exists();
    }

    public function manageEvents(User $user, Community $community)
    {
        return $this->manageCommunity($user, $community);
    }

    public function manageMembers(User $user, Community $community)
    {
        return $this->manageCommunity($user, $community);
    }
} 