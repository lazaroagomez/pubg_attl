<?php
/**
 * Test script to verify PUBG Leaderboard setup
 */

echo "PUBG Leaderboard - Test Script\n";
echo "==============================\n\n";

// Test PHP version
echo "1. PHP Version: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    echo "   ✓ PHP 8.0+ detected\n";
} else {
    echo "   ✗ PHP version too old (need 8.0+)\n";
}

// Test required extensions
echo "\n2. Required Extensions:\n";
$extensions = ['pdo', 'pdo_sqlite', 'curl', 'json'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✓ $ext loaded\n";
    } else {
        echo "   ✗ $ext NOT loaded\n";
    }
}

// Test database connection
echo "\n3. Database Connection:\n";
try {
    require_once __DIR__ . '/lib/database.php';
    $db = Database::getInstance();
    echo "   ✓ Database connected successfully\n";
    
    // Count tables
    $stmt = $db->getPDO()->query("SELECT COUNT(*) as count FROM sqlite_master WHERE type='table'");
    $count = $stmt->fetch()['count'];
    echo "   ✓ Database has $count tables\n";
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
}

// Test environment variables
echo "\n4. Environment Variables:\n";
$envVars = [
    'PUBG_API_KEY' => 'API Key',
    'APP_PORT' => 'App Port',
    'CLAN_NAME' => 'Clan Name',
    'ADMIN_USERNAME' => 'Admin Username'
];

foreach ($envVars as $var => $name) {
    $value = getenv($var);
    if ($value) {
        if ($var === 'PUBG_API_KEY') {
            echo "   ✓ $name: " . substr($value, 0, 10) . "...\n";
        } else {
            echo "   ✓ $name: $value\n";
        }
    } else {
        echo "   ✗ $name: NOT SET\n";
    }
}

// Test directory permissions
echo "\n5. Directory Permissions:\n";
$dirs = ['database', 'logs'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "   ✓ $dir/ exists and is writable\n";
        } else {
            echo "   ✗ $dir/ exists but is NOT writable\n";
        }
    } else {
        echo "   ✗ $dir/ does NOT exist\n";
    }
}

// Test API connection (if API key is set)
echo "\n6. PUBG API Connection:\n";
if (getenv('PUBG_API_KEY')) {
    try {
        require_once __DIR__ . '/lib/pubg-api.php';
        $api = PubgApi::getInstance();
        $seasons = $api->getSeasons();
        if (count($seasons) > 0) {
            echo "   ✓ API connection successful\n";
            echo "   ✓ Found " . count($seasons) . " seasons\n";
        } else {
            echo "   ✗ No seasons found\n";
        }
    } catch (Exception $e) {
        echo "   ✗ API error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ⚠ Skipping API test (no API key set)\n";
}

echo "\n==============================\n";
echo "Test completed!\n";

// Summary
$errors = substr_count(ob_get_contents(), '✗');
if ($errors === 0) {
    echo "\n✅ All tests passed! The application is ready to use.\n";
} else {
    echo "\n⚠️  Found $errors errors. Please fix them before running the application.\n";
}

echo "\nTo start the application:\n";
echo "1. Make sure you have a valid .env file\n";
echo "2. Run: docker-compose up -d\n";
echo "3. Access: http://localhost:8080\n"; 