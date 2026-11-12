<?php

declare(strict_types=1);

/**
 * EvaluaTech Production Database Initializer
 *
 * Provisions a clean, production-ready institutional database:
 * - Initializes full relational schema via DatabaseSchemaManager
 * - Establishes official School AAAAA13 academic colleges and departments
 * - Sets up active academic term and administrative evaluation window
 * - Provisions primary University Registrar Administrator account
 * - Guarantees ZERO fake student evaluations, ZERO mock clearances, and ZERO fake vault records.
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/bootstrap/app.php';

// No default: a password committed to the repository would be public. Supply it at provisioning time.
$adminPassword = (string) (getenv('EVALUATECH_ADMIN_PASSWORD') ?: '');
$policyViolation = $adminPassword === ''
    ? 'EVALUATECH_ADMIN_PASSWORD is not set.'
    : App\Services\TemporaryCredential::policyViolation($adminPassword, 'ADM-00001', 'admin@aaaaa13.edu');
if ($policyViolation !== null) {
    fwrite(STDERR, "Refusing to provision the administrator account: {$policyViolation}" . PHP_EOL
        . 'Set EVALUATECH_ADMIN_PASSWORD (at least ' . App\Services\TemporaryCredential::MIN_LENGTH
        . ' characters, letters and numbers) in the environment or .env, then re-run.' . PHP_EOL);
    exit(1);
}

$sqlitePath = $rootDir . '/storage/framework/evaluatech_aaaaa13.sqlite';
$storageDir = dirname($sqlitePath);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$pdo = new \PDO('sqlite:' . $sqlitePath);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$schema = new App\Services\DatabaseSchemaManager();
$schema->initializeSchema($pdo);

if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    fwrite(STDERR, 'Refusing to provision: this database already has user accounts. '
        . 'production-init.php only initialises an empty database.' . PHP_EOL);
    exit(1);
}

$now = date('Y-m-d H:i:s');

// 1. Official School AAAAA13 Colleges & Academic Departments
$departments = [
    [1, 'CCS', 'Department of Computer Science', 'College of Computer Studies'],
    [2, 'CAS', 'Department of General Education', 'College of Arts and Sciences'],
    [3, 'CBA', 'Department of Business Administration', 'College of Business Administration'],
    [4, 'COE', 'Department of Teacher Education', 'College of Education'],
    [5, 'CE', 'Department of Civil and Industrial Engineering', 'College of Engineering'],
    [6, 'CN', 'Department of Nursing', 'College of Nursing'],
    [7, 'CIHM', 'Department of Hospitality Management', 'College of International Hospitality Management'],
];

$deptStmt = $pdo->prepare("
    INSERT OR REPLACE INTO departments (id, code, name, college_name, created_at, updated_at)
    VALUES (:id, :code, :name, :college_name, :created_at, :updated_at)
");

foreach ($departments as [$id, $code, $name, $collegeName]) {
    $deptStmt->execute([
        ':id' => $id,
        ':code' => $code,
        ':name' => $name,
        ':college_name' => $collegeName,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

// 2. Active Academic Term (AY 2026-2027 1st Semester)
$pdo->exec("
    INSERT OR REPLACE INTO academic_terms (id, code, name, academic_year, is_active, evaluation_open, starts_on, ends_on, created_at, updated_at)
    VALUES (1, '1SEM-2026', '1st Semester', '2026-2027', 1, 1, '2026-08-15', '2026-12-20', '{$now}', '{$now}')
");

// 3. Evaluation Window Parameters (Mandatory Timing Policy: 0s dwell, Asia/Manila)
$pdo->exec("
    INSERT OR REPLACE INTO evaluation_window_configs (
        academic_term_id, status, min_dwell_seconds, straight_line_variance_threshold,
        watchlist_mean_threshold, watchlist_negative_sentiment_pct, announcement_message,
        starts_at, ends_at, timezone, last_window_action, created_at, updated_at
    ) VALUES (
        1, 'OPEN', 0, 0.0, 3.50, 35.0,
        'School AAAAA13 Student Evaluation of Teachers (SET) is currently active for 1st Semester AY 2026-2027.',
        '2026-08-15 08:00:00', '2026-12-20 23:59:59', 'Asia/Manila', 'OPEN',
        '{$now}', '{$now}'
    )
");

// 4. Primary University Registrar Administrator Account
$passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);

$userStmt = $pdo->prepare("
    INSERT OR REPLACE INTO users (id, institutional_id, name, email, password, role, department_id, is_active, created_at, updated_at)
    VALUES (1, 'ADM-00001', 'University Registrar Admin', 'admin@aaaaa13.edu', :password, 'admin', NULL, 1, :created_at, :updated_at)
");

$userStmt->execute([
    ':password' => $passwordHash,
    ':created_at' => $now,
    ':updated_at' => $now,
]);

// 5. Verify Clean State (Zero Mock Evaluations in Production)
$clearanceCount = (int) $pdo->query("SELECT COUNT(*) FROM evaluation_clearances")->fetchColumn();
$vaultCount = (int) $pdo->query("SELECT COUNT(*) FROM evaluation_vault")->fetchColumn();
$enrollmentCount = (int) $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
$sectionCount = (int) $pdo->query("SELECT COUNT(*) FROM course_sections")->fetchColumn();

echo json_encode([
    'success' => true,
    'mode' => 'PRODUCTION_INITIALIZED',
    'institution' => 'School AAAAA13',
    'departments_count' => count($departments),
    'active_term' => '1SEM-2026 (1st Semester AY 2026-2027)',
    'admin_account' => 'admin@aaaaa13.edu',
    'zero_mock_verification' => [
        'evaluation_clearances' => $clearanceCount,
        'evaluation_vault' => $vaultCount,
        'enrollments' => $enrollmentCount,
        'course_sections' => $sectionCount,
    ],
    'message' => 'EvaluaTech production database initialized cleanly with zero mock evaluations.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
