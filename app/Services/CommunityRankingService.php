<?php

namespace App\Services;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\ChatMessage;
use App\Models\MatchHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommunityRankingService
{
    private const ACTIVITY_WEIGHTS = [
        'event_participation' => 0.4,  // 40% for event participation
        'chat_activity' => 0.2,        // 20% for community engagement
        'match_performance' => 0.2,    // 20% for match results
        'level_points' => 0.2          // 20% for host-assigned level
    ];

    public function calculateMemberRanking(Community $community, User $user)
    {
        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$member) {
            return null;
        }

        // Calculate activity score (40%)
        $activityScore = $this->calculateActivityScore($community, $user);

        // Calculate level points (20%)
        $levelPoints = $this->calculateLevelPoints($member->level);

        // Calculate chat activity (20%)
        $chatScore = $this->calculateChatActivity($community, $user);

        // Calculate match performance (20%)
        $performanceScore = $this->calculateMatchPerformance($community, $user);

        // Calculate total ranking score
        $totalScore = ($activityScore * 0.4) + 
                     ($levelPoints * 0.2) + 
                     ($chatScore * 0.2) + 
                     ($performanceScore * 0.2);

        // Update member's ranking score
        $member->ranking_score = $totalScore;
        $member->save();

        return [
            'total_score' => round($totalScore, 2),
            'details' => [
                'activity_score' => round($activityScore, 2),
                'level_points' => round($levelPoints, 2),
                'chat_score' => round($chatScore, 2),
                'performance_score' => round($performanceScore, 2)
            ]
        ];
    }

    private function calculateActivityScore(Community $community, User $user)
    {
        // Get events in the last 30 days
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $totalEvents = Event::where('community_id', $community->id)
            ->where('start_time', '>=', $thirtyDaysAgo)
            ->count();

        if ($totalEvents === 0) {
            return 0;
        }

        $participatedEvents = EventParticipant::whereHas('event', function ($query) use ($community, $thirtyDaysAgo) {
            $query->where('community_id', $community->id)
                ->where('start_time', '>=', $thirtyDaysAgo);
        })
        ->where('user_id', $user->id)
        ->where('status', 'completed')
        ->count();

        // Calculate participation rate (0-100)
        $participationRate = ($participatedEvents / $totalEvents) * 100;

        return min(100, $participationRate);
    }

    private function calculateLevelPoints($level)
    {
        $levelPoints = [
            'beginner' => 20,
            'intermediate' => 40,
            'advanced' => 60,
            'expert' => 80,
            'professional' => 100
        ];

        return $levelPoints[$level] ?? 0;
    }

    private function calculateChatActivity(Community $community, User $user)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Get total messages in community
        $totalMessages = ChatMessage::where('community_id', $community->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        if ($totalMessages === 0) {
            return 0;
        }

        // Get user's messages
        $userMessages = ChatMessage::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        // Calculate relative activity (0-100)
        $averageMessages = $totalMessages / $community->members()->count();
        $activityRate = ($userMessages / $averageMessages) * 50;

        // Cap at 100
        return min(100, $activityRate);
    }

    private function calculateMatchPerformance(Community $community, User $user)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Get matches in community events
        $matches = DB::table('match_histories')
            ->join('events', 'match_histories.event_id', '=', 'events.id')
            ->where('events.community_id', $community->id)
            ->where('match_histories.created_at', '>=', $thirtyDaysAgo)
            ->where(function ($query) use ($user) {
                $query->where('player1_id', $user->id)
                    ->orWhere('player2_id', $user->id);
            })
            ->where('match_status', 'completed')
            ->get();

        if ($matches->isEmpty()) {
            return 0;
        }

        $totalMatches = $matches->count();
        $wins = $matches->filter(function ($match) use ($user) {
            return ($match->player1_id === $user->id && $match->winner_id === $user->id) ||
                   ($match->player2_id === $user->id && $match->winner_id === $user->id);
        })->count();

        // Calculate win rate (0-100)
        $winRate = ($wins / $totalMatches) * 100;

        return $winRate;
    }

    public function updateCommunityRankings(Community $community)
    {
        $members = CommunityMember::where('community_id', $community->id)->get();

        foreach ($members as $member) {
            $this->calculateMemberRanking($community, $member->user);
        }

        // Update rankings based on total scores
        $rankedMembers = CommunityMember::where('community_id', $community->id)
            ->orderByDesc('ranking_score')
            ->get();

        $rank = 1;
        foreach ($rankedMembers as $member) {
            $member->rank = $rank++;
            $member->save();
        }

        return $rankedMembers;
    }

    public function updateMemberRankings(Community $community)
    {
        try {
            DB::beginTransaction();

            // Get all active members
            $members = CommunityMember::where('community_id', $community->id)
                ->where('status', 'active')
                ->get();

            // Calculate scores for each member
            $memberScores = $members->map(function ($member) use ($community) {
                return [
                    'member_id' => $member->id,
                    'user_id' => $member->user_id,
                    'total_score' => $this->calculateTotalScore($member, $community),
                    'level_points' => $member->level_points,
                    'activity_score' => $member->activity_score
                ];
            });

            // Sort by total score and assign rankings
            $rankedMembers = $memberScores->sortByDesc('total_score')->values();
            
            foreach ($rankedMembers as $index => $member) {
                CommunityMember::where('id', $member['member_id'])->update([
                    'ranking' => $index + 1
                ]);
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function calculateTotalScore(CommunityMember $member, Community $community)
    {
        $eventScore = $this->calculateEventParticipationScore($member, $community);
        $chatScore = $this->calculateChatActivityScore($member);
        $performanceScore = $this->calculateMatchPerformanceScore($member, $community);
        $levelScore = $this->calculateLevelScore($member);

        return (
            $eventScore * self::ACTIVITY_WEIGHTS['event_participation'] +
            $chatScore * self::ACTIVITY_WEIGHTS['chat_activity'] +
            $performanceScore * self::ACTIVITY_WEIGHTS['match_performance'] +
            $levelScore * self::ACTIVITY_WEIGHTS['level_points']
        );
    }

    private function calculateEventParticipationScore(CommunityMember $member, Community $community)
    {
        // Count events participated in last 30 days
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $eventCount = Event::where('community_id', $community->id)
            ->whereHas('participants', function ($query) use ($member) {
                $query->where('user_id', $member->user_id)
                    ->where('status', 'attended');
            })
            ->where('event_date', '>=', $thirtyDaysAgo)
            ->count();

        // Score based on participation (max 100)
        return min(100, $eventCount * 20);
    }

    private function calculateChatActivityScore(CommunityMember $member)
    {
        // Calculate chat activity in last 30 days
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $messageCount = $member->communityMessages()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        // Score based on message count (max 100)
        return min(100, $messageCount * 5);
    }

    private function calculateMatchPerformanceScore(CommunityMember $member, Community $community)
    {
        // Get matches from last 30 days
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $matches = MatchHistory::whereHas('event', function ($query) use ($community) {
            $query->where('community_id', $community->id);
        })
        ->where(function ($query) use ($member) {
            $query->where('player1_id', $member->user_id)
                ->orWhere('player2_id', $member->user_id);
        })
        ->where('created_at', '>=', $thirtyDaysAgo)
        ->get();

        if ($matches->isEmpty()) {
            return 50; // Neutral score for no matches
        }

        // Calculate win rate
        $totalMatches = $matches->count();
        $wonMatches = $matches->filter(function ($match) use ($member) {
            return ($match->player1_id === $member->user_id && $match->winner_id === $member->user_id) ||
                   ($match->player2_id === $member->user_id && $match->winner_id === $member->user_id);
        })->count();

        $winRate = ($wonMatches / $totalMatches) * 100;
        
        // Score based on win rate and match count
        $baseScore = $winRate;
        $matchCountBonus = min(20, $totalMatches * 2); // Up to 20 bonus points for playing matches
        
        return min(100, $baseScore + $matchCountBonus);
    }

    private function calculateLevelScore(CommunityMember $member)
    {
        // Convert level to numeric score
        $levelScores = [
            'beginner' => 20,
            'intermediate' => 40,
            'advanced' => 60,
            'expert' => 80,
            'professional' => 100
        ];

        $baseScore = $levelScores[$member->level] ?? 20;
        
        // Add bonus from level points (max 20 bonus points)
        $levelPointsBonus = min(20, $member->level_points / 5);
        
        return $baseScore + $levelPointsBonus;
    }

    public function updateMemberLevel(CommunityMember $member, string $newLevel)
    {
        if (!in_array($newLevel, ['beginner', 'intermediate', 'advanced', 'expert', 'professional'])) {
            return false;
        }

        try {
            DB::beginTransaction();

            $member->update([
                'level' => $newLevel,
                'level_points' => 0 // Reset level points on level change
            ]);

            // Recalculate rankings after level change
            $this->updateMemberRankings($member->community);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function addLevelPoints(CommunityMember $member, int $points)
    {
        try {
            DB::beginTransaction();

            $member->increment('level_points', $points);

            // Check if member should level up based on points
            $this->checkForLevelUp($member);

            // Update rankings
            $this->updateMemberRankings($member->community);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    private function checkForLevelUp(CommunityMember $member)
    {
        $levelThresholds = [
            'beginner' => 100,
            'intermediate' => 200,
            'advanced' => 300,
            'expert' => 400
        ];

        $currentLevel = $member->level;
        $nextLevel = $this->getNextLevel($currentLevel);

        if ($nextLevel && isset($levelThresholds[$currentLevel])) {
            if ($member->level_points >= $levelThresholds[$currentLevel]) {
                $this->updateMemberLevel($member, $nextLevel);
            }
        }
    }

    private function getNextLevel(string $currentLevel)
    {
        $levels = [
            'beginner' => 'intermediate',
            'intermediate' => 'advanced',
            'advanced' => 'expert',
            'expert' => 'professional'
        ];

        return $levels[$currentLevel] ?? null;
    }
} 