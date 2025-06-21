<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Models\UserSportRating;
use App\Models\MatchHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MatchmakingService
{
    /**
     * Fair Matchmaking Algorithm
     * Factors: MMR, Win Rate, Waiting Time, Premium Protection
     */
    public function createFairMatches(Event $event)
    {
        try {
            DB::beginTransaction();

            // Get all confirmed participants with ratings
            $participants = $this->getEligibleParticipants($event);
            
            if ($participants->count() < 2) {
                return [
                    'success' => false,
                    'message' => 'Tidak cukup peserta untuk matchmaking (minimum 2)'
                ];
            }

            // Calculate compatibility scores for all possible pairs
            $possibleMatches = $this->calculateCompatibilityScores($participants, $event);
            
            // Create optimal matches using fair algorithm
            $matches = $this->optimizeMatches($possibleMatches, $event);
            
            // Save matches to database
            $savedMatches = $this->saveMatches($matches, $event);
            
            DB::commit();
            
            return [
                'success' => true,
                'matches' => $savedMatches,
                'total_matches' => count($savedMatches),
                'matched_players' => count($savedMatches) * 2,
                'waiting_players' => $participants->count() - (count($savedMatches) * 2)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Matchmaking Error: ' . $e->getMessage(), [
                'event_id' => $event->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat melakukan matchmaking',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get eligible participants for matchmaking
     */
    private function getEligibleParticipants(Event $event)
    {
        return EventParticipant::with(['user.sportRatings', 'user.profile'])
            ->where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->whereDoesntHave('currentMatches', function($query) {
                $query->whereIn('match_status', ['scheduled', 'ongoing']);
            })
            ->get();
    }

    /**
     * Calculate compatibility scores for all possible player pairs
     */
    private function calculateCompatibilityScores($participants, Event $event)
    {
        $possibleMatches = [];
        $participantArray = $participants->toArray();

        for ($i = 0; $i < count($participantArray) - 1; $i++) {
            for ($j = $i + 1; $j < count($participantArray); $j++) {
                $player1 = $participants[$i];
                $player2 = $participants[$j];

                $compatibilityScore = $this->calculateCompatibility($player1, $player2, $event);
                
                $possibleMatches[] = [
                    'player1' => $player1,
                    'player2' => $player2,
                    'compatibility_score' => $compatibilityScore,
                    'details' => $compatibilityScore['details']
                ];
            }
        }

        // Sort by compatibility score (highest first)
        usort($possibleMatches, function($a, $b) {
            return $b['compatibility_score']['total'] <=> $a['compatibility_score']['total'];
        });

        return $possibleMatches;
    }

    /**
     * Calculate compatibility between two players
     */
    private function calculateCompatibility($participant1, $participant2, Event $event)
    {
        $player1 = $participant1->user;
        $player2 = $participant2->user;
        
        // Get sport ratings
        $rating1 = $this->getUserSportRating($player1, $event->sport_id);
        $rating2 = $this->getUserSportRating($player2, $event->sport_id);

        // Calculate individual factors
        $mmrScore = $this->calculateMMRCompatibility($rating1['mmr'], $rating2['mmr']);
        $winRateScore = $this->calculateWinRateCompatibility($rating1['win_rate'], $rating2['win_rate']);
        $waitingTimeScore = $this->calculateWaitingTimeBonus($participant1, $participant2);
        $levelScore = $this->calculateLevelCompatibility($rating1['level'], $rating2['level']);

        // Factor weights
        $weights = [
            'mmr' => 0.4,      // 40% - Most important for fair matches
            'level' => 0.25,   // 25% - Skill level compatibility  
            'win_rate' => 0.2, // 20% - Performance consistency
            'waiting_time' => 0.15 // 15% - Fairness for waiting players
        ];

        $totalScore = (
            $mmrScore * $weights['mmr'] +
            $levelScore * $weights['level'] +
            $winRateScore * $weights['win_rate'] +
            $waitingTimeScore * $weights['waiting_time']
        );

        return [
            'total' => round($totalScore, 2),
            'details' => [
                'mmr_score' => $mmrScore,
                'level_score' => $levelScore,
                'win_rate_score' => $winRateScore,
                'waiting_time_score' => $waitingTimeScore,
                'player1_mmr' => $rating1['mmr'],
                'player2_mmr' => $rating2['mmr'],
                'player1_level' => $rating1['level'],
                'player2_level' => $rating2['level'],
                'player1_win_rate' => $rating1['win_rate'],
                'player2_win_rate' => $rating2['win_rate']
            ]
        ];
    }

    /**
     * MMR Compatibility (closer MMR = higher score)
     */
    private function calculateMMRCompatibility($mmr1, $mmr2)
    {
        $difference = abs($mmr1 - $mmr2);
        
        // Optimal MMR difference: 0-100 points
        if ($difference <= 50) {
            return 100; // Perfect match
        } elseif ($difference <= 100) {
            return 90 - ($difference - 50); // 90-40 range
        } elseif ($difference <= 200) {
            return 40 - (($difference - 100) * 0.3); // 40-10 range
        } else {
            return max(0, 10 - (($difference - 200) * 0.05)); // 10-0 range
        }
    }

    /**
     * Win Rate Compatibility (similar win rates = better matches)
     */
    private function calculateWinRateCompatibility($winRate1, $winRate2)
    {
        $difference = abs($winRate1 - $winRate2);
        
        // Win rate difference scoring
        if ($difference <= 10) {
            return 100; // Very similar performance
        } elseif ($difference <= 20) {
            return 80 - ($difference - 10); // 80-70 range
        } elseif ($difference <= 40) {
            return 70 - (($difference - 20) * 1.5); // 70-40 range
        } else {
            return max(0, 40 - (($difference - 40) * 0.8)); // 40-0 range
        }
    }

    /**
     * Waiting Time Bonus (longer waiting = higher priority)
     */
    private function calculateWaitingTimeBonus($participant1, $participant2)
    {
        $now = Carbon::now();
        $waitTime1 = $now->diffInMinutes($participant1->confirmed_at ?? $participant1->created_at);
        $waitTime2 = $now->diffInMinutes($participant2->confirmed_at ?? $participant2->created_at);
        
        $avgWaitTime = ($waitTime1 + $waitTime2) / 2;
        
        // Waiting time bonus scoring
        if ($avgWaitTime >= 60) { // 1+ hours
            return 100;
        } elseif ($avgWaitTime >= 30) { // 30+ minutes
            return 80 + (($avgWaitTime - 30) / 30) * 20;
        } elseif ($avgWaitTime >= 15) { // 15+ minutes
            return 60 + (($avgWaitTime - 15) / 15) * 20;
        } else {
            return 40 + ($avgWaitTime / 15) * 20; // 0-15 minutes
        }
    }

    /**
     * Level Compatibility (same or adjacent levels preferred)
     */
    private function calculateLevelCompatibility($level1, $level2)
    {
        $levelOrder = ['beginner', 'intermediate', 'advanced', 'expert'];
        $index1 = array_search($level1, $levelOrder);
        $index2 = array_search($level2, $levelOrder);
        
        if ($index1 === false || $index2 === false) {
            return 50; // Unknown levels
        }
        
        $difference = abs($index1 - $index2);
        
        switch ($difference) {
            case 0: return 100; // Same level
            case 1: return 75;  // Adjacent level
            case 2: return 40;  // Two levels apart
            case 3: return 10;  // Maximum difference
            default: return 0;
        }
    }

    /**
     * Get user sport rating with defaults
     */
    private function getUserSportRating($user, $sportId)
    {
        $rating = UserSportRating::where('user_id', $user->id)
            ->where('sport_id', $sportId)
            ->first();

        if (!$rating) {
            return [
                'mmr' => 1000, // Default MMR for new players
                'level' => 'beginner',
                'win_rate' => 0,
                'matches_played' => 0
            ];
        }

        $winRate = $rating->matches_played > 0 ? 
            round(($rating->matches_won / $rating->matches_played) * 100, 1) : 0;

        return [
            'mmr' => $rating->mmr,
            'level' => $this->getSkillLevelFromMMR($rating->mmr),
            'win_rate' => $winRate,
            'matches_played' => $rating->matches_played
        ];
    }

    /**
     * Optimize matches to avoid conflicts and maximize fairness
     */
    private function optimizeMatches($possibleMatches, Event $event)
    {
        $finalMatches = [];
        $usedPlayers = [];

        foreach ($possibleMatches as $match) {
            $player1Id = $match['player1']->user_id;
            $player2Id = $match['player2']->user_id;

            // Skip if either player is already matched
            if (in_array($player1Id, $usedPlayers) || in_array($player2Id, $usedPlayers)) {
                continue;
            }

            // Check premium protection
            if (!$this->canMatchPlayers($match['player1'], $match['player2'], $event)) {
                continue;
            }

            $finalMatches[] = $match;
            $usedPlayers[] = $player1Id;
            $usedPlayers[] = $player2Id;
        }

        return $finalMatches;
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
     * Save matches to database
     */
    private function saveMatches($matches, Event $event)
    {
        $savedMatches = [];

        foreach ($matches as $match) {
            $matchHistory = MatchHistory::create([
                'event_id' => $event->id,
                'sport_id' => $event->sport_id,
                'player1_id' => $match['player1']->user_id,
                'player2_id' => $match['player2']->user_id,
                'match_date' => $event->event_date,
                'match_status' => 'scheduled',
                'court_number' => null, // Will be assigned by host
                'estimated_duration' => $event->estimated_duration ?? 60,
                'match_notes' => 'Auto-generated by matchmaking system'
            ]);

            $savedMatches[] = [
                'id' => $matchHistory->id,
                'player1' => $match['player1']->user,
                'player2' => $match['player2']->user,
                'compatibility_score' => $match['compatibility_score'],
                'court_number' => null,
                'status' => 'scheduled'
            ];
        }

        return $savedMatches;
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
            return !$participant->user->hasActiveMatch();
        });

        return [
            'total_participants' => $participants->count(),
            'active_matches' => $activeMatches,
            'waiting_players' => $waitingPlayers->count(),
            'can_create_matches' => $waitingPlayers->count() >= 2,
            'queue_list' => $waitingPlayers->map(function($participant) {
                return [
                    'user' => $participant->user,
                    'waiting_since' => $participant->confirmed_at ?? $participant->created_at,
                    'waiting_minutes' => Carbon::now()->diffInMinutes($participant->confirmed_at ?? $participant->created_at)
                ];
            })->sortByDesc('waiting_minutes')->values()
        ];
    }
} 