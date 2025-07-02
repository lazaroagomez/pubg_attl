<?php
/**
 * Cron script for automatic data fetching
 * This script runs continuously in the Docker container
 */

require_once __DIR__ . '/lib/database.php';
require_once __DIR__ . '/lib/pubg-api.php';
require_once __DIR__ . '/lib/rating.php';

// Set execution time limit to unlimited
set_time_limit(0);

// Initialize components
$db = Database::getInstance();
$api = PubgApi::getInstance();

// Log function
function logMessage($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
}

// Main update function
function updatePlayerStats() {
    global $db, $api;
    
    logMessage("Starting player stats update cycle...");
    
    try {
        // Update seasons first
        logMessage("Fetching seasons...");
        $seasons = $api->getSeasons();
        if (!empty($seasons)) {
            $seasonsData = array_map(function($season) {
                return [
                    'id' => $season['id'],
                    'isCurrentSeason' => $season['attributes']['isCurrentSeason'] ?? false,
                    'isOffseason' => $season['attributes']['isOffseason'] ?? false
                ];
            }, $seasons);
            $db->updateSeasons($seasonsData);
            logMessage("Updated " . count($seasonsData) . " seasons");
        }
        
        // Get current season
        $currentSeason = $db->getCurrentSeason();
        if (!$currentSeason) {
            logMessage("WARNING: No current season found, will use lifetime stats");
        }
        
        // Get all active players
        $players = $db->getActivePlayers();
        logMessage("Found " . count($players) . " active players to update");
        
        if (empty($players)) {
            logMessage("No active players found. Skipping update.");
            return;
        }
        
        // Prepare player IDs for batch operations
        $playerIds = array_column($players, 'pubg_id');
        $playerIdToDbId = array_combine(
            array_column($players, 'pubg_id'),
            array_column($players, 'id')
        );
        
        // Default game mode
        $gameMode = 'squad-fpp';
        
        // Batch fetch stats if we have a current season
        if ($currentSeason) {
            logMessage("Batch fetching season stats for game mode: $gameMode");
            $batchStats = $api->batchGetSeasonStats($playerIds, $currentSeason['id'], $gameMode);
            
            foreach ($batchStats as $pubgId => $statsData) {
                if (!isset($playerIdToDbId[$pubgId])) {
                    continue;
                }
                
                $playerId = $playerIdToDbId[$pubgId];
                $stats = $api->parseStats($statsData, $gameMode);
                
                // Calculate ratings
                $ratings = RatingSystem::calculateRatings($stats);
                $stats = array_merge($stats, $ratings);
                
                // Update database
                $db->updatePlayerStats($playerId, $stats, $currentSeason['id'], $gameMode, 'season');
                logMessage("Updated stats for player ID: $playerId");
            }
        }
        
        // Fetch individual lifetime stats as fallback
        foreach ($players as $player) {
            logMessage("Fetching lifetime stats for: " . $player['name']);
            $lifetimeData = $api->getPlayerLifetimeStats($player['pubg_id']);
            
            if ($lifetimeData) {
                $stats = $api->parseStats($lifetimeData);
                
                // Calculate ratings
                $ratings = RatingSystem::calculateRatings(array_merge($stats, ['stats_type' => 'lifetime']));
                $stats = array_merge($stats, $ratings);
                
                // Update database
                $db->updatePlayerStats($player['id'], $stats, 'lifetime', 'all', 'lifetime');
                logMessage("Updated lifetime stats for: " . $player['name']);
            }
            
            // Fetch weapon mastery
            logMessage("Fetching weapon mastery for: " . $player['name']);
            $weaponData = $api->getWeaponMastery($player['pubg_id']);
            
            if ($weaponData) {
                $weapons = $api->parseWeaponMastery($weaponData);
                if (!empty($weapons)) {
                    $db->updateWeaponMastery($player['id'], $weapons);
                    logMessage("Updated weapon mastery for: " . $player['name'] . " (" . count($weapons) . " weapons)");
                }
            }
            
            // Small delay between individual requests to respect rate limits
            sleep(6); // 10 requests per minute = 1 request every 6 seconds
        }
        
        logMessage("Player stats update cycle completed successfully");
        
    } catch (Exception $e) {
        logMessage("ERROR: " . $e->getMessage());
        // Don't exit, just log the error and continue
    }
}

// Clean up old data function
function cleanupOldData() {
    global $db;
    
    try {
        logMessage("Cleaning up old data...");
        
        // Clean API logs older than 7 days
        $stmt = $db->getPDO()->prepare("
            DELETE FROM api_logs 
            WHERE created_at < datetime('now', '-7 days')
        ");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        
        if ($deleted > 0) {
            logMessage("Deleted $deleted old API log entries");
        }
        
        // Vacuum database to reclaim space
        $db->getPDO()->exec("VACUUM");
        logMessage("Database vacuumed");
        
    } catch (Exception $e) {
        logMessage("ERROR during cleanup: " . $e->getMessage());
    }
}

// Main loop
logMessage("PUBG Leaderboard Cron Started");
logMessage("Update interval: 60 seconds");

$iteration = 0;
while (true) {
    $iteration++;
    logMessage("=== Starting iteration #$iteration ===");
    
    // Update player stats
    updatePlayerStats();
    
    // Clean up old data every 10 iterations (10 minutes)
    if ($iteration % 10 === 0) {
        cleanupOldData();
    }
    
    // Sleep for 60 seconds
    logMessage("Sleeping for 60 seconds...");
    sleep(60);
} 