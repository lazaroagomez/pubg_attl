<?php
/**
 * Database Handler for PUBG Leaderboard
 * Manages all SQLite database operations
 */

class Database {
    private PDO $pdo;
    private static ?Database $instance = null;
    
    private function __construct() {
        $dbPath = getenv('DB_PATH') ?: '/app/database/data.sqlite';
        $dbDir = dirname($dbPath);
        
        // Create directory if it doesn't exist
        if (!file_exists($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        
        try {
            $this->pdo = new PDO("sqlite:$dbPath");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign keys
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
            // Initialize database
            $this->initDatabase();
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    private function initDatabase(): void {
        // Players table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS players (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pubg_id VARCHAR(255) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                platform VARCHAR(50) NOT NULL DEFAULT 'steam',
                shard VARCHAR(50) NOT NULL DEFAULT 'steam',
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Player stats table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS player_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                season_id VARCHAR(255),
                game_mode VARCHAR(50),
                stats_type VARCHAR(50) DEFAULT 'season', -- season, lifetime, ranked
                
                -- Basic stats
                matches_played INTEGER DEFAULT 0,
                wins INTEGER DEFAULT 0,
                top10s INTEGER DEFAULT 0,
                
                -- Combat stats
                kills INTEGER DEFAULT 0,
                deaths INTEGER DEFAULT 0,
                damage_dealt REAL DEFAULT 0,
                headshot_kills INTEGER DEFAULT 0,
                longest_kill REAL DEFAULT 0,
                road_kills INTEGER DEFAULT 0,
                vehicle_destroys INTEGER DEFAULT 0,
                
                -- Support stats
                assists INTEGER DEFAULT 0,
                dbnos INTEGER DEFAULT 0,
                revives INTEGER DEFAULT 0,
                heals INTEGER DEFAULT 0,
                boosts INTEGER DEFAULT 0,
                
                -- Survival stats
                time_survived REAL DEFAULT 0,
                walk_distance REAL DEFAULT 0,
                ride_distance REAL DEFAULT 0,
                swim_distance REAL DEFAULT 0,
                
                -- Calculated metrics
                kd_ratio REAL DEFAULT 0,
                win_rate REAL DEFAULT 0,
                top10_rate REAL DEFAULT 0,
                avg_damage REAL DEFAULT 0,
                headshot_rate REAL DEFAULT 0,
                
                -- Rating scores
                combat_score REAL DEFAULT 0,
                survival_score REAL DEFAULT 0,
                support_score REAL DEFAULT 0,
                pubg_rating REAL DEFAULT 0,
                confidence_factor REAL DEFAULT 0,
                
                -- Metadata
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
                UNIQUE(player_id, season_id, game_mode, stats_type)
            )
        ");
        
        // Weapon mastery table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS weapon_mastery (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                weapon_name VARCHAR(100) NOT NULL,
                weapon_type VARCHAR(50),
                xp INTEGER DEFAULT 0,
                level INTEGER DEFAULT 0,
                kills INTEGER DEFAULT 0,
                damage REAL DEFAULT 0,
                headshots INTEGER DEFAULT 0,
                defeats INTEGER DEFAULT 0,
                longest_defeat REAL DEFAULT 0,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
                UNIQUE(player_id, weapon_name)
            )
        ");
        
        // Seasons table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS seasons (
                id VARCHAR(255) PRIMARY KEY,
                is_current BOOLEAN DEFAULT 0,
                is_offseason BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Cache table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS cache (
                cache_key VARCHAR(255) PRIMARY KEY,
                cache_value TEXT,
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // API logs table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS api_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                endpoint VARCHAR(255),
                method VARCHAR(10),
                status_code INTEGER,
                response_time REAL,
                error_message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create indexes
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_player_stats_player ON player_stats(player_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_player_stats_season ON player_stats(season_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_weapon_mastery_player ON weapon_mastery(player_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cache_expires ON cache(expires_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_api_logs_created ON api_logs(created_at)");
    }
    
    // Player methods
    public function addPlayer(string $pubgId, string $name, string $platform = 'steam', string $shard = 'steam'): int {
        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO players (pubg_id, name, platform, shard, updated_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$pubgId, $name, $platform, $shard]);
        return $this->pdo->lastInsertId();
    }
    
    public function getActivePlayers(): array {
        $stmt = $this->pdo->query("SELECT * FROM players WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getPlayer(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    public function getPlayerByPubgId(string $pubgId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE pubg_id = ?");
        $stmt->execute([$pubgId]);
        return $stmt->fetch() ?: null;
    }
    
    // Stats methods
    public function updatePlayerStats(int $playerId, array $stats, string $seasonId = null, string $gameMode = 'squad-fpp', string $statsType = 'season'): void {
        // Calculate derived metrics
        $stats['kd_ratio'] = $stats['deaths'] > 0 ? $stats['kills'] / $stats['deaths'] : $stats['kills'];
        $stats['win_rate'] = $stats['matches_played'] > 0 ? ($stats['wins'] / $stats['matches_played']) * 100 : 0;
        $stats['top10_rate'] = $stats['matches_played'] > 0 ? ($stats['top10s'] / $stats['matches_played']) * 100 : 0;
        $stats['avg_damage'] = $stats['matches_played'] > 0 ? $stats['damage_dealt'] / $stats['matches_played'] : 0;
        $stats['headshot_rate'] = $stats['kills'] > 0 ? ($stats['headshot_kills'] / $stats['kills']) * 100 : 0;
        
        $sql = "
            INSERT OR REPLACE INTO player_stats (
                player_id, season_id, game_mode, stats_type,
                matches_played, wins, top10s,
                kills, deaths, damage_dealt, headshot_kills, longest_kill, road_kills, vehicle_destroys,
                assists, dbnos, revives, heals, boosts,
                time_survived, walk_distance, ride_distance, swim_distance,
                kd_ratio, win_rate, top10_rate, avg_damage, headshot_rate,
                combat_score, survival_score, support_score, pubg_rating, confidence_factor,
                last_updated
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                CURRENT_TIMESTAMP
            )
        ";
        
        $params = [
            $playerId, $seasonId, $gameMode, $statsType,
            $stats['matches_played'] ?? 0,
            $stats['wins'] ?? 0,
            $stats['top10s'] ?? 0,
            $stats['kills'] ?? 0,
            $stats['deaths'] ?? 0,
            $stats['damage_dealt'] ?? 0,
            $stats['headshot_kills'] ?? 0,
            $stats['longest_kill'] ?? 0,
            $stats['road_kills'] ?? 0,
            $stats['vehicle_destroys'] ?? 0,
            $stats['assists'] ?? 0,
            $stats['dbnos'] ?? 0,
            $stats['revives'] ?? 0,
            $stats['heals'] ?? 0,
            $stats['boosts'] ?? 0,
            $stats['time_survived'] ?? 0,
            $stats['walk_distance'] ?? 0,
            $stats['ride_distance'] ?? 0,
            $stats['swim_distance'] ?? 0,
            $stats['kd_ratio'],
            $stats['win_rate'],
            $stats['top10_rate'],
            $stats['avg_damage'],
            $stats['headshot_rate'],
            $stats['combat_score'] ?? 0,
            $stats['survival_score'] ?? 0,
            $stats['support_score'] ?? 0,
            $stats['pubg_rating'] ?? 0,
            $stats['confidence_factor'] ?? 0
        ];
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    public function getPlayerStats(int $playerId, string $seasonId = null, string $gameMode = null): array {
        $sql = "SELECT * FROM player_stats WHERE player_id = ?";
        $params = [$playerId];
        
        if ($seasonId) {
            $sql .= " AND season_id = ?";
            $params[] = $seasonId;
        }
        
        if ($gameMode) {
            $sql .= " AND game_mode = ?";
            $params[] = $gameMode;
        }
        
        $sql .= " ORDER BY last_updated DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getLeaderboard(string $seasonId = null, string $gameMode = 'squad-fpp', string $orderBy = 'pubg_rating', int $limit = 50): array {
        $sql = "
            SELECT 
                p.*,
                ps.*,
                p.name as player_name,
                p.pubg_id as player_pubg_id
            FROM player_stats ps
            JOIN players p ON ps.player_id = p.id
            WHERE p.is_active = 1
        ";
        
        $params = [];
        
        if ($seasonId) {
            $sql .= " AND ps.season_id = ?";
            $params[] = $seasonId;
        }
        
        if ($gameMode) {
            $sql .= " AND ps.game_mode = ?";
            $params[] = $gameMode;
        }
        
        $allowedOrderBy = ['pubg_rating', 'combat_score', 'survival_score', 'support_score', 'kd_ratio', 'wins', 'kills'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'pubg_rating';
        }
        
        $sql .= " ORDER BY ps.$orderBy DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Cache methods
    public function setCache(string $key, $value, int $ttl = 3600): void {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO cache (cache_key, cache_value, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$key, json_encode($value), $expiresAt]);
    }
    
    public function getCache(string $key) {
        // Clean expired cache
        $this->pdo->exec("DELETE FROM cache WHERE expires_at < datetime('now')");
        
        $stmt = $this->pdo->prepare("
            SELECT cache_value FROM cache 
            WHERE cache_key = ? AND expires_at > datetime('now')
        ");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? json_decode($result['cache_value'], true) : null;
    }
    
    // Season methods
    public function updateSeasons(array $seasons): void {
        // Reset all seasons
        $this->pdo->exec("UPDATE seasons SET is_current = 0, is_offseason = 0");
        
        foreach ($seasons as $season) {
            $stmt = $this->pdo->prepare("
                INSERT OR REPLACE INTO seasons (id, is_current, is_offseason)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $season['id'],
                $season['isCurrentSeason'] ? 1 : 0,
                $season['isOffseason'] ? 1 : 0
            ]);
        }
    }
    
    public function getCurrentSeason(): ?array {
        $stmt = $this->pdo->query("SELECT * FROM seasons WHERE is_current = 1 LIMIT 1");
        return $stmt->fetch() ?: null;
    }
    
    // API logging
    public function logApiCall(string $endpoint, string $method, int $statusCode, float $responseTime, ?string $error = null): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO api_logs (endpoint, method, status_code, response_time, error_message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$endpoint, $method, $statusCode, $responseTime, $error]);
    }
    
    // Weapon mastery methods
    public function updateWeaponMastery(int $playerId, array $weapons): void {
        foreach ($weapons as $weapon) {
            $stmt = $this->pdo->prepare("
                INSERT OR REPLACE INTO weapon_mastery (
                    player_id, weapon_name, weapon_type, xp, level, 
                    kills, damage, headshots, defeats, longest_defeat,
                    last_updated
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $playerId,
                $weapon['name'],
                $weapon['type'] ?? null,
                $weapon['xp'] ?? 0,
                $weapon['level'] ?? 0,
                $weapon['kills'] ?? 0,
                $weapon['damage'] ?? 0,
                $weapon['headshots'] ?? 0,
                $weapon['defeats'] ?? 0,
                $weapon['longest_defeat'] ?? 0
            ]);
        }
    }
    
    public function getWeaponMastery(int $playerId): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM weapon_mastery 
            WHERE player_id = ? 
            ORDER BY kills DESC
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }
    
    public function getPDO(): PDO {
        return $this->pdo;
    }
} 