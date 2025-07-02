<?php
/**
 * API Status endpoint
 * Returns current API rate limit status
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../lib/database.php';

$db = Database::getInstance();

// Calculate remaining API calls
$stmt = $db->getPDO()->prepare("
    SELECT COUNT(*) as used_calls
    FROM api_logs 
    WHERE created_at > datetime('now', '-60 seconds')
");
$stmt->execute();
$result = $stmt->fetch();

$apiLimit = (int)(getenv('API_RATE_LIMIT') ?: 10);
$usedCalls = $result['used_calls'] ?? 0;
$remainingCalls = max(0, $apiLimit - $usedCalls);

// Get last update time
$stmt = $db->getPDO()->query("
    SELECT MAX(last_updated) as last_update 
    FROM player_stats
");
$lastUpdate = $stmt->fetch()['last_update'] ?? null;

echo json_encode([
    'api_calls_remaining' => $remainingCalls,
    'api_calls_limit' => $apiLimit,
    'api_calls_used' => $usedCalls,
    'last_data_update' => $lastUpdate,
    'status' => 'operational'
]); 