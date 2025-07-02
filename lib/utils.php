<?php
/**
 * Utility functions for PUBG Leaderboard
 */

/**
 * Format time duration in human readable format
 */
function formatDuration(int $seconds): string {
    if ($seconds < 60) {
        return "{$seconds}s";
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return "{$minutes}m";
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return "{$hours}h {$minutes}m";
    } else {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        return "{$days}d {$hours}h";
    }
}

/**
 * Format distance in human readable format
 */
function formatDistance(float $meters): string {
    if ($meters < 1000) {
        return round($meters) . "m";
    } else {
        return round($meters / 1000, 1) . "km";
    }
}

/**
 * Format number with appropriate suffix (K, M, etc)
 */
function formatNumber($number): string {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return (string)$number;
}

/**
 * Get ordinal suffix for a number
 */
function getOrdinalSuffix(int $number): string {
    $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
        return $number . 'th';
    } else {
        return $number . $ends[$number % 10];
    }
}

/**
 * Calculate percentage change between two values
 */
function calculatePercentageChange(float $old, float $new): float {
    if ($old == 0) {
        return $new > 0 ? 100 : 0;
    }
    return (($new - $old) / $old) * 100;
}

/**
 * Format percentage with color based on positive/negative
 */
function formatPercentageChange(float $change): string {
    $formatted = sprintf("%+.1f%%", $change);
    $color = $change > 0 ? 'text-green-600' : ($change < 0 ? 'text-red-600' : 'text-gray-600');
    $arrow = $change > 0 ? '↑' : ($change < 0 ? '↓' : '→');
    
    return "<span class=\"{$color}\">{$arrow} {$formatted}</span>";
}

/**
 * Get season display name
 */
function getSeasonDisplayName(string $seasonId): string {
    // Parse season ID format (e.g., "division.bro.official.pc-2018-01")
    if (preg_match('/pc-(\d{4})-(\d{2})$/', $seasonId, $matches)) {
        $year = $matches[1];
        $season = $matches[2];
        return "Season {$season} ({$year})";
    }
    return $seasonId;
}

/**
 * Get weapon category from weapon ID
 */
function getWeaponCategory(string $weaponId): string {
    $categories = [
        'AKM' => 'Assault Rifle',
        'AUG' => 'Assault Rifle',
        'Beryl' => 'Assault Rifle',
        'G36C' => 'Assault Rifle',
        'Groza' => 'Assault Rifle',
        'M416' => 'Assault Rifle',
        'M16A4' => 'Assault Rifle',
        'Mk47' => 'Assault Rifle',
        'QBZ' => 'Assault Rifle',
        'SCAR-L' => 'Assault Rifle',
        
        'Bizon' => 'SMG',
        'MP5K' => 'SMG',
        'Thompson' => 'SMG',
        'UMP' => 'SMG',
        'Uzi' => 'SMG',
        'Vector' => 'SMG',
        
        'AWM' => 'Sniper Rifle',
        'Kar98k' => 'Sniper Rifle',
        'M24' => 'Sniper Rifle',
        'Mosin' => 'Sniper Rifle',
        'Win94' => 'Sniper Rifle',
        
        'Mini14' => 'DMR',
        'Mk14' => 'DMR',
        'QBU' => 'DMR',
        'SKS' => 'DMR',
        'SLR' => 'DMR',
        'VSS' => 'DMR',
        
        'DP28' => 'LMG',
        'M249' => 'LMG',
        
        'S12K' => 'Shotgun',
        'S1897' => 'Shotgun',
        'S686' => 'Shotgun',
        'DBS' => 'Shotgun',
        
        'Deagle' => 'Pistol',
        'P18C' => 'Pistol',
        'P1911' => 'Pistol',
        'P92' => 'Pistol',
        'R1895' => 'Pistol',
        'R45' => 'Pistol',
        'Skorpion' => 'Pistol',
        
        'Crossbow' => 'Other',
        'Sawed-off' => 'Other',
    ];
    
    foreach ($categories as $weapon => $category) {
        if (stripos($weaponId, $weapon) !== false) {
            return $category;
        }
    }
    
    return 'Unknown';
}

/**
 * Generate gravatar URL
 */
function getGravatarUrl(string $identifier, int $size = 80): string {
    $hash = md5(strtolower(trim($identifier)));
    return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=retro";
}

/**
 * Sanitize input
 */
function sanitizeInput($input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Get time ago string
 */
function timeAgo($datetime): string {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('Y-m-d', $time);
    }
}

/**
 * Check if dark mode is enabled
 */
function isDarkMode(): bool {
    return isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true';
}

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is admin
 */
function isAdmin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Format JSON for pretty display
 */
function prettyJson($data): string {
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Get current page from URL
 */
function getCurrentPage(): string {
    $page = basename($_SERVER['PHP_SELF'], '.php');
    return $page ?: 'index';
}

/**
 * Build URL with query parameters
 */
function buildUrl(string $base, array $params): string {
    $query = http_build_query($params);
    return $query ? "{$base}?{$query}" : $base;
}

/**
 * Get client IP address
 */
function getClientIp(): string {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Generate random color for charts
 */
function generateChartColors(int $count): array {
    $colors = [
        'rgba(255, 99, 132, 0.6)',
        'rgba(54, 162, 235, 0.6)',
        'rgba(255, 206, 86, 0.6)',
        'rgba(75, 192, 192, 0.6)',
        'rgba(153, 102, 255, 0.6)',
        'rgba(255, 159, 64, 0.6)',
        'rgba(199, 199, 199, 0.6)',
        'rgba(83, 102, 255, 0.6)',
        'rgba(255, 99, 255, 0.6)',
        'rgba(99, 255, 132, 0.6)',
    ];
    
    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $result[] = $colors[$i % count($colors)];
    }
    return $result;
}

/**
 * Calculate trend based on historical data
 */
function calculateTrend(array $values): string {
    if (count($values) < 2) {
        return 'stable';
    }
    
    $recent = array_slice($values, -5);
    $older = array_slice($values, 0, count($values) - 5);
    
    $recentAvg = array_sum($recent) / count($recent);
    $olderAvg = array_sum($older) / count($older);
    
    $change = $olderAvg > 0 ? (($recentAvg - $olderAvg) / $olderAvg) * 100 : 0;
    
    if ($change > 10) {
        return 'improving';
    } elseif ($change < -10) {
        return 'declining';
    } else {
        return 'stable';
    }
} 