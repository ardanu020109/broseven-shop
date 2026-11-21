<?php

$envFile = __DIR__.'/../.env';
$env = [];

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);

    if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\"'");
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';
$main = $env['DB_DATABASE'] ?? '';
$test = 'broseven_shop_test';

if ($main === $test) {
    fwrite(STDERR, "DB_DATABASE di .env tidak boleh sama dengan database testing.\n");
    exit(1);
}

try {
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database {$test} siap.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Gagal membuat database tes: '.$e->getMessage()."\n");
    exit(1);
}