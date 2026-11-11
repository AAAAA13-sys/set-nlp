<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);
require_once $rootDir . '/bootstrap/app.php';
require_once $rootDir . '/database/seeders/EvaluaTechDemoSeeder.php';

$sqlitePath = $rootDir . '/storage/framework/evaluatech_aaaaa13.sqlite';
$pdo = new \PDO('sqlite:' . $sqlitePath);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$schema = new App\Services\DatabaseSchemaManager();
$schema->initializeSchema($pdo);

$seeder = new Database\Seeders\EvaluaTechDemoSeeder($pdo);
$result = $seeder->seed();

echo json_encode([
    'success' => true,
    'message' => 'EvaluaTech demo dataset reseeded successfully',
    'result' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
