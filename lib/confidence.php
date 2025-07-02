<?php
/**
 * Confidence System for PUBG Leaderboard
 * Handles confidence factor calculations based on matches played
 */

class ConfidenceSystem {
    // Minimum matches for full confidence
    const FULL_CONFIDENCE_THRESHOLD = 50;
    
    // Confidence level thresholds
    const CONFIDENCE_LOW = 20;
    const CONFIDENCE_MEDIUM = 50;
    
    // Confidence badges
    const BADGES = [
        'low' => [
            'min' => 0,
            'max' => 19,
            'color' => 'red',
            'icon' => '🔴',
            'label' => 'Datos limitados',
            'class' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
        ],
        'medium' => [
            'min' => 20,
            'max' => 49,
            'color' => 'yellow',
            'icon' => '🟡',
            'label' => 'Confianza media',
            'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
        ],
        'high' => [
            'min' => 50,
            'max' => PHP_INT_MAX,
            'color' => 'green',
            'icon' => '🟢',
            'label' => 'Alta confianza',
            'class' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
        ]
    ];
    
    /**
     * Calculate confidence factor based on matches played
     * Linear scale from 0 to 1, capped at FULL_CONFIDENCE_THRESHOLD
     */
    public static function getConfidenceFactor(int $matchesPlayed): float {
        if ($matchesPlayed >= self::FULL_CONFIDENCE_THRESHOLD) {
            return 1.0;
        }
        return $matchesPlayed / self::FULL_CONFIDENCE_THRESHOLD;
    }
    
    /**
     * Get confidence badge based on matches played
     */
    public static function getConfidenceBadge(int $matchesPlayed): array {
        foreach (self::BADGES as $level => $badge) {
            if ($matchesPlayed >= $badge['min'] && $matchesPlayed <= $badge['max']) {
                return array_merge($badge, ['level' => $level, 'matches' => $matchesPlayed]);
            }
        }
        return self::BADGES['high']; // Default to high if somehow outside ranges
    }
    
    /**
     * Apply confidence factor to a base score
     */
    public static function applyConfidence(float $baseScore, int $matchesPlayed, string $statsType = 'season'): float {
        // Lifetime stats always have full confidence
        if ($statsType === 'lifetime') {
            return $baseScore;
        }
        
        $confidenceFactor = self::getConfidenceFactor($matchesPlayed);
        return $baseScore * $confidenceFactor;
    }
    
    /**
     * Calculate matches needed to reach next confidence level
     */
    public static function matchesToNextLevel(int $currentMatches): ?int {
        if ($currentMatches < self::CONFIDENCE_LOW) {
            return self::CONFIDENCE_LOW - $currentMatches;
        } elseif ($currentMatches < self::CONFIDENCE_MEDIUM) {
            return self::CONFIDENCE_MEDIUM - $currentMatches;
        }
        return null; // Already at max level
    }
    
    /**
     * Get confidence percentage for display
     */
    public static function getConfidencePercentage(int $matchesPlayed): int {
        $factor = self::getConfidenceFactor($matchesPlayed);
        return (int)($factor * 100);
    }
    
    /**
     * Determine if stats are reliable enough for certain metrics
     */
    public static function isReliableForMetric(int $matchesPlayed, string $metric): bool {
        $requirements = [
            'consistency_rating' => 20,    // Need at least 20 matches for consistency
            'clutch_performance' => 30,    // Need 30 matches for clutch analysis
            'improvement_trend' => 40,     // Need 40 matches for trend analysis
            'peak_performance' => 10       // Only 10 matches needed for peak
        ];
        
        return $matchesPlayed >= ($requirements[$metric] ?? 0);
    }
    
    /**
     * Format confidence indicator for display
     */
    public static function formatConfidenceIndicator(int $matchesPlayed): string {
        $badge = self::getConfidenceBadge($matchesPlayed);
        $percentage = self::getConfidencePercentage($matchesPlayed);
        
        return sprintf(
            '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s" title="%d partidas jugadas (%d%% confianza)">
                <span class="mr-1">%s</span>
                %s
            </span>',
            $badge['class'],
            $matchesPlayed,
            $percentage,
            $badge['icon'],
            $badge['label']
        );
    }
    
    /**
     * Get confidence progress bar HTML
     */
    public static function getProgressBar(int $matchesPlayed): string {
        $percentage = self::getConfidencePercentage($matchesPlayed);
        $badge = self::getConfidenceBadge($matchesPlayed);
        
        $colorClass = match($badge['level']) {
            'low' => 'bg-red-500',
            'medium' => 'bg-yellow-500',
            'high' => 'bg-green-500',
            default => 'bg-gray-500'
        };
        
        return sprintf(
            '<div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                <div class="%s h-2.5 rounded-full transition-all duration-300" style="width: %d%%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1">%d/%d partidas para confianza máxima</p>',
            $colorClass,
            $percentage,
            $matchesPlayed,
            self::FULL_CONFIDENCE_THRESHOLD
        );
    }
    
    /**
     * Calculate weighted average considering confidence
     */
    public static function weightedAverage(array $players, string $metric): float {
        $totalWeight = 0;
        $weightedSum = 0;
        
        foreach ($players as $player) {
            $confidence = self::getConfidenceFactor($player['matches_played']);
            $value = $player[$metric] ?? 0;
            
            $weightedSum += $value * $confidence;
            $totalWeight += $confidence;
        }
        
        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
    }
    
    /**
     * Filter players by confidence level
     */
    public static function filterByConfidence(array $players, string $minLevel = 'low'): array {
        $minMatches = match($minLevel) {
            'medium' => self::CONFIDENCE_LOW,
            'high' => self::CONFIDENCE_MEDIUM,
            default => 0
        };
        
        return array_filter($players, fn($player) => $player['matches_played'] >= $minMatches);
    }
    
    /**
     * Group players by confidence level
     */
    public static function groupByConfidence(array $players): array {
        $groups = [
            'low' => [],
            'medium' => [],
            'high' => []
        ];
        
        foreach ($players as $player) {
            $badge = self::getConfidenceBadge($player['matches_played']);
            $groups[$badge['level']][] = $player;
        }
        
        return $groups;
    }
} 