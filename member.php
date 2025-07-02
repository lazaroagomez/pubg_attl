<?php
/**
 * Individual player profile page
 */

session_start();

require_once __DIR__ . '/lib/database.php';
require_once __DIR__ . '/lib/rating.php';
require_once __DIR__ . '/lib/confidence.php';
require_once __DIR__ . '/lib/utils.php';

$db = Database::getInstance();

// Get player ID from URL
$playerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$playerId) {
    header('Location: /');
    exit;
}

// Get player info
$player = $db->getPlayer($playerId);

if (!$player) {
    header('Location: /');
    exit;
}

// Get all stats for this player
$allStats = $db->getPlayerStats($playerId);

// Get current season stats (default view)
$currentStats = null;
foreach ($allStats as $stat) {
    if ($stat['stats_type'] === 'season' && $stat['game_mode'] === 'squad-fpp') {
        $currentStats = $stat;
        break;
    }
}

// Fallback to lifetime stats if no season stats
if (!$currentStats) {
    foreach ($allStats as $stat) {
        if ($stat['stats_type'] === 'lifetime') {
            $currentStats = $stat;
            break;
        }
    }
}

// Get weapon mastery
$weapons = $db->getWeaponMastery($playerId);

// Group weapons by category
$weaponsByCategory = [];
foreach ($weapons as $weapon) {
    $category = getWeaponCategory($weapon['weapon_name']);
    if (!isset($weaponsByCategory[$category])) {
        $weaponsByCategory[$category] = [];
    }
    $weaponsByCategory[$category][] = $weapon;
}

// Sort categories and weapons
ksort($weaponsByCategory);
foreach ($weaponsByCategory as &$categoryWeapons) {
    usort($categoryWeapons, fn($a, $b) => $b['kills'] - $a['kills']);
}

$pageTitle = htmlspecialchars($player['name']) . " - Perfil";
require_once __DIR__ . '/templates/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Player Header -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <img class="h-20 w-20 rounded-full" 
                             src="<?php echo getGravatarUrl($player['name'], 160); ?>" 
                             alt="">
                        <div class="ml-5">
                            <h3 class="text-2xl font-bold leading-6 text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($player['name']); ?>
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                                <?php echo htmlspecialchars($player['platform']); ?> • 
                                Actualizado <?php echo timeAgo($player['updated_at']); ?>
                            </p>
                            <?php if ($currentStats): ?>
                            <div class="mt-2">
                                <?php echo ConfidenceSystem::formatConfidenceIndicator($currentStats['matches_played']); ?>
                                <span class="ml-2 text-sm text-gray-500">
                                    <?php echo $currentStats['matches_played']; ?> partidas jugadas
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <a href="/" class="text-sm text-orange-600 hover:text-orange-900 dark:text-orange-400">
                            ← Volver al leaderboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($currentStats): ?>
        <!-- Rating Overview -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5 mb-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        PUBG Rating
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold <?php echo RatingSystem::getRatingColorClass($currentStats['pubg_rating']); ?>">
                        <?php echo RatingSystem::formatRating($currentStats['pubg_rating']); ?>
                    </dd>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Combat Score
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold <?php echo RatingSystem::getRatingColorClass($currentStats['combat_score']); ?>">
                        ⚔️ <?php echo number_format($currentStats['combat_score'], 1); ?>
                    </dd>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Survival Score
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold <?php echo RatingSystem::getRatingColorClass($currentStats['survival_score']); ?>">
                        🛡️ <?php echo number_format($currentStats['survival_score'], 1); ?>
                    </dd>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Support Score
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold <?php echo RatingSystem::getRatingColorClass($currentStats['support_score']); ?>">
                        🤝 <?php echo number_format($currentStats['support_score'], 1); ?>
                    </dd>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Confidence
                    </dt>
                    <dd class="mt-1">
                        <?php echo ConfidenceSystem::getProgressBar($currentStats['matches_played']); ?>
                    </dd>
                </div>
            </div>
        </div>
        
        <!-- Rating Breakdown -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    Desglose de Rating
                </h3>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-5 sm:px-6">
                <canvas id="ratingChart" class="max-h-64"></canvas>
            </div>
        </div>
        
        <!-- Detailed Stats -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Combat Stats -->
            <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                        ⚔️ Estadísticas de Combate
                    </h3>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700">
                    <dl>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Kills</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['kills']; ?> 
                                <span class="text-gray-500">(<?php echo number_format($currentStats['kills'] / max(1, $currentStats['matches_played']), 2); ?> por partida)</span>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">K/D Ratio</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo number_format($currentStats['kd_ratio'], 2); ?>
                            </dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Damage</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo formatNumber($currentStats['damage_dealt']); ?> 
                                <span class="text-gray-500">(<?php echo round($currentStats['avg_damage']); ?> por partida)</span>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Headshot %</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo number_format($currentStats['headshot_rate'], 1); ?>%
                                <span class="text-gray-500">(<?php echo $currentStats['headshot_kills']; ?> headshots)</span>
                            </dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Longest Kill</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo formatDistance($currentStats['longest_kill']); ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
            
            <!-- Survival Stats -->
            <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                        🛡️ Estadísticas de Supervivencia
                    </h3>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700">
                    <dl>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Wins</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['wins']; ?> 
                                <span class="text-gray-500">(<?php echo number_format($currentStats['win_rate'], 1); ?>%)</span>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Top 10</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['top10s']; ?> 
                                <span class="text-gray-500">(<?php echo number_format($currentStats['top10_rate'], 1); ?>%)</span>
                            </dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tiempo Sobrevivido</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo formatDuration($currentStats['time_survived']); ?>
                                <span class="text-gray-500">(<?php echo formatDuration($currentStats['time_survived'] / max(1, $currentStats['matches_played'])); ?> promedio)</span>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Distancia Total</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo formatDistance($currentStats['walk_distance'] + $currentStats['ride_distance'] + $currentStats['swim_distance']); ?>
                            </dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Partidas Jugadas</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['matches_played']; ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
            
            <!-- Support Stats -->
            <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                        🤝 Estadísticas de Soporte
                    </h3>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700">
                    <dl>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Assists</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['assists']; ?>
                                <span class="text-gray-500">(<?php echo number_format($currentStats['assists'] / max(1, $currentStats['matches_played']), 2); ?> por partida)</span>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Revives</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['revives']; ?>
                                <span class="text-gray-500">(<?php echo number_format($currentStats['revives'] / max(1, $currentStats['matches_played']), 2); ?> por partida)</span>
                            </dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">DBNOs</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['dbnos']; ?>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Heals</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['heals']; ?>
                            </dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Boosts</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['boosts']; ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
            
            <!-- Other Stats -->
            <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                        📊 Otras Estadísticas
                    </h3>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700">
                    <dl>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Road Kills</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['road_kills']; ?>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Vehicle Destroys</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo $currentStats['vehicle_destroys']; ?>
                            </dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Walk Distance</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo formatDistance($currentStats['walk_distance']); ?>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Ride Distance</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo formatDistance($currentStats['ride_distance']); ?>
                            </dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Swim Distance</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                                <?php echo formatDistance($currentStats['swim_distance']); ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
        
        <!-- Weapon Mastery -->
        <?php if (!empty($weapons)): ?>
        <div class="mt-6 bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    🔫 Maestría de Armas
                </h3>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700 p-6">
                <?php foreach ($weaponsByCategory as $category => $categoryWeapons): ?>
                <div class="mb-6">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">
                        <?php echo htmlspecialchars($category); ?>
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($categoryWeapons as $weapon): ?>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h5 class="font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($weapon['weapon_name']); ?>
                                </h5>
                                <span class="text-sm text-gray-500">Lvl <?php echo $weapon['level']; ?></span>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                <div>Kills: <span class="font-medium text-gray-900 dark:text-white"><?php echo $weapon['kills']; ?></span></div>
                                <div>Damage: <span class="font-medium text-gray-900 dark:text-white"><?php echo formatNumber($weapon['damage']); ?></span></div>
                                <div>Headshots: <span class="font-medium text-gray-900 dark:text-white"><?php echo $weapon['headshots']; ?></span></div>
                                <?php if ($weapon['longest_defeat'] > 0): ?>
                                <div>Longest: <span class="font-medium text-gray-900 dark:text-white"><?php echo formatDistance($weapon['longest_defeat']); ?></span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- All Stats Modes -->
        <div class="mt-6 bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    📋 Todas las Estadísticas
                </h3>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Temporada/Modo
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Partidas
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Rating
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    K/D
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Wins
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Avg DMG
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($allStats as $stat): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    <?php 
                                    if ($stat['stats_type'] === 'lifetime') {
                                        echo 'Lifetime';
                                    } else {
                                        echo getSeasonDisplayName($stat['season_id'] ?? 'Unknown');
                                    }
                                    ?> - <?php echo strtoupper(str_replace('-', ' ', $stat['game_mode'] ?? 'all')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-white">
                                    <?php echo $stat['matches_played']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-sm font-medium <?php echo RatingSystem::getRatingColorClass($stat['pubg_rating']); ?>">
                                        <?php echo number_format($stat['pubg_rating'], 1); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-white">
                                    <?php echo number_format($stat['kd_ratio'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-white">
                                    <?php echo $stat['wins']; ?>
                                    <span class="text-gray-500">(<?php echo number_format($stat['win_rate'], 1); ?>%)</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-white">
                                    <?php echo round($stat['avg_damage']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- No Stats Available -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                        No hay estadísticas disponibles
                    </h3>
                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                        <p>Las estadísticas de este jugador aún no han sido actualizadas. Por favor, espera unos minutos.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($currentStats): ?>
<script>
// Rating breakdown chart
const ctx = document.getElementById('ratingChart').getContext('2d');
const ratingBreakdown = <?php echo json_encode(RatingSystem::getRatingBreakdown($currentStats)); ?>;

new Chart(ctx, {
    type: 'radar',
    data: {
        labels: Object.values(ratingBreakdown).map(r => r.label),
        datasets: [{
            label: 'Score',
            data: Object.values(ratingBreakdown).map(r => r.score),
            backgroundColor: 'rgba(251, 146, 60, 0.2)',
            borderColor: 'rgba(251, 146, 60, 1)',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            r: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    stepSize: 20
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?> 