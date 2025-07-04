<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Models\UserSportRating;
use App\Models\MatchHistory;
use App\Models\GuestPlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MatchmakingService
{
    private $levelWeight = 0.40;
    private $mmrWeight = 0.35;
    private $winRateWeight = 0.15;
    private $waitingTimeWeight = 0.10;

    /**
     * Fair Matchmaking Algorithm
     * Factors: MMR, Win Rate, Waiting Time, Premium Protection
     */
    public function createFairMatches(Event $event)
    {
        try {
            DB::beginTransaction();

            $participants = $this->getEligibleParticipants($event);
            
            if ($participants->count() < 2) {
                return [
                    'success' => false,
                    'message' => 'Not enough players for matchmaking'
                ];
            }

            $possibleMatches = $this->calculateCompatibilityScores($participants);
            $matches = $this->optimizeMatches($possibleMatches);
            
            // Create match records
            $createdMatches = collect();
            foreach ($matches as $match) {
                $matchRecord = new MatchHistory([
                    'event_id' => $event->id,
                    'court_number' => null, // Will be assigned by host
                    'match_status' => 'pending',
                    'scheduled_time' => now(),
                ]);

                if ($match['player1']['player_type'] === 'registered') {
                    $matchRecord->player1_id = $match['player1']['user_id'];
                } else {
                    $matchRecord->player1_guest_id = $match['player1']['guest_id'];
                }

                if ($match['player2']['player_type'] === 'registered') {
                    $matchRecord->player2_id = $match['player2']['user_id'];
                } else {
                    $matchRecord->player2_guest_id = $match['player2']['guest_id'];
                }

                $matchRecord->save();
                $createdMatches->push($matchRecord);
            }

            DB::commit();
            
            return [
                'success' => true,
                'matches' => $createdMatches,
                'total_matches' => $createdMatches->count()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in createFairMatches: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating matches'];
        }
    }

    /**
     * Get eligible participants for matchmaking
     */
    public function getEligibleParticipants(Event $event)
    {
        // Get registered players
        $registeredParticipants = EventParticipant::with(['user', 'user.userSportRatings'])
            ->where('event_id', $event->id)
            ->where('status', 'checked_in')
            ->whereNull('guest_player_id')
            ->get();

        // Get guest players
        $guestParticipants = EventParticipant::with(['guestPlayer'])
            ->where('event_id', $event->id)
            ->where('status', 'checked_in')
            ->whereNotNull('guest_player_id')
            ->get();

        // Combine and format all participants
        $allParticipants = collect();

        foreach ($registeredParticipants as $participant) {
            $allParticipants->push([
                'participant_id' => $participant->id,
                'player_type' => 'registered',
                'user_id' => $participant->user_id,
                'guest_id' => null,
                'name' => $participant->user->name,
                'mmr' => $participant->user->userSportRatings
                    ->where('sport_id', $event->sport_id)
                    ->first()?->mmr ?? 1000,
                'win_rate' => $this->calculateWinRate($participant->user, $event->sport_id),
                'waiting_since' => $participant->checked_in_at,
                'is_premium' => $participant->user->is_premium
            ]);
        }

        foreach ($guestParticipants as $participant) {
            $guest = $participant->guestPlayer;
            $allParticipants->push([
                'participant_id' => $participant->id,
                'player_type' => 'guest',
                'user_id' => null,
                'guest_id' => $guest->id,
                'name' => $guest->name . ' (Guest)',
                'mmr' => $guest->estimated_mmr,
                'win_rate' => null,
                'waiting_since' => $participant->checked_in_at,
                'is_premium' => false
            ]);
        }

        return $allParticipants;
    }

    /**
     * Calculate compatibility scores for all possible player pairs
     */
    protected function calculateCompatibilityScores($participants)
    {
        $possibleMatches = collect();

        foreach ($participants as $i => $player1) {
            foreach ($participants->slice($i + 1) as $player2) {
                $mmrDiff = abs($player1['mmr'] - $player2['mmr']);
                $waitingTime = max(
                    now()->diffInMinutes($player1['waiting_since']),
                    now()->diffInMinutes($player2['waiting_since'])
                );

                // Calculate win rate compatibility only if both are registered players
                $winRateScore = 100;
                if ($player1['player_type'] === 'registered' && $player2['player_type'] === 'registered') {
                    $winRateDiff = abs(($player1['win_rate'] ?? 0) - ($player2['win_rate'] ?? 0));
                    $winRateScore = max(0, 100 - ($winRateDiff * 2));
                }

                // Calculate final compatibility score
                $score = $this->calculateFinalScore($mmrDiff, $winRateScore, $waitingTime);

                $possibleMatches->push([
                    'player1' => $player1,
                    'player2' => $player2,
                    'compatibility_score' => $score,
                    'details' => [
                        'mmr_difference' => $mmrDiff,
                        'win_rate_score' => $winRateScore,
                        'waiting_time' => $waitingTime
                    ]
                ]);
            }
        }

        return $possibleMatches->sortByDesc('compatibility_score');
    }

    /**
     * Optimize matches to avoid conflicts and maximize fairness
     */
    private function optimizeMatches($possibleMatches)
    {
        $matches = [];
        $usedPlayers = [];
        
        foreach ($possibleMatches as $match) {
            $player1Id = $match['player1']['participant_id'];
            $player2Id = $match['player2']['participant_id'];
            
            if (!isset($usedPlayers[$player1Id]) && !isset($usedPlayers[$player2Id])) {
                $matches[] = $match;
                $usedPlayers[$player1Id] = true;
                $usedPlayers[$player2Id] = true;
            }
        }
        
        return $matches;
    }

    /**
     * Calculate compatibility between two players
     */
    private function calculateCompatibilityScore($participant1, $participant2)
    {
        $player1 = $participant1['user'];
        $player2 = $participant2['user'];

        // Check premium player protection
        if (!$this->canMatchPremiumPlayers($player1, $player2)) {
            return [
                'total' => 0,
                'details' => [
                    'level_score' => 0,
                    'mmr_score' => 0,
                    'win_rate_score' => 0,
                    'waiting_score' => 0,
                    'reason' => 'Premium player protection active'
                ]
            ];
        }

        // Get sport ratings
        $rating1 = $this->getUserSportRating($player1, $participant1['event']->sport_id);
        $rating2 = $this->getUserSportRating($player2, $participant2['event']->sport_id);

        // Calculate level compatibility (40%)
        $levelScore = $this->calculateLevelCompatibility($rating1['level'], $rating2['level']);

        // Calculate MMR compatibility (35%)
        $mmrScore = $this->calculateMMRCompatibility($rating1['mmr'], $rating2['mmr']);

        // Calculate win rate compatibility (15%)
        $winRateScore = $this->calculateWinRateCompatibility($rating1['win_rate'], $rating2['win_rate']);

        // Calculate waiting time bonus (10%)
        $waitingScore = $this->calculateWaitingTimeBonus($participant1, $participant2);

        // Apply new weights
        $totalScore = ($levelScore * 0.4) +
                     ($mmrScore * 0.35) +
                     ($winRateScore * 0.15) +
                     ($waitingScore * 0.1);

        return [
            'total' => round($totalScore, 2),
            'details' => [
                'level_score' => round($levelScore, 2),
                'mmr_score' => round($mmrScore, 2),
                'win_rate_score' => round($winRateScore, 2),
                'waiting_score' => round($waitingScore, 2)
            ]
        ];
    }

    /**
     * Level Compatibility (same or adjacent levels preferred)
     */
    private function calculateLevelCompatibility($level1, $level2)
    {
        $levelValues = [
            'beginner' => 1,
            'intermediate' => 2,
            'advanced' => 3,
            'expert' => 4,
            'professional' => 5
        ];

        $value1 = $levelValues[$level1] ?? 1;
        $value2 = $levelValues[$level2] ?? 1;
        $difference = abs($value1 - $value2);

        // Perfect match for same level
        if ($difference === 0) {
            return 100;
        }
        // One level difference
        elseif ($difference === 1) {
            return 75;
        }
        // Two levels difference
        elseif ($difference === 2) {
            return 50;
        }
        // More than two levels difference
        else {
            return max(0, 100 - ($difference * 30));
        }
    }

    /**
     * MMR Compatibility (closer MMR = higher score)
     */
    private function calculateMMRCompatibility($mmr1, $mmr2)
    {
        $difference = abs($mmr1 - $mmr2);

        // Perfect match (0-50 MMR difference)
        if ($difference <= 50) {
            return 100;
        }
        // Good match (51-100 MMR difference)
        elseif ($difference <= 100) {
            return 90 - (($difference - 50) * 0.8);
        }
        // Acceptable match (101-200 MMR difference)
        elseif ($difference <= 200) {
            return 50 - (($difference - 100) * 0.4);
        }
        // Poor match (>200 MMR difference)
        else {
            return max(0, 10 - (($difference - 200) * 0.05));
        }
    }

    /**
     * Win Rate Compatibility (similar win rates = better matches)
     */
    private function calculateWinRateCompatibility($winRate1, $winRate2)
    {
        $difference = abs($winRate1 - $winRate2);

        // Perfect match (0-5% difference)
        if ($difference <= 5) {
            return 100;
        }
        // Good match (6-15% difference)
        elseif ($difference <= 15) {
            return 90 - (($difference - 5) * 2);
        }
        // Acceptable match (16-30% difference)
        elseif ($difference <= 30) {
            return 70 - (($difference - 15) * 2);
        }
        // Poor match (>30% difference)
        else {
            return max(0, 40 - (($difference - 30) * 1));
        }
    }

    /**
     * Waiting Time Bonus (longer waiting = higher priority)
     */
    private function calculateWaitingTimeBonus($participant1, $participant2)
    {
        $waitTime1 = now()->diffInMinutes($participant1['waiting_since']);
        $waitTime2 = now()->diffInMinutes($participant2['waiting_since']);
        $avgWaitTime = ($waitTime1 + $waitTime2) / 2;

        // Max score for waiting 60+ minutes
        if ($avgWaitTime >= 60) {
            return 100;
        }
        // Linear increase from 0 to 100 over 60 minutes
        else {
            return min(100, ($avgWaitTime / 60) * 100);
        }
    }

    /**
     * Get user sport rating with defaults
     */
    private function getUserSportRating($user, $sportId)
    {
        $rating = $user->sportRatings()->where('sport_id', $sportId)->first();
        
        return [
            'mmr' => $rating ? $rating->mmr : 1000,
            'level' => $rating ? $rating->level : 'beginner',
            'win_rate' => $rating ? $rating->win_rate : 50
        ];
    }

    /**
     * Check if players can be matched (premium protection)
     */
    private function canMatchPlayers($participant1, $participant2, Event $event)
    {
        // Premium players cannot be arbitrarily switched by host
        // But matchmaking system can match them fairly
        return true;
    }

    /**
     * Check if players can be matched (premium protection)
     */
    private function canMatchPremiumPlayers($player1, $player2)
    {
        // If neither player is premium, no restrictions
        if (!$player1->is_premium && !$player2->is_premium) {
            return true;
        }

        // Get sport ratings for both players
        $rating1 = $this->getUserSportRating($player1, $participant1->event->sport_id);
        $rating2 = $this->getUserSportRating($player2, $participant2->event->sport_id);

        // Premium player protection rules
        if ($player1->is_premium || $player2->is_premium) {
            // MMR difference must be within 150 points for premium players
            $mmrDiff = abs($rating1['mmr'] - $rating2['mmr']);
            if ($mmrDiff > 150) {
                return false;
            }

            // Must be same skill level for premium players
            if ($rating1['level'] !== $rating2['level']) {
                return false;
            }

            // Win rate difference must be within 20% for premium players
            $winRateDiff = abs($rating1['win_rate'] - $rating2['win_rate']);
            if ($winRateDiff > 20) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get skill level from MMR
     */
    private function getSkillLevelFromMMR($mmr)
    {
        if ($mmr < 800) return 'beginner';
        if ($mmr < 1200) return 'intermediate';  
        if ($mmr < 1600) return 'advanced';
        return 'expert';
    }

    /**
     * Get queue information for event
     */
    public function getQueueInfo(Event $event)
    {
        $participants = $this->getEligibleParticipants($event);
        $activeMatches = MatchHistory::where('event_id', $event->id)
            ->whereIn('match_status', ['scheduled', 'ongoing'])
            ->count();

        $waitingPlayers = $participants->filter(function($participant) {
            return !$participant['user']->hasActiveMatch();
        });

        return [
            'total_participants' => $participants->count(),
            'active_matches' => $activeMatches,
            'waiting_players' => $waitingPlayers->count(),
            'can_create_matches' => $waitingPlayers->count() >= 2,
            'queue_list' => $waitingPlayers->map(function($participant) {
                return [
                    'user' => $participant['user'],
                    'waiting_since' => $participant['waiting_since'],
                    'waiting_minutes' => Carbon::now()->diffInMinutes($participant['waiting_since'])
                ];
            })->sortByDesc('waiting_minutes')->values()
        ];
    }
} 