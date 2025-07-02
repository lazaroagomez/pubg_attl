<?php
/**
 * Header template for PUBG Leaderboard
 */
require_once __DIR__ . '/../lib/utils.php';

$currentPage = getCurrentPage();
$darkMode = isDarkMode();
$clanName = getenv('CLAN_NAME') ?: 'PUBG Clan';
$appName = getenv('APP_NAME') ?: 'PUBG Leaderboard';
?>
<!DOCTYPE html>
<html lang="es" class="<?php echo $darkMode ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? $appName); ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Alpine.js for reactivity -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Custom styles -->
    <style>
        [x-cloak] { display: none !important; }
        .gradient-text {
            background: linear-gradient(to right, #f59e0b, #ef4444);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hover-scale {
            transition: transform 0.2s;
        }
        .hover-scale:hover {
            transform: scale(1.05);
        }
    </style>
    
    <!-- Dark mode script -->
    <script>
        // Check and apply dark mode preference
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        
        function toggleDarkMode() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
                document.cookie = "darkMode=false;path=/;max-age=31536000";
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
                document.cookie = "darkMode=true;path=/;max-age=31536000";
            }
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-800 shadow-lg" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <!-- Logo -->
                    <div class="flex-shrink-0 flex items-center">
                        <a href="/" class="text-xl font-bold gradient-text">
                            🎮 <?php echo htmlspecialchars($clanName); ?>
                        </a>
                    </div>
                    
                    <!-- Desktop Navigation -->
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="/" 
                           class="<?php echo $currentPage === 'index' ? 'border-orange-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Leaderboard
                        </a>
                        <a href="/compare.php" 
                           class="<?php echo $currentPage === 'compare' ? 'border-orange-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Comparar
                        </a>
                        <a href="/trends.php" 
                           class="<?php echo $currentPage === 'trends' ? 'border-orange-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Tendencias
                        </a>
                        <a href="/weapons.php" 
                           class="<?php echo $currentPage === 'weapons' ? 'border-orange-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Armas
                        </a>
                        <a href="/confidence.php" 
                           class="<?php echo $currentPage === 'confidence' ? 'border-orange-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Confianza
                        </a>
                        <?php if (isAdmin()): ?>
                        <a href="/admin.php" 
                           class="<?php echo $currentPage === 'admin' ? 'border-orange-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Admin
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right side buttons -->
                <div class="hidden sm:ml-6 sm:flex sm:items-center space-x-4">
                    <!-- Dark mode toggle -->
                    <button onclick="toggleDarkMode()" 
                            class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white">
                        <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                        </svg>
                        <svg class="w-5 h-5 block dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                    </button>
                    
                    <?php if (!isAdmin()): ?>
                    <!-- Admin login button -->
                    <a href="/admin.php" 
                       class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Admin
                    </a>
                    <?php else: ?>
                    <!-- Logout button -->
                    <form method="POST" action="/admin.php" class="inline">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" 
                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Logout
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                
                <!-- Mobile menu button -->
                <div class="-mr-2 flex items-center sm:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" 
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <svg class="h-6 w-6" x-show="!mobileMenuOpen" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg class="h-6 w-6" x-show="mobileMenuOpen" x-cloak fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div class="sm:hidden" x-show="mobileMenuOpen" x-cloak>
            <div class="pt-2 pb-3 space-y-1">
                <a href="/" 
                   class="<?php echo $currentPage === 'index' ? 'bg-orange-50 border-orange-500 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Leaderboard
                </a>
                <a href="/compare.php" 
                   class="<?php echo $currentPage === 'compare' ? 'bg-orange-50 border-orange-500 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Comparar
                </a>
                <a href="/trends.php" 
                   class="<?php echo $currentPage === 'trends' ? 'bg-orange-50 border-orange-500 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Tendencias
                </a>
                <a href="/weapons.php" 
                   class="<?php echo $currentPage === 'weapons' ? 'bg-orange-50 border-orange-500 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Armas
                </a>
                <a href="/confidence.php" 
                   class="<?php echo $currentPage === 'confidence' ? 'bg-orange-50 border-orange-500 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Confianza
                </a>
                <?php if (isAdmin()): ?>
                <a href="/admin.php" 
                   class="<?php echo $currentPage === 'admin' ? 'bg-orange-50 border-orange-500 text-orange-700 dark:bg-orange-900 dark:text-orange-200' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'; ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                    Admin
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="min-h-screen"> 