<?php
/**
 * Admin panel for PUBG Leaderboard
 */

session_start();

require_once __DIR__ . '/lib/database.php';
require_once __DIR__ . '/lib/pubg-api.php';
require_once __DIR__ . '/lib/utils.php';

$db = Database::getInstance();
$api = PubgApi::getInstance();

// Check authentication
$adminUsername = getenv('ADMIN_USERNAME') ?: 'admin';
$adminPassword = getenv('ADMIN_PASSWORD') ?: 'changeme123';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === $adminUsername && $password === $adminPassword) {
        $_SESSION['is_admin'] = true;
        header('Location: /admin.php');
        exit;
    } else {
        $loginError = 'Usuario o contraseña incorrectos';
    }
}

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'logout') {
    unset($_SESSION['is_admin']);
    header('Location: /');
    exit;
}

// Require authentication for the rest of the page
if (!isAdmin()) {
    $pageTitle = "Admin Login";
    require_once __DIR__ . '/templates/header.php';
    ?>
    
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
                    Panel de Administración
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                    Ingresa tus credenciales para continuar
                </p>
            </div>
            <form class="mt-8 space-y-6" method="POST">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <?php if (isset($loginError)): ?>
                <div class="rounded-md bg-red-50 dark:bg-red-900 p-4">
                    <div class="flex">
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                <?php echo htmlspecialchars($loginError); ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="username" class="sr-only">Usuario</label>
                        <input id="username" name="username" type="text" required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 text-gray-900 dark:text-white dark:bg-gray-700 rounded-t-md focus:outline-none focus:ring-orange-500 focus:border-orange-500 focus:z-10 sm:text-sm" 
                               placeholder="Usuario">
                    </div>
                    <div>
                        <label for="password" class="sr-only">Contraseña</label>
                        <input id="password" name="password" type="password" required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 text-gray-900 dark:text-white dark:bg-gray-700 rounded-b-md focus:outline-none focus:ring-orange-500 focus:border-orange-500 focus:z-10 sm:text-sm" 
                               placeholder="Contraseña">
                    </div>
                </div>
                
                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                        Ingresar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// Handle admin actions
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    switch ($_POST['action']) {
        case 'add_player':
            $playerName = sanitizeInput($_POST['player_name']);
            $platform = $_POST['platform'] ?? 'steam';
            $shard = $_POST['shard'] ?? 'steam';
            
            try {
                // Lookup player in PUBG API
                $players = $api->lookupPlayersByNames([$playerName]);
                
                if (empty($players)) {
                    throw new Exception("Jugador no encontrado en PUBG API");
                }
                
                $player = $players[0];
                $pubgId = $player['id'];
                $actualName = $player['attributes']['name'];
                
                // Add to database
                $db->addPlayer($pubgId, $actualName, $platform, $shard);
                $message = "Jugador '{$actualName}' añadido exitosamente";
                
            } catch (Exception $e) {
                $error = "Error al añadir jugador: " . $e->getMessage();
            }
            break;
            
        case 'toggle_player':
            $playerId = (int)$_POST['player_id'];
            $isActive = $_POST['is_active'] === '1' ? 0 : 1;
            
            try {
                $stmt = $db->getPDO()->prepare("UPDATE players SET is_active = ? WHERE id = ?");
                $stmt->execute([$isActive, $playerId]);
                $message = "Estado del jugador actualizado";
            } catch (Exception $e) {
                $error = "Error al actualizar jugador: " . $e->getMessage();
            }
            break;
            
        case 'delete_player':
            $playerId = (int)$_POST['player_id'];
            
            try {
                $stmt = $db->getPDO()->prepare("DELETE FROM players WHERE id = ?");
                $stmt->execute([$playerId]);
                $message = "Jugador eliminado exitosamente";
            } catch (Exception $e) {
                $error = "Error al eliminar jugador: " . $e->getMessage();
            }
            break;
            
        case 'force_update':
            try {
                // Force immediate update by touching a cache key
                $db->setCache('force_update', time(), 1);
                $message = "Actualización forzada iniciada. Los datos se actualizarán en el próximo ciclo del cron.";
            } catch (Exception $e) {
                $error = "Error al forzar actualización: " . $e->getMessage();
            }
            break;
    }
}

// Get data for display
$players = $db->getPDO()->query("SELECT * FROM players ORDER BY name")->fetchAll();

// Get API stats
$stmt = $db->getPDO()->query("
    SELECT 
        COUNT(*) as total_calls,
        COUNT(CASE WHEN status_code = 200 THEN 1 END) as successful_calls,
        COUNT(CASE WHEN created_at > datetime('now', '-1 minute') THEN 1 END) as calls_last_minute,
        AVG(response_time) as avg_response_time
    FROM api_logs
    WHERE created_at > datetime('now', '-24 hours')
");
$apiStats = $stmt->fetch();

// Get cache stats
$stmt = $db->getPDO()->query("
    SELECT COUNT(*) as total_entries,
           COUNT(CASE WHEN expires_at > datetime('now') THEN 1 END) as active_entries
    FROM cache
");
$cacheStats = $stmt->fetch();

$pageTitle = "Admin Panel";
require_once __DIR__ . '/templates/header.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="md:flex md:items-center md:justify-between mb-6">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:text-3xl sm:truncate">
                    🔧 Panel de Administración
                </h2>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="rounded-md bg-green-50 dark:bg-green-900 p-4 mb-6">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="rounded-md bg-red-50 dark:bg-red-900 p-4 mb-6">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">
                        <?php echo htmlspecialchars($error); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Total Jugadores
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        <?php echo count($players); ?>
                    </dd>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        API Calls (24h)
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        <?php echo $apiStats['total_calls']; ?>
                    </dd>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Cache Activo
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        <?php echo $cacheStats['active_entries']; ?>
                    </dd>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Calls/Min
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        <?php echo $apiStats['calls_last_minute']; ?>/10
                    </dd>
                </div>
            </div>
        </div>
        
        <!-- Add Player Form -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                Añadir Jugador
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_player">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nombre del Jugador
                        </label>
                        <input type="text" name="player_name" required
                               class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
                               placeholder="PlayerName">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Plataforma
                        </label>
                        <select name="platform"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm">
                            <?php foreach ($api->getPlatforms() as $key => $name): ?>
                            <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Región (Shard)
                        </label>
                        <select name="shard"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 sm:text-sm">
                            <?php foreach ($api->getPlatforms() as $key => $name): ?>
                            <option value="<?php echo $key; ?>"><?php echo $key; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                        Añadir Jugador
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Players List -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    Jugadores Registrados
                </h3>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Jugador
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    PUBG ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Plataforma
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Última Actualización
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($players as $player): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($player['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <code class="text-xs"><?php echo htmlspecialchars($player['pubg_id']); ?></code>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($player['platform']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($player['is_active']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo timeAgo($player['updated_at']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_player">
                                        <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                        <input type="hidden" name="is_active" value="<?php echo $player['is_active']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="text-orange-600 hover:text-orange-900 dark:text-orange-400 dark:hover:text-orange-300">
                                            <?php echo $player['is_active'] ? 'Desactivar' : 'Activar'; ?>
                                        </button>
                                    </form>
                                    <span class="text-gray-300 dark:text-gray-600 mx-2">|</span>
                                    <form method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar este jugador?');">
                                        <input type="hidden" name="action" value="delete_player">
                                        <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- System Actions -->
        <div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                Acciones del Sistema
            </h3>
            <div class="space-y-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="force_update">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Forzar Actualización de Datos
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?> 