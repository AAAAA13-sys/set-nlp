<?php

declare(strict_types=1);

namespace PHPUnit\Framework {
    if (!class_exists(AssertionFailedError::class)) {
        class AssertionFailedError extends \RuntimeException {}
    }

    if (!class_exists(TestCase::class)) {
        abstract class TestCase
        {
            protected static int $assertionCount = 0;

            protected function setUp(): void {}
            protected function tearDown(): void {}

            public function runSetUp(): void
            {
                $this->setUp();
            }

            public function runTearDown(): void
            {
                $this->tearDown();
            }

            public static function getAssertionCount(): int
            {
                return self::$assertionCount;
            }

            public static function assertTrue(mixed $condition, string $message = ''): void
            {
                self::$assertionCount++;
                if ($condition !== true) {
                    throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that condition is true.');
                }
            }

            public static function assertFalse(mixed $condition, string $message = ''): void
            {
                self::$assertionCount++;
                if ($condition !== false) {
                    throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that condition is false.');
                }
            }

            public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if ($expected !== $actual) {
                    $exp = var_export($expected, true);
                    $act = var_export($actual, true);
                    throw new AssertionFailedError($message !== '' ? $message : "Failed asserting that {$act} is identical to {$exp}.");
                }
            }

            public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if ($expected != $actual) {
                    $exp = var_export($expected, true);
                    $act = var_export($actual, true);
                    throw new AssertionFailedError($message !== '' ? $message : "Failed asserting that {$act} equals {$exp}.");
                }
            }

            public static function assertEqualsWithDelta(float $expected, float $actual, float $delta, string $message = ''): void
            {
                self::$assertionCount++;
                if (abs($expected - $actual) > $delta) {
                    throw new AssertionFailedError(
                        $message !== '' ? $message : "Failed asserting that {$actual} is within {$delta} of {$expected}."
                    );
                }
            }

            public static function assertNotEmpty(mixed $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if (empty($actual)) {
                    throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that value is not empty.');
                }
            }

            public static function assertEmpty(mixed $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if (!empty($actual)) {
                    throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that value is empty.');
                }
            }

            public static function assertNull(mixed $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if ($actual !== null) {
                    throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that value is null.');
                }
            }

            public static function assertNotNull(mixed $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if ($actual === null) {
                    throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that value is not null.');
                }
            }

            public static function assertCount(int $expectedCount, iterable $haystack, string $message = ''): void
            {
                self::$assertionCount++;
                $actualCount = is_array($haystack) ? count($haystack) : iterator_count($haystack);
                if ($expectedCount !== $actualCount) {
                    throw new AssertionFailedError(
                        $message !== '' ? $message : "Failed asserting that count {$actualCount} matches expected {$expectedCount}."
                    );
                }
            }

            public static function assertContains(mixed $needle, iterable $haystack, string $message = ''): void
            {
                self::$assertionCount++;
                $items = is_array($haystack) ? $haystack : iterator_to_array($haystack);
                if (!in_array($needle, $items, true)) {
                    throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that haystack contains needle.');
                }
            }

            public static function assertNotContains(mixed $needle, iterable $haystack, string $message = ''): void
            {
                self::$assertionCount++;
                $items = is_array($haystack) ? $haystack : iterator_to_array($haystack);
                if (in_array($needle, $items, true)) {
                    throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that haystack does not contain needle.');
                }
            }

            public static function assertArrayHasKey(int|string $key, array $array, string $message = ''): void
            {
                self::$assertionCount++;
                if (!array_key_exists($key, $array)) {
                    throw new AssertionFailedError($message !== '' ? $message : "Failed asserting that array has key '{$key}'.");
                }
            }

            public static function assertArrayNotHasKey(int|string $key, array $array, string $message = ''): void
            {
                self::$assertionCount++;
                if (array_key_exists($key, $array)) {
                    throw new AssertionFailedError($message !== '' ? $message : "Failed asserting that array does not have key '{$key}'.");
                }
            }

            public static function assertGreaterThanOrEqual(float|int $expected, float|int $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if ($actual < $expected) {
                    throw new AssertionFailedError($message !== '' ? $message : "Failed asserting that {$actual} is >= {$expected}.");
                }
            }

            public static function assertLessThanOrEqual(float|int $expected, float|int $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if ($actual > $expected) {
                    throw new AssertionFailedError($message !== '' ? $message : "Failed asserting that {$actual} is <= {$expected}.");
                }
            }

            public static function assertGreaterThan(float|int $expected, float|int $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if ($actual <= $expected) {
                    throw new AssertionFailedError($message !== '' ? $message : "Failed asserting that {$actual} is > {$expected}.");
                }
            }

            public static function assertLessThan(float|int $expected, float|int $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if ($actual >= $expected) {
                    throw new AssertionFailedError($message !== '' ? $message : "Failed asserting that {$actual} is < {$expected}.");
                }
            }

            public static function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
            {
                self::$assertionCount++;
                if (!str_starts_with($string, $prefix)) {
                    throw new AssertionFailedError(
                        $message !== '' ? $message : "Failed asserting that '{$string}' starts with '{$prefix}'."
                    );
                }
            }

            public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
            {
                self::$assertionCount++;
                if (!str_contains($haystack, $needle)) {
                    throw new AssertionFailedError(
                        $message !== '' ? $message : "Failed asserting that '{$haystack}' contains '{$needle}'."
                    );
                }
            }

            public static function assertIsArray(mixed $actual, string $message = ''): void
            {
                self::$assertionCount++;
                if (!is_array($actual)) {
                    throw new AssertionFailedError(
                        $message !== '' ? $message : "Failed asserting that value is of type array."
                    );
                }
            }
        }
    }
}

namespace Tests {
    use PDO;
    use PHPUnit\Framework\TestCase as BaseTestCase;

    abstract class TestCase extends BaseTestCase
    {
        protected PDO $pdo;
        protected string $appKey = 'evaluatech-app-signing-secret-2026-key';
        protected string $vaultSalt = 'evaluatech-zero-knowledge-vault-salt-2026';

        public function runSetUp(): void
        {
            $this->setUp();
        }

        public function runTearDown(): void
        {
            $this->tearDown();
        }

        protected function setUp(): void
        {
            parent::setUp();
            date_default_timezone_set('Asia/Manila');
            $this->pdo = new PDO('sqlite::memory:');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
            $this->migrateIsolatedSchema();
            $this->seedCanonicalInstitution();
        }

        protected function migrateIsolatedSchema(): void
        {
            $this->pdo->exec("
                CREATE TABLE departments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    college_name TEXT NOT NULL DEFAULT 'College of Computer Studies'
                );

                CREATE TABLE academic_terms (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    period_label TEXT NOT NULL DEFAULT 'Final SET',
                    is_active INTEGER NOT NULL DEFAULT 1
                );

                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    institutional_id TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    role TEXT NOT NULL CHECK(role IN ('admin', 'dean', 'chair', 'faculty', 'student')),
                    department_id INTEGER NULL REFERENCES departments(id) ON DELETE SET NULL
                );

                CREATE TABLE courses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    department_id INTEGER NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
                    code TEXT NOT NULL UNIQUE,
                    title TEXT NOT NULL,
                    units INTEGER NOT NULL DEFAULT 3
                );

                CREATE TABLE course_sections (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
                    faculty_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    academic_term_id INTEGER NOT NULL REFERENCES academic_terms(id) ON DELETE CASCADE,
                    section_code TEXT NOT NULL,
                    term_grades_submitted INTEGER NOT NULL DEFAULT 0,
                    registrar_lock INTEGER NOT NULL DEFAULT 0,
                    UNIQUE(course_id, academic_term_id, section_code)
                );

                CREATE TABLE enrollments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    course_section_id INTEGER NOT NULL REFERENCES course_sections(id) ON DELETE CASCADE,
                    academic_term_id INTEGER NOT NULL REFERENCES academic_terms(id) ON DELETE CASCADE,
                    enrollment_status TEXT NOT NULL DEFAULT 'ENROLLED',
                    UNIQUE(student_id, course_section_id)
                );

                CREATE TABLE evaluation_clearances (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    course_section_id INTEGER NOT NULL REFERENCES course_sections(id) ON DELETE CASCADE,
                    academic_term_id INTEGER NOT NULL REFERENCES academic_terms(id) ON DELETE CASCADE,
                    submission_key TEXT NOT NULL UNIQUE,
                    status TEXT NOT NULL DEFAULT 'CLEARED' CHECK(status IN ('PENDING', 'CLEARED')),
                    clearance_token TEXT NOT NULL,
                    cleared_at TEXT NOT NULL,
                    created_at TEXT NULL,
                    updated_at TEXT NULL,
                    UNIQUE(student_id, course_section_id)
                );

                CREATE INDEX idx_clearances_student_section ON evaluation_clearances(student_id, course_section_id);
                CREATE INDEX idx_clearances_submission_key ON evaluation_clearances(submission_key);

                CREATE TABLE evaluation_vault (
                    id TEXT PRIMARY KEY,
                    course_section_id INTEGER NOT NULL REFERENCES course_sections(id) ON DELETE CASCADE,
                    faculty_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    academic_term_id INTEGER NOT NULL REFERENCES academic_terms(id) ON DELETE CASCADE,
                    ratings_json TEXT NOT NULL,
                    mean_score REAL NOT NULL,
                    rating_variance REAL NOT NULL,
                    is_straight_lined INTEGER NOT NULL DEFAULT 0,
                    dwell_time_seconds INTEGER NOT NULL,
                    comment_text TEXT NULL,
                    comment_hash TEXT NOT NULL,
                    sentiment_polarity REAL NOT NULL DEFAULT 0.0,
                    sentiment_valence TEXT NOT NULL DEFAULT 'Neutral' CHECK(sentiment_valence IN ('Positive', 'Neutral', 'Negative')),
                    pedagogical_tag TEXT NOT NULL DEFAULT 'Pedagogical Delivery' CHECK(pedagogical_tag IN (
                        'Pedagogical Delivery',
                        'Assessment & Grading',
                        'Classroom Management',
                        'Professional Conduct',
                        'Student Support & Mentorship'
                    )),
                    recorded_date TEXT NOT NULL,
                    professor_questions_json TEXT NULL
                );

                CREATE INDEX idx_vault_section_faculty_term ON evaluation_vault(course_section_id, faculty_id, academic_term_id);
                CREATE INDEX idx_vault_faculty_sentiment_tag ON evaluation_vault(faculty_id, sentiment_valence, pedagogical_tag);

                CREATE TABLE quality_evaluations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cohort TEXT NOT NULL CHECK(cohort IN ('Students', 'Faculty Members', 'Deans & Chairs')),
                    sus_items_json TEXT NOT NULL,
                    sus_score REAL NOT NULL,
                    functional_suitability REAL NOT NULL,
                    performance_efficiency REAL NOT NULL,
                    usability REAL NOT NULL,
                    reliability REAL NOT NULL,
                    portability REAL NOT NULL,
                    recorded_date TEXT NOT NULL,
                    created_at TEXT NULL,
                    updated_at TEXT NULL
                );

                CREATE TABLE evaluation_vault_v2_extensions (
                    vault_id TEXT PRIMARY KEY,
                    cluster_scores_json TEXT NULL,
                    professor_questions_json TEXT NULL,
                    nlp_engine TEXT NOT NULL DEFAULT 'calamanCy+VADER'
                );

                CREATE TABLE faculty_action_plans (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    plan_id TEXT NOT NULL UNIQUE,
                    faculty_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    department_id INTEGER NULL REFERENCES departments(id) ON DELETE SET NULL,
                    primary_cluster TEXT NOT NULL DEFAULT 'Pedagogical Delivery',
                    status TEXT NOT NULL DEFAULT 'PENDING_REVIEW' CHECK(status IN ('PENDING_REVIEW', 'APPROVED', 'IN_PROGRESS', 'RESOLVED')),
                    urgency_level TEXT NOT NULL DEFAULT 'MODERATE',
                    mean_score REAL NOT NULL,
                    negative_sentiment_pct REAL NOT NULL,
                    interventions_json TEXT NULL,
                    dean_notes TEXT NULL,
                    signed_off_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                    signed_off_at TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );

                CREATE INDEX idx_fap_faculty ON faculty_action_plans(faculty_id);
                CREATE INDEX idx_fap_status ON faculty_action_plans(status);

                CREATE TABLE evaluation_window_configs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    academic_term_id INTEGER NOT NULL UNIQUE REFERENCES academic_terms(id) ON DELETE CASCADE,
                    status TEXT NOT NULL DEFAULT 'OPEN' CHECK(status IN ('OPEN', 'SUSPENDED', 'CLOSED')),
                    min_dwell_seconds INTEGER NOT NULL DEFAULT 0,
                    straight_line_variance_threshold REAL NOT NULL DEFAULT 0.0,
                    watchlist_mean_threshold REAL NOT NULL DEFAULT 3.50,
                    watchlist_negative_sentiment_pct REAL NOT NULL DEFAULT 35.0,
                    announcement_message TEXT NULL,
                    starts_at TEXT NULL,
                    ends_at TEXT NULL,
                    timezone TEXT NOT NULL DEFAULT 'Asia/Manila',
                    last_window_action TEXT NOT NULL DEFAULT 'OPEN',
                    updated_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );
            ");
        }

        protected function seedCanonicalInstitution(): void
        {
            $this->pdo->exec("
                INSERT INTO departments (id, code, name, college_name) VALUES
                (1, 'CCS', 'College of Computer Studies', 'College of Computer Studies'),
                (2, 'CAS', 'College of Arts and Sciences', 'College of Arts and Sciences');

                INSERT INTO academic_terms (id, code, name, period_label, is_active) VALUES
                (1, 'FALL-2026', 'Fall', 'Final SET', 1);

                INSERT INTO evaluation_window_configs (
                    academic_term_id, status, min_dwell_seconds, straight_line_variance_threshold,
                    watchlist_mean_threshold, watchlist_negative_sentiment_pct, announcement_message,
                    starts_at, ends_at, timezone, last_window_action,
                    created_at, updated_at
                ) VALUES (
                    1, 'OPEN', 0, 0.0, 3.50, 35.0,
                    'Pamantasan ng Lungsod ng Pasig (PLP) Student Evaluation of Teachers (SET) is currently active.',
                    '2026-01-01 08:00:00', '2026-12-31 23:59:59', 'Asia/Manila', 'OPEN',
                    datetime('now'), datetime('now')
                );
            ");

            $pwd = password_hash('EvaluaTech!2026', PASSWORD_BCRYPT, ['cost' => 4]);
            $stmtUser = $this->pdo->prepare("
                INSERT INTO users (id, institutional_id, name, email, password_hash, role, department_id)
                VALUES (:id, :inst, :name, :email, :pwd, :role, :dept)
            ");

            $users = [
                ['id' => 1, 'inst' => 'REG-0001',   'name' => 'Registrar System Admin', 'email' => 'registrar@evaluatech.edu', 'role' => 'admin',   'dept' => null],
                ['id' => 2, 'inst' => 'DEAN-0100',  'name' => 'Dean V. Mendoza',        'email' => 'dean.ccs@evaluatech.edu',  'role' => 'dean',    'dept' => 1],
                ['id' => 3, 'inst' => 'CHAIR-0201', 'name' => 'Chair L. Villanueva',    'email' => 'chair.ccs@evaluatech.edu', 'role' => 'chair',   'dept' => 1],
                ['id' => 4, 'inst' => 'CHAIR-0202', 'name' => 'Chair H. Bautista',      'email' => 'chair.cas@evaluatech.edu', 'role' => 'chair',   'dept' => 2],
                ['id' => 5, 'inst' => 'FAC-1001',   'name' => 'Prof. A. Dela Cruz',     'email' => 'adelacruz@evaluatech.edu', 'role' => 'faculty', 'dept' => 1],
                ['id' => 6, 'inst' => 'FAC-1002',   'name' => 'Dr. M. Santos',          'email' => 'msantos@evaluatech.edu',   'role' => 'faculty', 'dept' => 1],
                ['id' => 7, 'inst' => 'FAC-1003',   'name' => 'Prof. E. Rivera',        'email' => 'erivera@evaluatech.edu',   'role' => 'faculty', 'dept' => 2],
                ['id' => 8, 'inst' => 'FAC-1004',   'name' => 'Dr. R. Garcia',          'email' => 'rgarcia@evaluatech.edu',   'role' => 'faculty', 'dept' => 2],
                ['id' => 9, 'inst' => 'FAC-1005',   'name' => 'Prof. K. Gomez',         'email' => 'kgomez@evaluatech.edu',    'role' => 'faculty', 'dept' => 1],
                ['id' => 10, 'inst' => 'FAC-1006',  'name' => 'Engr. J. Luna',          'email' => 'jluna@evaluatech.edu',     'role' => 'faculty', 'dept' => 2],
                ['id' => 11, 'inst' => '2024-10892','name' => 'Student 2024-10892',     'email' => '2024-10892@evaluatech.edu','role' => 'student', 'dept' => 1],
                ['id' => 12, 'inst' => '2024-10955','name' => 'Student 2024-10955',     'email' => '2024-10955@evaluatech.edu','role' => 'student', 'dept' => 1],
            ];

            foreach ($users as $u) {
                $stmtUser->execute([
                    ':id'    => $u['id'],
                    ':inst'  => $u['inst'],
                    ':name'  => $u['name'],
                    ':email' => $u['email'],
                    ':pwd'   => $pwd,
                    ':role'  => $u['role'],
                    ':dept'  => $u['dept'],
                ]);
            }

            $courses = [
                [1, 1, 'CS-401',   'Algorithms & Complexity',       5, 'CS401-A',   1, 1],
                [2, 1, 'CS-405',   'Software Engineering II',       6, 'CS405-A',   1, 0],
                [3, 2, 'ENG-102',  'Academic Technical Writing',    7, 'ENG102-A',  0, 0],
                [4, 2, 'MATH-201', 'Discrete Mathematical Logic',   8, 'MATH201-A', 0, 0],
                [5, 1, 'CS-408',   'Natural Language Processing',   9, 'CS408-A',   0, 0],
                [6, 2, 'PHYS-101', 'University Physics I',         10, 'PHYS101-A', 0, 0],
            ];

            $stmtCourse = $this->pdo->prepare("
                INSERT INTO courses (id, department_id, code, title, units)
                VALUES (:id, :dept, :code, :title, 3)
            ");
            $stmtSection = $this->pdo->prepare("
                INSERT INTO course_sections (id, course_id, faculty_id, academic_term_id, section_code, term_grades_submitted, registrar_lock)
                VALUES (:id, :course_id, :faculty_id, 1, :section_code, :grades, :lock)
            ");
            $stmtEnroll = $this->pdo->prepare("
                INSERT INTO enrollments (student_id, course_section_id, academic_term_id)
                VALUES (11, :section_id, 1)
            ");

            foreach ($courses as [$id, $dept, $code, $title, $facultyId, $secCode, $gradesSubmitted, $regLock]) {
                $stmtCourse->execute([
                    ':id'    => $id,
                    ':dept'  => $dept,
                    ':code'  => $code,
                    ':title' => $title,
                ]);
                $stmtSection->execute([
                    ':id'           => $id,
                    ':course_id'    => $id,
                    ':faculty_id'   => $facultyId,
                    ':section_code' => $secCode,
                    ':grades'       => $gradesSubmitted,
                    ':lock'         => $regLock,
                ]);
                $stmtEnroll->execute([':section_id' => $id]);
            }
        }
    }
}
