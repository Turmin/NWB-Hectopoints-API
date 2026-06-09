<?php

declare(strict_types=1);

final class Database
{
    private $connection = null;
    private $config;

    public function __construct(?string $configPath = null)
    {
        $this->config = $this->loadConfig($configPath);
    }

    public function connect(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = $this->config['dsn'] ?? $this->buildDsn($this->config);
        $username = (string)($this->config['username'] ?? $this->config['user'] ?? '');
        $password = (string)($this->config['password'] ?? '');

        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->connection->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (PDOException $exception) {
            error_log('Database connection error: ' . $exception->getMessage());
            throw new RuntimeException('Database connection failed.');
        }

        return $this->connection;
    }

    private function loadConfig(?string $configPath): array
    {
        $paths = array_values(array_filter([
            $configPath,
            dirname(__DIR__, 2) . '/database.credentials.php',
        ]));

        foreach ($paths as $path) {
            if (is_file($path)) {
                $config = require $path;
                if (!is_array($config)) {
                    throw new RuntimeException('Database configuration must return an array.');
                }

                return $config;
            }
        }

        $databaseUrl = getenv('DATABASE_URL');
        if ($databaseUrl !== false && $databaseUrl !== '') {
            return $this->parseDatabaseUrl($databaseUrl);
        }

        $envConfig = [
            'driver' => getenv('DB_DRIVER') ?: 'mysql',
            'host' => getenv('DB_HOST') ?: '',
            'port' => getenv('DB_PORT') ?: '3306',
            'db_name' => getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: '',
            'username' => getenv('DB_USER') ?: getenv('MYSQL_USER') ?: '',
            'password' => getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '',
        ];

        if ($envConfig['host'] !== '' && $envConfig['db_name'] !== '') {
            return $envConfig;
        }

        throw new RuntimeException(
            'Database configuration not found. Create database.credentials.php outside the webroot or set DB_HOST, DB_NAME, DB_USER and DB_PASSWORD.'
        );
    }

    private function buildDsn(array $config): string
    {
        $driver = (string)($config['driver'] ?? 'mysql');
        if ($driver !== 'mysql') {
            throw new RuntimeException('This application expects a MySQL database.');
        }

        $host = (string)($config['host'] ?? 'localhost');
        $port = (string)($config['port'] ?? '3306');
        $database = (string)($config['db_name'] ?? $config['database'] ?? $config['name'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Database name is missing from configuration.');
        }

        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
    }

    private function parseDatabaseUrl(string $databaseUrl): array
    {
        $parts = parse_url($databaseUrl);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('DATABASE_URL is invalid.');
        }

        $scheme = (string)($parts['scheme'] ?? 'mysql');
        if ($scheme === 'mariadb') {
            $scheme = 'mysql';
        }

        return [
            'driver' => $scheme,
            'host' => (string)$parts['host'],
            'port' => (string)($parts['port'] ?? 3306),
            'db_name' => ltrim((string)$parts['path'], '/'),
            'username' => isset($parts['user']) ? rawurldecode((string)$parts['user']) : '',
            'password' => isset($parts['pass']) ? rawurldecode((string)$parts['pass']) : '',
        ];
    }
}
