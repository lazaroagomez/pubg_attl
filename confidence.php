<?php
/**
 * Confidence Dashboard - Shows player confidence levels
 */

session_start();

require_once __DIR__ . '/lib/database.php';
require_once __DIR__ . '/lib/confidence.php';
require_once __DIR__ . '/lib/utils.php';

$db = Database::getInstance();

// Get all players with their latest stats
$stmt = $db->getPDO()->query("
    SELECT 
        p.*,
        ps.*,
        p.name as player_name,
        p.pubg_id as player_pubg_id
    FROM players p
    LEFT JOIN (
        SELECT *
        FROM player_stats
        WHERE (player_id, last_updated) IN (
            SELECT player_id, MAX(last_updated)
            FROM player_stats
            GROUP BY player_id
        )
    ) ps ON p.id = ps.player_id
    WHERE p.is_active = 1
    ORDER BY ps.matches_played DESC, p.name ASC
");
$players = $stmt->fetchAll();

// Group players by confidence level
$groupedPlayers = ConfidenceSystem::groupByConfidence($players);

// Calculate statistics
$totalPlayers = count($players);
$stats = [
    'low' => count($groupedPlayers['low']),
    'medium' => count($groupedPlayers['medium']),
    'high' => count($groupedPlayers['high']),
    'total_matches' => array_sum(array_column($players, 'matches_played')),
    'avg_matches' => $totalPlayers > 0 ? array_sum(array_column($players, 'matches_played')) / $totalPlayers : 0,
];

// Players close to graduating to next level
$almostMedium = array_filter($groupedPlayers['low'], fn($p) => $p['matches_played'] >= 15);
$almostHigh = array_filter($groupedPlayers['medium'], fn($p) => $p['matches_played'] >= 45);

$pageTitle = "Dashboard de Confianza - " . getenv('CLAN_NAME');
require_once __DIR__ . '/templates/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:text-3xl sm:truncate">
                📊 Dashboard de Confianza del Clan
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Sistema de confianza basado en partidas jugadas. Los ratings se ajustan según el nivel de confianza.
            </p>
        </div>
        
        <!-- Confidence Overview -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5 mb-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-5 py-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <span class="text-2xl">🔴</span>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                    Confianza Baja
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <?php echo $stats['low']; ?>
                                    <span class="text-sm text-gray-500">(<?php echo $totalPlayers > 0 ? round($stats['low'] / $totalPlayers * 100) : 0; ?>%)</span>
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
                            <span class="text-2xl">🟡</span>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                    Confianza Media
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <?php echo $stats['medium']; ?>
                                    <span class="text-sm text-gray-500">(<?php echo $totalPlayers > 0 ? round($stats['medium'] / $totalPlayers * 100) : 0; ?>%)</span>
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
                            <span class="text-2xl">🟢</span>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                    Confianza Alta
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <?php echo $stats['high']; ?>
                                    <span class="text-sm text-gray-500">(<?php echo $totalPlayers > 0 ? round($stats['high'] / $totalPlayers * 100) : 0; ?>%)</span>
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
                                    <?php echo formatNumber($stats['total_matches']); ?>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                    Promedio
                                </dt>
                                <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <?php echo round($stats['avg_matches']); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Players About to Graduate -->
        <?php if (count($almostMedium) > 0 || count($almostHigh) > 0): ?>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                        Jugadores próximos a subir de nivel
                    </h3>
                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                        <?php if (count($almostMedium) > 0): ?>
                        <p>
                            <strong>Próximos a confianza media (20+ partidas):</strong>
                            <?php echo implode(', ', array_map(fn($p) => $p['player_name'] . ' (' . $p['matches_played'] . ')', $almostMedium)); ?>
                        </p>
                        <?php endif; ?>
                        <?php if (count($almostHigh) > 0): ?>
                        <p class="mt-1">
                            <strong>Próximos a confianza alta (50+ partidas):</strong>
                            <?php echo implode(', ', array_map(fn($p) => $p['player_name'] . ' (' . $p['matches_played'] . ')', $almostHigh)); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Confidence Level Explanation -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                ¿Cómo funciona el sistema de confianza?
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">🔴</span>
                        <h4 class="font-medium text-gray-900 dark:text-white">Confianza Baja (0-19 partidas)</h4>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Los ratings tienen un factor de confianza bajo. Un jugador con KD 5.0 pero solo 5 partidas 
                        tendrá ratings muy reducidos comparado con su potencial real.
                    </p>
                    <div class="mt-2">
                        <p class="text-xs text-gray-500">Ejemplo: Combat Score Base 80 × 0.1 confianza = 8 puntos finales</p>
                    </div>
                </div>
                
                <div>
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">🟡</span>
                        <h4 class="font-medium text-gray-900 dark:text-white">Confianza Media (20-49 partidas)</h4>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Los ratings son más representativos pero aún se ajustan. El factor de confianza aumenta 
                        linealmente hasta alcanzar el máximo en 50 partidas.
                    </p>
                    <div class="mt-2">
                        <p class="text-xs text-gray-500">Ejemplo: Combat Score Base 80 × 0.6 confianza = 48 puntos finales</p>
                    </div>
                </div>
                
                <div>
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">🟢</span>
                        <h4 class="font-medium text-gray-900 dark:text-white">Confianza Alta (50+ partidas)</h4>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Los ratings reflejan el rendimiento real del jugador sin penalizaciones. El factor de 
                        confianza es 1.0 (100%) y los scores no se reducen.
                    </p>
                    <div class="mt-2">
                        <p class="text-xs text-gray-500">Ejemplo: Combat Score Base 80 × 1.0 confianza = 80 puntos finales</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Players by Confidence Level -->
        <div class="space-y-6">
            <?php foreach (['high' => '🟢 Alta Confianza', 'medium' => '🟡 Confianza Media', 'low' => '🔴 Confianza Baja'] as $level => $title): ?>
            <?php if (count($groupedPlayers[$level]) > 0): ?>
            <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                        <?php echo $title; ?> (<?php echo count($groupedPlayers[$level]); ?> jugadores)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Jugador
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Partidas
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    % Confianza
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Progreso
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    PUBG Rating
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    K/D
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Para Subir
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($groupedPlayers[$level] as $player): 
                                $matchesPlayed = $player['matches_played'] ?? 0;
                                $confidencePercentage = ConfidenceSystem::getConfidencePercentage($matchesPlayed);
                                $matchesToNext = ConfidenceSystem::matchesToNextLevel($matchesPlayed);
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded-full" 
                                                 src="<?php echo getGravatarUrl($player['player_name']); ?>" 
                                                 alt="">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <a href="/member.php?id=<?php echo $player['player_id'] ?? $player['id']; ?>" 
                                                   class="hover:text-orange-600">
                                                    <?php echo htmlspecialchars($player['player_name']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo $matchesPlayed; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo $confidencePercentage; ?>%
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo ConfidenceSystem::getProgressBar($matchesPlayed); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm font-medium <?php echo RatingSystem::getRatingColorClass($player['pubg_rating'] ?? 0); ?>">
                                        <?php echo number_format($player['pubg_rating'] ?? 0, 1); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        <?php echo number_format($player['kd_ratio'] ?? 0, 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($matchesToNext): ?>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo $matchesToNext; ?> partidas
                                        </span>
                                    <?php else: ?>
                                        <span class="text-sm text-green-600 dark:text-green-400">
                                            ✓ Máximo
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?> 