<?php
require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv('.env');

$dbUrl = $_ENV['DATABASE_URL'];

if (!preg_match('/^mysql:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/([^\?]+)\??/', $dbUrl, $m)) {
    die("Could not parse DATABASE_URL\n");
}

$user = $m[1];
$pass = $m[2];
$host = $m[3];
$port = $m[4];
$db = $m[5];

function ensureColumn(PDO $pdo, string $table, string $column, string $ddl): void
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :col");
    $stmt->execute(['col' => $column]);
    $exists = (bool) $stmt->fetch();

    if (!$exists) {
        echo "Adding {$table}.{$column}...\n";
        $pdo->exec("ALTER TABLE `$table` ADD $ddl");
        echo "✓ Added {$table}.{$column}\n";
    }
}

function ensureForeignKeyUtilisateur(PDO $pdo): void
{
    $sql = "
        SELECT REFERENCED_TABLE_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'commande'
          AND COLUMN_NAME = 'utilisateur_id'
          AND CONSTRAINT_NAME = 'FK_6EEAA67DFB88E14F'
        LIMIT 1
    ";

    $target = $pdo->query($sql)->fetchColumn();

    if ($target !== 'users') {
        echo "Fixing FK commande.utilisateur_id -> users(id)...\n";
        try {
            $pdo->exec("ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DFB88E14F");
        } catch (Throwable) {
            // Ignore when missing and recreate below.
        }
        $pdo->exec("ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES users (id)");
        echo "✓ FK fixed\n";
    }
}

function reportLegacyTables(PDO $pdo): void
{
    $tables = [];
    foreach ($pdo->query("SHOW TABLES") as $row) {
        $tables[] = current($row);
    }

    $legacy = ['commandes', 'menus', 'horaires', 'user'];
    $found = array_values(array_intersect($legacy, $tables));

    if ($found) {
        echo "\nLegacy tables detected (not dropped automatically): " . implode(', ', $found) . "\n";
        echo "Run manual review before cleanup in production data.\n";
    }
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4;ssl_mode=REQUIRED",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Get existing columns in users table
    $result = $pdo->query("SHOW COLUMNS FROM users");
    $columns = array_map(fn($row) => $row['Field'], $result->fetchAll());
    
    echo "Current columns in users: " . implode(', ', $columns) . "\n";
    
    // Check if reset_token_expires_at exists and rename it
    if (in_array('reset_token_expires_at', $columns)) {
        echo "Renaming reset_token_expires_at to reset_token_expired_at...\n";
        $pdo->exec("ALTER TABLE users CHANGE reset_token_expires_at reset_token_expired_at DATETIME DEFAULT NULL");
        echo "✓ Column renamed\n";
    }
    
    // Check if reset_token_expired_at already exists
    $result = $pdo->query("SHOW COLUMNS FROM users");
    $columns = array_map(fn($row) => $row['Field'], $result->fetchAll());
    
    if (!in_array('reset_token_expired_at', $columns)) {
        echo "Adding reset_token_expired_at column...\n";
        $pdo->exec("ALTER TABLE users ADD reset_token_expired_at DATETIME DEFAULT NULL");
        echo "✓ Column added\n";
    }
    
    // Check if adresse is TEXT type
    $result = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'adresse'");
    $row = $result->fetch();
    if ($row && strpos($row['Type'], 'TEXT') === false) {
        echo "Changing adresse to LONGTEXT...\n";
        $pdo->exec("ALTER TABLE users MODIFY adresse LONGTEXT NOT NULL");
        echo "✓ Column type changed\n";
    }

    // commande table fixes
    ensureColumn($pdo, 'commande', 'motif_annulation', "motif_annulation LONGTEXT DEFAULT NULL");

    // menu table fixes
    ensureColumn($pdo, 'menu', 'image', "image VARCHAR(255) DEFAULT NULL");
    ensureColumn($pdo, 'menu', 'details', "details LONGTEXT DEFAULT NULL");
    ensureColumn($pdo, 'menu', 'entrees', "entrees LONGTEXT DEFAULT NULL");
    ensureColumn($pdo, 'menu', 'plats', "plats LONGTEXT DEFAULT NULL");
    ensureColumn($pdo, 'menu', 'desserts', "desserts LONGTEXT DEFAULT NULL");

    ensureForeignKeyUtilisateur($pdo);
    reportLegacyTables($pdo);
    
    echo "\n✓ Schema fixes complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
