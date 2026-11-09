<?php
declare(strict_types=1);

/**
 * Conexión PDO a Neon PostgreSQL. Configurar mediante variables de entorno
 * en Render. No colocar credenciales dentro del repositorio.
 */
function appEnv(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }
    return trim(trim($value), " \t\n\r\0\x0B'\"");
}

$host = appEnv('PGHOST');
$port = appEnv('PGPORT', '5432');
$dbname = appEnv('PGDATABASE');
$username = appEnv('PGUSER');
$password = appEnv('PGPASSWORD');
$sslmode = appEnv('PGSSLMODE', 'require');

$invalid = $host === '' || $dbname === '' || $username === '' || $password === ''
    || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535
    || preg_match('/\s|[;=]/', $host) === 1
    || preg_match('/[;\r\n]/', $dbname) === 1
    || preg_match('/[;\r\n]/', $username) === 1
    || !in_array($sslmode, ['require', 'verify-full', 'verify-ca'], true);

if ($invalid) {
    error_log('NubeMedia: configuración de PostgreSQL incompleta o con formato incorrecto.');
    http_response_code(500);
    exit('La aplicación no tiene configurada correctamente su conexión con la base de datos.');
}

try {
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $host, $port, $dbname, $sslmode);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('NubeMedia: error de conexión PostgreSQL: ' . $e->getMessage());
    http_response_code(500);
    exit('No fue posible conectar con la base de datos. Revisa los registros del servicio.');
}
