<?php
$config = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'db' => 'docentes1_db',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
];

$localConfigFile = __DIR__ . '/db.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

$host = getenv('DOCENTES_DB_HOST') ?: $config['host'];
$port = (int)(getenv('DOCENTES_DB_PORT') ?: $config['port']);
$db = getenv('DOCENTES_DB_NAME') ?: $config['db'];
$user = getenv('DOCENTES_DB_USER') ?: $config['user'];
$pass = getenv('DOCENTES_DB_PASSWORD');
$pass = $pass === false ? $config['pass'] : $pass;
$charset = $config['charset'];

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo conectar con la base de datos. Revisa la configuración local.');
}
