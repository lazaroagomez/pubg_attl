<?php
/**
 * Main leaderboard page
 */

session_start();

require_once __DIR__ . '/lib/database.php';
require_once __DIR__ . '/lib/rating.php';
require_once __DIR__ . '/lib/confidence.php';
require_once __DIR__ . '/lib/utils.php';

$db = Database::getInstance();

// Get filter parameters
$seasonId = $_GET['season'] ?? null;
$gameMode = $_GET['mode'] ?? 'squad-fpp';
$orderBy = $_GET['sort'] ?? 'pubg_rating';
$confidenceFilter = $_GET['confidence'] ?? 'all';

// Get current season if not specified
if (!$seasonId) {
    $currentSeason = $db->getCurrentSeason();
    $seasonId = $currentSeason ? $currentSeason['id'] : 'lifetime';
}

// Get leaderboard data
$players = $db->getLeaderboard($seasonId, $gameMode, $orderBy, 100);

// Apply confidence filter
if ($confidenceFilter !== 'all') {
    $players = ConfidenceSystem::filterByConfidence($players, $confidenceFilter);
}

// Get available seasons for dropdown
$seasons = $db->getPDO()->query("SELECT * FROM seasons ORDER BY id DESC")->fetchAll();

$pageTitle = "Leaderboard - " . getenv('CLAN_NAME');
require_once __DIR__ . '/templates/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="md:flex md:items-center md:justify-between mb-6">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:text-3xl sm:truncate">
                    🏆 Clan Leaderboard
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Ranking completo con sistema de confianza basado en partidas jugadas
                </p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <button onclick="location.reload()" 
                        class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Actualizar
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Season Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Temporada</label>
                    <select name="season" 
                            onchange="this.form.submit()"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm rounded-md">
                        <option value="lifetime" <?php echo $seasonId === 'lifetime' ? 'selected' : ''; ?>>
                            Lifetime Stats
                        </option>
                        <?php foreach ($seasons as $season): ?>
                        <option value="<?php echo htmlspecialchars($season['id']); ?>" 
                                <?php echo $seasonId === $season['id'] ? 'selected' : ''; ?>>
                            <?php echo getSeasonDisplayName($season['id']); ?>
                            <?php echo $season['is_current'] ? '(Actual)' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Game Mode Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Modo de Juego</label>
                    <select name="mode" 
                            onchange="this.form.submit()"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm rounded-md">
                        <option value="squad-fpp" <?php echo $gameMode === 'squad-fpp' ? 'selected' : ''; ?>>Squad FPP</option>
                        <option value="squad" <?php echo $gameMode === 'squad' ? 'selected' : ''; ?>>Squad TPP</option>
                        <option value="duo-fpp" <?php echo $gameMode === 'duo-fpp' ? 'selected' : ''; ?>>Duo FPP</option>
                        <option value="duo" <?php echo $gameMode === 'duo' ? 'selected' : ''; ?>>Duo TPP</option>
                        <option value="solo-fpp" <?php echo $gameMode === 'solo-fpp' ? 'selected' : ''; ?>>Solo FPP</option>
                        <option value="solo" <?php echo $gameMode === 'solo' ? 'selected' : ''; ?>>Solo TPP</option>
                    </select>
                </div>
                
                <!-- Sort By Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ordenar por</label>
                    <select name="sort" 
                            onchange="this.form.submit()"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm rounded-md">
                        <option value="pubg_rating" <?php echo $orderBy === 'pubg_rating' ? 'selected' : ''; ?>>PUBG Rating</option>
                        <option value="combat_score" <?php echo $orderBy === 'combat_score' ? 'selected' : ''; ?>>Combat Score</option>
                        <option value="survival_score" <?php echo $orderBy === 'survival_score' ? 'selected' : ''; ?>>Survival Score</option>
                        <option value="support_score" <?php echo $orderBy === 'support_score' ? 'selected' : ''; ?>>Support Score</option>
                        <option value="kd_ratio" <?php echo $orderBy === 'kd_ratio' ? 'selected' : ''; ?>>K/D Ratio</option>
                        <option value="wins" <?php echo $orderBy === 'wins' ? 'selected' : ''; ?>>Victorias</option>
                        <option value="kills" <?php echo $orderBy === 'kills' ? 'selected' : ''; ?>>Kills</option>
                    </select>
                </div>
                
                <!-- Confidence Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nivel de Confianza</label>
                    <select name="confidence" 
                            onchange="this.form.submit()"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm rounded-md">
                        <option value="all" <?php echo $confidenceFilter === 'all' ? 'selected' : ''; ?>>Todos los jugadores</option>
                        <option value="high" <?php echo $confidenceFilter === 'high' ? 'selected' : ''; ?>>Alta confianza (50+ partidas)</option>
                        <option value="medium" <?php echo $confidenceFilter === 'medium' ? 'selected' : ''; ?>>Media/Alta (20+ partidas)</option>
                    </select>
                </div>
            </form>
        </div>
        
        <!-- Leaderboard Table -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Rank
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Jugador
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                PUBG Rating
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Combat
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Survival
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Support
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                K/D
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Partidas
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Wins
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Avg DMG
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php 
                        $rank = 1;
                        foreach ($players as $player): 
                            $confidenceBadge = ConfidenceSystem::getConfidenceBadge($player['matches_played']);
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php if ($rank <= 3): ?>
                                    <span class="text-2xl">
                                        <?php echo $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉'); ?>
                                    </span>
                                <?php else: ?>
                                    <?php echo $rank; ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img class="h-10 w-10 rounded-full" 
                                             src="<?php echo getGravatarUrl($player['player_name']); ?>" 
                                             alt="">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <a href="/member.php?id=<?php echo $player['player_id']; ?>" 
                                               class="hover:text-orange-600">
                                                <?php echo htmlspecialchars($player['player_name']); ?>
                                            </a>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo ConfidenceSystem::formatConfidenceIndicator($player['matches_played']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-lg font-bold <?php echo RatingSystem::getRatingColorClass($player['pubg_rating']); ?>">
                                    <?php echo RatingSystem::formatRating($player['pubg_rating']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm <?php echo RatingSystem::getRatingColorClass($player['combat_score']); ?>">
                                    ⚔️ <?php echo number_format($player['combat_score'], 1); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm <?php echo RatingSystem::getRatingColorClass($player['survival_score']); ?>">
                                    🛡️ <?php echo number_format($player['survival_score'], 1); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm <?php echo RatingSystem::getRatingColorClass($player['support_score']); ?>">
                                    🤝 <?php echo number_format($player['support_score'], 1); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo number_format($player['kd_ratio'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    <?php echo $player['matches_played']; ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo ConfidenceSystem::getConfidencePercentage($player['matches_played']); ?>% conf
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    <?php echo $player['wins']; ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo number_format($player['win_rate'], 1); ?>%
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    <?php echo round($player['avg_damage']); ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                        $rank++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Stats Summary -->
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <?php
            $totalPlayers = count($players);
            $highConfidencePlayers = count(ConfidenceSystem::filterByConfidence($players, 'high'));
            $avgRating = $totalPlayers > 0 ? array_sum(array_column($players, 'pubg_rating')) / $totalPlayers : 0;
            $totalMatches = array_sum(array_column($players, 'matches_played'));
            ?>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                    Jugadores Activos
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <?php echo $totalPlayers; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                    Alta Confianza
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <?php echo $highConfidencePlayers; ?> <span class="text-sm text-gray-500">(<?php echo $totalPlayers > 0 ? round($highConfidencePlayers / $totalPlayers * 100) : 0; ?>%)</span>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                    Rating Promedio
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <?php echo number_format($avgRating, 1); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                    Total Partidas
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <?php echo formatNumber($totalMatches); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?> 