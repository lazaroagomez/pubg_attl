<?php
/**
 * PUBG API Wrapper
 * Handles all PUBG API interactions with rate limiting, caching, and batch operations
 */

require_once __DIR__ . '/database.php';

class PubgApi {
    private string $apiKey;
    private string $platform;
    private string $shard;
    private Database $db;
    private static ?PubgApi $instance = null;
    
    // API configuration
    private const BASE_URL = 'https://api.pubg.com';
    private const RATE_LIMIT = 10;
    private const RATE_WINDOW = 60; // seconds
    
    // Cache TTL configuration (in seconds)
    private array $cacheTTL = [
        'seasons' => 86400,        // 24h
        'player_stats' => 3600,    // 1h
        'weapon_mastery' => 21600, // 6h
        'leaderboards' => 7200,    // 2h
        'lifetime_stats' => 43200, // 12h
        'player_lookup' => 3600,   // 1h
    ];
    
    private function __construct() {
        $this->apiKey = getenv('PUBG_API_KEY') ?: '';
        $this->platform = getenv('PUBG_PLATFORM') ?: 'steam';
        $this->shard = getenv('PUBG_SHARD') ?: 'steam';
        $this->db = Database::getInstance();
        
        // Override cache TTLs from environment if set
        foreach ($this->cacheTTL as $key => $default) {
            $envKey = 'CACHE_TTL_' . strtoupper($key);
            $envValue = getenv($envKey);
            if ($envValue !== false) {
                $this->cacheTTL[$key] = (int)$envValue;
            }
        }
    }
    
    public static function getInstance(): PubgApi {
        if (self::$instance === null) {
            self::$instance = new PubgApi();
        }
        return self::$instance;
    }
    
    /**
     * Make an API request with rate limiting and caching
     */
    private function makeRequest(string $endpoint, string $cacheKey = null, int $cacheTTL = null): ?array {
        // Check cache first
        if ($cacheKey) {
            $cached = $this->db->getCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        // Check rate limiting
        if (!$this->checkRateLimit()) {
            throw new Exception('Rate limit exceeded. Please wait before making more requests.');
        }
        
        $url = self::BASE_URL . $endpoint;
        $startTime = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/vnd.api+json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = microtime(true) - $startTime;
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log API call
        $this->db->logApiCall($endpoint, 'GET', $statusCode, $responseTime, $error ?: null);
        
        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }
        
        if ($statusCode !== 200) {
            $errorMessage = "API Error: HTTP $statusCode";
            if ($response) {
                $decoded = json_decode($response, true);
                if (isset($decoded['errors'][0]['detail'])) {
                    $errorMessage .= ' - ' . $decoded['errors'][0]['detail'];
                }
            }
            throw new Exception($errorMessage);
        }
        
        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception('Invalid JSON response from API');
        }
        
        // Cache the response
        if ($cacheKey && $cacheTTL) {
            $this->db->setCache($cacheKey, $data, $cacheTTL);
        }
        
        return $data;
    }
    
    /**
     * Check if we're within rate limits
     */
    private function checkRateLimit(): bool {
        $stmt = $this->db->getPDO()->prepare("
            SELECT COUNT(*) as count 
            FROM api_logs 
            WHERE created_at > datetime('now', '-' || ? || ' seconds')
        ");
        $stmt->execute([self::RATE_WINDOW]);
        $result = $stmt->fetch();
        
        return $result['count'] < self::RATE_LIMIT;
    }
    
    /**
     * Get all seasons
     */
    public function getSeasons(): array {
        $endpoint = "/shards/{$this->shard}/seasons";
        $cacheKey = "seasons_{$this->shard}";
        
        try {
            $response = $this->makeRequest($endpoint, $cacheKey, $this->cacheTTL['seasons']);
            return $response['data'] ?? [];
        } catch (Exception $e) {
            error_log("Failed to fetch seasons: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get current season
     */
    public function getCurrentSeason(): ?array {
        $seasons = $this->getSeasons();
        foreach ($seasons as $season) {
            if (isset($season['attributes']['isCurrentSeason']) && $season['attributes']['isCurrentSeason']) {
                return $season;
            }
        }
        return null;
    }
    
    /**
     * Lookup players by names (batch operation)
     */
    public function lookupPlayersByNames(array $names): array {
        $chunks = array_chunk($names, 10); // API limit is 10 players per request
        $allPlayers = [];
        
        foreach ($chunks as $chunk) {
            $namesParam = implode(',', $chunk);
            $endpoint = "/shards/{$this->shard}/players?filter[playerNames]={$namesParam}";
            $cacheKey = "player_lookup_" . md5($namesParam);
            
            try {
                $response = $this->makeRequest($endpoint, $cacheKey, $this->cacheTTL['player_lookup']);
                if (isset($response['data'])) {
                    $allPlayers = array_merge($allPlayers, $response['data']);
                }
            } catch (Exception $e) {
                error_log("Failed to lookup players: " . $e->getMessage());
            }
        }
        
        return $allPlayers;
    }
    
    /**
     * Get player season stats
     */
    public function getPlayerSeasonStats(string $playerId, string $seasonId): ?array {
        $endpoint = "/shards/{$this->shard}/players/{$playerId}/seasons/{$seasonId}";
        $cacheKey = "player_stats_{$playerId}_{$seasonId}";
        
        try {
            $response = $this->makeRequest($endpoint, $cacheKey, $this->cacheTTL['player_stats']);
            return $response['data'] ?? null;
        } catch (Exception $e) {
            error_log("Failed to fetch player season stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get player lifetime stats
     */
    public function getPlayerLifetimeStats(string $playerId): ?array {
        $endpoint = "/shards/{$this->shard}/players/{$playerId}/seasons/lifetime";
        $cacheKey = "player_lifetime_{$playerId}";
        
        try {
            $response = $this->makeRequest($endpoint, $cacheKey, $this->cacheTTL['lifetime_stats']);
            return $response['data'] ?? null;
        } catch (Exception $e) {
            error_log("Failed to fetch player lifetime stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get player ranked stats
     */
    public function getPlayerRankedStats(string $playerId, string $seasonId): ?array {
        $endpoint = "/shards/{$this->shard}/players/{$playerId}/seasons/{$seasonId}/ranked";
        $cacheKey = "player_ranked_{$playerId}_{$seasonId}";
        
        try {
            $response = $this->makeRequest($endpoint, $cacheKey, $this->cacheTTL['player_stats']);
            return $response['data'] ?? null;
        } catch (Exception $e) {
            error_log("Failed to fetch player ranked stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Batch get season stats for multiple players (optimized)
     */
    public function batchGetSeasonStats(array $playerIds, string $seasonId, string $gameMode): array {
        $chunks = array_chunk($playerIds, 10); // API limit
        $allStats = [];
        
        foreach ($chunks as $chunk) {
            $idsParam = implode(',', $chunk);
            $endpoint = "/shards/{$this->shard}/seasons/{$seasonId}/gameMode/{$gameMode}/players?filter[playerIds]={$idsParam}";
            $cacheKey = "batch_stats_" . md5("{$seasonId}_{$gameMode}_{$idsParam}");
            
            try {
                $response = $this->makeRequest($endpoint, $cacheKey, $this->cacheTTL['player_stats']);
                if (isset($response['data'])) {
                    foreach ($response['data'] as $playerData) {
                        $allStats[$playerData['relationships']['player']['data']['id']] = $playerData;
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to batch fetch stats: " . $e->getMessage());
            }
        }
        
        return $allStats;
    }
    
    /**
     * Get weapon mastery for a player
     */
    public function getWeaponMastery(string $playerId): ?array {
        $endpoint = "/shards/{$this->shard}/players/{$playerId}/weapon_mastery";
        $cacheKey = "weapon_mastery_{$playerId}";
        
        try {
            $response = $this->makeRequest($endpoint, $cacheKey, $this->cacheTTL['weapon_mastery']);
            return $response['data'] ?? null;
        } catch (Exception $e) {
            error_log("Failed to fetch weapon mastery: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get leaderboard for a season/game mode
     */
    public function getLeaderboard(string $seasonId, string $gameMode): array {
        $endpoint = "/shards/{$this->shard}/leaderboards/{$seasonId}/{$gameMode}";
        $cacheKey = "leaderboard_{$seasonId}_{$gameMode}";
        
        try {
            $response = $this->makeRequest($endpoint, $cacheKey, $this->cacheTTL['leaderboards']);
            return $response['data'] ?? [];
        } catch (Exception $e) {
            error_log("Failed to fetch leaderboard: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Parse stats from API response to database format
     */
    public function parseStats(array $apiData, string $gameMode = null): array {
        $attributes = $apiData['attributes'] ?? [];
        $stats = $attributes['gameModeStats'][$gameMode] ?? $attributes['overall'] ?? [];
        
        return [
            'matches_played' => $stats['roundsPlayed'] ?? 0,
            'wins' => $stats['wins'] ?? 0,
            'top10s' => $stats['top10s'] ?? 0,
            'kills' => $stats['kills'] ?? 0,
            'deaths' => $stats['losses'] ?? 0,
            'damage_dealt' => $stats['damageDealt'] ?? 0,
            'headshot_kills' => $stats['headshotKills'] ?? 0,
            'longest_kill' => $stats['longestKill'] ?? 0,
            'road_kills' => $stats['roadKills'] ?? 0,
            'vehicle_destroys' => $stats['vehicleDestroys'] ?? 0,
            'assists' => $stats['assists'] ?? 0,
            'dbnos' => $stats['dBNOs'] ?? 0,
            'revives' => $stats['revives'] ?? 0,
            'heals' => $stats['heals'] ?? 0,
            'boosts' => $stats['boosts'] ?? 0,
            'time_survived' => $stats['timeSurvived'] ?? 0,
            'walk_distance' => $stats['walkDistance'] ?? 0,
            'ride_distance' => $stats['rideDistance'] ?? 0,
            'swim_distance' => $stats['swimDistance'] ?? 0,
        ];
    }
    
    /**
     * Parse weapon mastery data
     */
    public function parseWeaponMastery(array $apiData): array {
        $weapons = [];
        $attributes = $apiData['attributes'] ?? [];
        
        foreach ($attributes as $weaponId => $weaponData) {
            if (is_array($weaponData) && isset($weaponData['XPTotal'])) {
                $weapons[] = [
                    'name' => $weaponId,
                    'xp' => $weaponData['XPTotal'] ?? 0,
                    'level' => $weaponData['LevelCurrent'] ?? 0,
                    'kills' => $weaponData['Kills'] ?? 0,
                    'damage' => $weaponData['DamagePlayer'] ?? 0,
                    'headshots' => $weaponData['HeadShots'] ?? 0,
                    'defeats' => $weaponData['Defeats'] ?? 0,
                    'longest_defeat' => $weaponData['LongestDefeat'] ?? 0,
                ];
            }
        }
        
        return $weapons;
    }
    
    /**
     * Get available game modes
     */
    public function getGameModes(): array {
        return [
            'solo' => 'Solo TPP',
            'solo-fpp' => 'Solo FPP',
            'duo' => 'Duo TPP',
            'duo-fpp' => 'Duo FPP',
            'squad' => 'Squad TPP',
            'squad-fpp' => 'Squad FPP',
            'normal-solo' => 'Normal Solo',
            'normal-duo' => 'Normal Duo',
            'normal-squad' => 'Normal Squad',
        ];
    }
    
    /**
     * Get available platforms/shards
     */
    public function getPlatforms(): array {
        return [
            'steam' => 'PC (Steam)',
            'xbox' => 'Xbox',
            'psn' => 'PlayStation',
            'stadia' => 'Stadia',
            'console' => 'Console (Combined)',
            'kakao' => 'Kakao (Korea)',
        ];
    }
} 