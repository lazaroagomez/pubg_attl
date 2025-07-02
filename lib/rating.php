<?php
/**
 * Rating System for PUBG Leaderboard
 * Calculates Combat, Survival, Support and overall PUBG Rating scores
 */

require_once __DIR__ . '/confidence.php';

class RatingSystem {
    // Weight configuration for final PUBG Rating
    const WEIGHT_COMBAT = 0.35;
    const WEIGHT_SURVIVAL = 0.25;
    const WEIGHT_SUPPORT = 0.20;
    const WEIGHT_CONSISTENCY = 0.20;
    
    /**
     * Calculate all rating scores for a player
     */
    public static function calculateRatings(array $stats): array {
        $matchesPlayed = $stats['matches_played'] ?? 0;
        $statsType = $stats['stats_type'] ?? 'season';
        
        // Calculate base scores
        $combatScoreBase = self::calculateCombatScore($stats);
        $survivalScoreBase = self::calculateSurvivalScore($stats);
        $supportScoreBase = self::calculateSupportScore($stats);
        $consistencyBonus = self::calculateConsistencyBonus($stats);
        
        // Apply confidence factor
        $confidenceFactor = ConfidenceSystem::getConfidenceFactor($matchesPlayed);
        
        // For lifetime stats, don't apply confidence
        if ($statsType === 'lifetime') {
            $confidenceFactor = 1.0;
        }
        
        // Calculate final scores with confidence
        $combatScore = $combatScoreBase * $confidenceFactor;
        $survivalScore = $survivalScoreBase * $confidenceFactor;
        $supportScore = $supportScoreBase * $confidenceFactor;
        $consistencyScore = $consistencyBonus * $confidenceFactor;
        
        // Calculate final PUBG Rating
        $pubgRating = (
            $combatScore * self::WEIGHT_COMBAT +
            $survivalScore * self::WEIGHT_SURVIVAL +
            $supportScore * self::WEIGHT_SUPPORT +
            $consistencyScore * self::WEIGHT_CONSISTENCY
        );
        
        return [
            'combat_score' => round($combatScore, 2),
            'survival_score' => round($survivalScore, 2),
            'support_score' => round($supportScore, 2),
            'pubg_rating' => round($pubgRating, 2),
            'confidence_factor' => round($confidenceFactor, 2),
            'combat_score_base' => round($combatScoreBase, 2),
            'survival_score_base' => round($survivalScoreBase, 2),
            'support_score_base' => round($supportScoreBase, 2),
            'consistency_bonus' => round($consistencyBonus, 2)
        ];
    }
    
    /**
     * Calculate Combat Score (0-100)
     * Formula: (K/D × 20) + (Damage_per_Match / 100) + (Headshot_% × 50) + (Accuracy × 100)
     */
    private static function calculateCombatScore(array $stats): float {
        $kd = $stats['kd_ratio'] ?? 0;
        $avgDamage = $stats['avg_damage'] ?? 0;
        $headshotRate = ($stats['headshot_rate'] ?? 0) / 100; // Convert to decimal
        
        // Since we don't have accuracy in the API, we'll estimate it based on other factors
        // Higher headshot rate and K/D usually correlate with better accuracy
        $estimatedAccuracy = min(0.5, ($headshotRate + min($kd / 10, 0.5)) / 2);
        
        $score = 
            ($kd * 20) +                    // K/D contribution (max ~100 for K/D of 5)
            ($avgDamage / 100) +            // Damage contribution (max ~5 for 500 avg damage)
            ($headshotRate * 50) +          // Headshot contribution (max 50)
            ($estimatedAccuracy * 100);     // Accuracy contribution (max 50)
        
        // Normalize to 0-100 scale
        return min(100, $score / 2);
    }
    
    /**
     * Calculate Survival Score (0-100)
     * Formula: (Win_Rate × 100) + (Top10_Rate × 50) + ((Max_Placement - Avg_Placement) / Max_Placement × 50)
     */
    private static function calculateSurvivalScore(array $stats): float {
        $winRate = ($stats['win_rate'] ?? 0) / 100; // Convert to decimal
        $top10Rate = ($stats['top10_rate'] ?? 0) / 100; // Convert to decimal
        
        // Estimate average placement based on win rate and top 10 rate
        // This is a simplified calculation since we don't have exact placement data
        $estimatedAvgPlacement = 100 - ($winRate * 99 + $top10Rate * 90);
        $maxPlacement = 100;
        $placementScore = ($maxPlacement - $estimatedAvgPlacement) / $maxPlacement;
        
        $score = 
            ($winRate * 100) +              // Win rate contribution (max 100)
            ($top10Rate * 50) +             // Top 10 contribution (max 50)
            ($placementScore * 50);         // Placement contribution (max 50)
        
        // Normalize to 0-100 scale
        return min(100, $score / 2);
    }
    
    /**
     * Calculate Support Score (0-100)
     * Formula: (Assists_per_Match × 10) + (Revives_per_Match × 15) + (Team_Kills_Share × 50)
     */
    private static function calculateSupportScore(array $stats): float {
        $matchesPlayed = max(1, $stats['matches_played'] ?? 1);
        
        $assistsPerMatch = ($stats['assists'] ?? 0) / $matchesPlayed;
        $revivesPerMatch = ($stats['revives'] ?? 0) / $matchesPlayed;
        
        // Team kills share is estimated based on assists relative to kills
        $kills = $stats['kills'] ?? 0;
        $assists = $stats['assists'] ?? 0;
        $teamKillsShare = $kills > 0 ? min(1, $assists / $kills) : 0;
        
        $score = 
            ($assistsPerMatch * 10) +       // Assists contribution (max ~20 for 2 assists/match)
            ($revivesPerMatch * 15) +       // Revives contribution (max ~30 for 2 revives/match)
            ($teamKillsShare * 50);         // Team play contribution (max 50)
        
        // Normalize to 0-100 scale
        return min(100, $score);
    }
    
    /**
     * Calculate Consistency Bonus (0-100)
     * Based on performance stability across matches
     */
    private static function calculateConsistencyBonus(array $stats): float {
        // For simplicity, we'll base consistency on win rate, K/D, and average damage
        // Higher values with more matches indicate consistency
        
        $matchesPlayed = $stats['matches_played'] ?? 0;
        if ($matchesPlayed < 10) {
            return 0; // Not enough data for consistency
        }
        
        $kd = $stats['kd_ratio'] ?? 0;
        $winRate = ($stats['win_rate'] ?? 0) / 100;
        $avgDamage = $stats['avg_damage'] ?? 0;
        
        // Normalize each metric to 0-1 scale
        $kdNorm = min(1, $kd / 5);                    // K/D of 5 = max
        $winRateNorm = $winRate;                      // Already 0-1
        $avgDamageNorm = min(1, $avgDamage / 500);    // 500 avg damage = max
        
        // Average normalized scores
        $avgPerformance = ($kdNorm + $winRateNorm + $avgDamageNorm) / 3;
        
        // Apply a multiplier based on matches played (more matches = more consistent)
        $matchMultiplier = min(1, $matchesPlayed / 100);
        
        return $avgPerformance * $matchMultiplier * 100;
    }
    
    /**
     * Get rating color class based on score
     */
    public static function getRatingColorClass(float $score): string {
        if ($score >= 80) {
            return 'text-purple-600 dark:text-purple-400'; // Legendary
        } elseif ($score >= 60) {
            return 'text-yellow-600 dark:text-yellow-400'; // Gold
        } elseif ($score >= 40) {
            return 'text-gray-600 dark:text-gray-400';     // Silver
        } elseif ($score >= 20) {
            return 'text-orange-600 dark:text-orange-400'; // Bronze
        } else {
            return 'text-red-600 dark:text-red-400';       // Unranked
        }
    }
    
    /**
     * Get rating tier name based on score
     */
    public static function getRatingTier(float $score): string {
        if ($score >= 80) {
            return 'Legendary';
        } elseif ($score >= 60) {
            return 'Gold';
        } elseif ($score >= 40) {
            return 'Silver';
        } elseif ($score >= 20) {
            return 'Bronze';
        } else {
            return 'Unranked';
        }
    }
    
    /**
     * Format rating for display with icon
     */
    public static function formatRating(float $score): string {
        $tier = self::getRatingTier($score);
        $colorClass = self::getRatingColorClass($score);
        
        $icons = [
            'Legendary' => '👑',
            'Gold' => '🥇',
            'Silver' => '🥈',
            'Bronze' => '🥉',
            'Unranked' => '🎮'
        ];
        
        $icon = $icons[$tier] ?? '🎮';
        
        return sprintf(
            '<span class="font-bold %s">%s %.1f</span>',
            $colorClass,
            $icon,
            $score
        );
    }
    
    /**
     * Get detailed rating breakdown
     */
    public static function getRatingBreakdown(array $ratings): array {
        return [
            'combat' => [
                'score' => $ratings['combat_score'],
                'base' => $ratings['combat_score_base'] ?? $ratings['combat_score'],
                'weight' => self::WEIGHT_COMBAT,
                'contribution' => $ratings['combat_score'] * self::WEIGHT_COMBAT,
                'icon' => '⚔️',
                'label' => 'Combat'
            ],
            'survival' => [
                'score' => $ratings['survival_score'],
                'base' => $ratings['survival_score_base'] ?? $ratings['survival_score'],
                'weight' => self::WEIGHT_SURVIVAL,
                'contribution' => $ratings['survival_score'] * self::WEIGHT_SURVIVAL,
                'icon' => '🛡️',
                'label' => 'Survival'
            ],
            'support' => [
                'score' => $ratings['support_score'],
                'base' => $ratings['support_score_base'] ?? $ratings['support_score'],
                'weight' => self::WEIGHT_SUPPORT,
                'contribution' => $ratings['support_score'] * self::WEIGHT_SUPPORT,
                'icon' => '🤝',
                'label' => 'Support'
            ],
            'consistency' => [
                'score' => $ratings['consistency_bonus'] ?? 0,
                'base' => $ratings['consistency_bonus'] ?? 0,
                'weight' => self::WEIGHT_CONSISTENCY,
                'contribution' => ($ratings['consistency_bonus'] ?? 0) * self::WEIGHT_CONSISTENCY,
                'icon' => '📊',
                'label' => 'Consistency'
            ]
        ];
    }
} 