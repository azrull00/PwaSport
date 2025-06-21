<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Models\UserSportRating;
use App\Models\UserPreferredArea;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EventRecommendationService
{
    /**
     * Get recommended events for user based on skill level and compatibility
     */
    public function getRecommendationsForUser(User $user, $limit = 10)
    {
        // Get user's preferred areas (free users: max 3, premium: unlimited)
        $preferredAreas = $user->preferredAreas()->pluck('area_name')->toArray();
        
        // Get user's sport ratings
        $userSportRatings = $user->sportRatings()->get()->keyBy('sport_id');
        
        // Base query for available events
        $query = Event::with(['sport', 'community', 'host.profile'])
            ->where('status', 'published')
            ->where('event_date', '>=', now())
            ->where('registration_deadline', '>=', now())
            ->whereDoesntHave('participants', function($participantQuery) use ($user) {
                $participantQuery->where('user_id', $user->id);
            });

        // Filter by preferred areas if user has any
        if (!empty($preferredAreas)) {
            $query->where(function($locationQuery) use ($preferredAreas) {
                foreach ($preferredAreas as $area) {
                    $locationQuery->orWhere('city', 'like', "%{$area}%")
                                ->orWhere('province', 'like', "%{$area}%");
                }
            });
        }

        // Get events and calculate recommendation scores
        $events = $query->get();
        $recommendations = [];

        foreach ($events as $event) {
            $score = $this->calculateRecommendationScore($user, $event, $userSportRatings);
            
            if ($score['total_score'] > 40) {
                $recommendations[] = [
                    'event' => $event,
                    'recommendation_score' => $score['total_score'],
                    'recommendation_reasons' => $this->getRecommendationReasons($user, $event, $userSportRatings)
                ];
            }
        }

        // Sort by recommendation score (highest first)
        usort($recommendations, function($a, $b) {
            return $b['recommendation_score'] <=> $a['recommendation_score'];
        });

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Calculate recommendation score for an event
     */
    private function calculateRecommendationScore(User $user, Event $event, $userSportRatings)
    {
        $score = 0;

        // Sport Experience (30%)
        $sportScore = $this->calculateSportScore($user, $event, $userSportRatings);
        $score += $sportScore * 0.3;

        // Skill Compatibility (25%)
        $skillScore = $this->calculateSkillScore($user, $event, $userSportRatings);
        $score += $skillScore * 0.25;

        // Location (20%)
        $locationScore = $this->calculateLocationScore($user, $event);
        $score += $locationScore * 0.2;

        // Time (15%)
        $timeScore = $this->calculateTimeScore($event);
        $score += $timeScore * 0.15;

        // Community (10%)
        $communityScore = $this->calculateCommunityScore($event);
        $score += $communityScore * 0.1;

        return [
            'total_score' => round($score, 2),
            'sport_score' => $sportScore,
            'skill_score' => $skillScore,
            'location_score' => $locationScore,
            'time_score' => $timeScore,
            'community_score' => $communityScore
        ];
    }

    /**
     * Calculate sport experience score
     */
    private function calculateSportScore(User $user, Event $event, $userSportRatings)
    {
        $sportRating = $userSportRatings->get($event->sport_id);
        
        if (!$sportRating) {
            return 60; // Encourage trying new sports
        }

        $matchesPlayed = $sportRating->matches_played;
        if ($matchesPlayed >= 20) return 100;
        if ($matchesPlayed >= 10) return 85;
        if ($matchesPlayed >= 5) return 75;
        return 65;
    }

    /**
     * Calculate skill level compatibility with other participants
     */
    private function calculateSkillScore(User $user, Event $event, $userSportRatings)
    {
        $userSportRating = $userSportRatings->get($event->sport_id);
        $userMMR = $userSportRating ? $userSportRating->mmr : 1000;

        // Get average participant MMR
        $avgMMR = DB::table('event_participants')
            ->join('user_sport_ratings', function($join) use ($event) {
                $join->on('event_participants.user_id', '=', 'user_sport_ratings.user_id')
                     ->where('user_sport_ratings.sport_id', '=', $event->sport_id);
            })
            ->where('event_participants.event_id', $event->id)
            ->where('event_participants.status', 'confirmed')
            ->avg('user_sport_ratings.mmr');

        if (!$avgMMR) return 75; // No participants yet

        $mmrDiff = abs($userMMR - $avgMMR);
        if ($mmrDiff <= 100) return 100;
        if ($mmrDiff <= 200) return 85;
        if ($mmrDiff <= 300) return 70;
        return 50;
    }

    /**
     * Calculate location preference score
     */
    private function calculateLocationScore(User $user, Event $event)
    {
        $preferredAreas = $user->preferredAreas()->pluck('area_name')->toArray();
        
        if (empty($preferredAreas)) return 50;

        foreach ($preferredAreas as $area) {
            if (stripos($event->city, $area) !== false || 
                stripos($event->province, $area) !== false) {
                return 100;
            }
        }
        return 30;
    }

    /**
     * Calculate time preference score
     */
    private function calculateTimeScore(Event $event)
    {
        $eventTime = Carbon::parse($event->event_date);
        $score = 50;

        // Weekend bonus
        if ($eventTime->isWeekend()) $score += 20;

        // Time preferences
        $hour = $eventTime->hour;
        if ($hour >= 17 && $hour <= 21) $score += 20;
        elseif ($hour >= 9 && $hour <= 12) $score += 15;

        return min($score, 100);
    }

    /**
     * Calculate community quality score
     */
    private function calculateCommunityScore(Event $event)
    {
        if (!$event->community) return 50;

        $score = 50;
        if ($event->community->average_rating >= 4.0) $score += 25;
        if ($event->community->total_members > 20) $score += 15;

        return min($score, 100);
    }

    /**
     * Get human-readable recommendation reasons
     */
    private function getRecommendationReasons(User $user, Event $event, $userSportRatings)
    {
        $reasons = [];
        $sportRating = $userSportRatings->get($event->sport_id);
        
        if ($sportRating && $sportRating->matches_played >= 10) {
            $reasons[] = "Anda berpengalaman di {$event->sport->name}";
        } elseif (!$sportRating) {
            $reasons[] = "Kesempatan mencoba {$event->sport->name}";
        }

        $preferredAreas = $user->preferredAreas()->pluck('area_name')->toArray();
        foreach ($preferredAreas as $area) {
            if (stripos($event->city, $area) !== false) {
                $reasons[] = "Lokasi di area preferensi Anda";
                break;
            }
        }

        if (Carbon::parse($event->event_date)->isWeekend()) {
            $reasons[] = "Event di akhir pekan";
        }

        return $reasons;
    }

    /**
     * Get recommendation categories for display
     */
    public function getRecommendationCategories(User $user)
    {
        $recommendations = $this->getRecommendationsForUser($user, 20);
        
        return [
            'perfect_match' => array_filter($recommendations, function($rec) {
                return $rec['recommendation_score'] >= 80;
            }),
            'good_match' => array_filter($recommendations, function($rec) {
                return $rec['recommendation_score'] >= 60 && 
                       $rec['recommendation_score'] < 80;
            }),
            'try_something_new' => array_filter($recommendations, function($rec) {
                return $rec['recommendation_score'] >= 40 && 
                       $rec['recommendation_score'] < 60;
            })
        ];
    }
}