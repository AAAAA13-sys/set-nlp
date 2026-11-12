<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

/**
 * DatabaseSchemaManager
 *
 * Dedicated domain service responsible for initializing relational database
 * DDL schemas, constraints, and audit tables across both SQLite memory test
 * harnesses and persistent MySQL / SQLite production runtimes.
 */
class DatabaseSchemaManager
{
    /**
     * Initialize all required relational tables and index structures.
     */
    public function initializeSchema(PDO $db): void
    {
        $driver = (string) $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $autoIncrement = $driver === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';

        $db->exec("
            CREATE TABLE IF NOT EXISTS departments (
                id {$autoIncrement},
                code VARCHAR(20) NOT NULL UNIQUE,
                name VARCHAR(160) NOT NULL,
                college_name VARCHAR(160) NOT NULL DEFAULT 'College of Computer Studies',
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id {$autoIncrement},
                institutional_id VARCHAR(32) NOT NULL UNIQUE,
                name VARCHAR(160) NOT NULL,
                email VARCHAR(160) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(24) NOT NULL,
                department_id INTEGER NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                remember_token VARCHAR(100) NULL,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        // Additive: accounts issued a one-time Registrar password must replace it before using the system.
        try {
            $hasFlag = $driver === 'sqlite'
                ? in_array('must_change_password', $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1), true)
                : $db->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch() !== false;
            if (!$hasFlag) {
                $db->exec('ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0');
            }
        } catch (Throwable) {
            // column already present or driver without introspection
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS academic_terms (
                id {$autoIncrement},
                code VARCHAR(32) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                academic_year VARCHAR(24) NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 0,
                evaluation_open INTEGER NOT NULL DEFAULT 1,
                starts_on VARCHAR(16) NULL,
                ends_on VARCHAR(16) NULL,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS courses (
                id {$autoIncrement},
                department_id INTEGER NOT NULL,
                code VARCHAR(24) NOT NULL UNIQUE,
                title VARCHAR(180) NOT NULL,
                units INTEGER NOT NULL DEFAULT 3,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS course_sections (
                id {$autoIncrement},
                course_id INTEGER NOT NULL,
                faculty_id INTEGER NOT NULL,
                academic_term_id INTEGER NOT NULL,
                section_code VARCHAR(32) NOT NULL,
                term_grades_submitted INTEGER NOT NULL DEFAULT 0,
                registrar_lock INTEGER NOT NULL DEFAULT 0,
                enrolled_count INTEGER NOT NULL DEFAULT 0,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL,
                UNIQUE (course_id, academic_term_id, section_code)
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS enrollments (
                id {$autoIncrement},
                student_id INTEGER NOT NULL,
                course_section_id INTEGER NOT NULL,
                academic_term_id INTEGER NOT NULL,
                enrollment_status VARCHAR(24) NOT NULL DEFAULT 'ENROLLED',
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL,
                UNIQUE (student_id, course_section_id)
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS evaluation_clearances (
                id {$autoIncrement},
                student_id INTEGER NOT NULL,
                course_section_id INTEGER NOT NULL,
                academic_term_id INTEGER NOT NULL,
                submission_key VARCHAR(96) NOT NULL UNIQUE,
                status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                clearance_token VARCHAR(128) NULL UNIQUE,
                cleared_at VARCHAR(32) NULL,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL,
                UNIQUE (student_id, course_section_id)
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS evaluation_vault (
                id VARCHAR(36) PRIMARY KEY,
                course_section_id INTEGER NOT NULL,
                faculty_id INTEGER NOT NULL,
                academic_term_id INTEGER NOT NULL,
                ratings_json TEXT NOT NULL,
                mean_score NUMERIC(4, 2) NOT NULL,
                rating_variance NUMERIC(6, 4) NOT NULL,
                is_straight_lined INTEGER NOT NULL DEFAULT 0,
                dwell_time_seconds INTEGER NOT NULL,
                comment_text TEXT NULL,
                comment_hash VARCHAR(64) NOT NULL,
                sentiment_polarity NUMERIC(5, 4) NOT NULL DEFAULT 0.0000,
                sentiment_valence VARCHAR(16) NOT NULL DEFAULT 'Neutral',
                pedagogical_tag VARCHAR(64) NOT NULL DEFAULT 'Pedagogical Delivery',
                recorded_date VARCHAR(16) NOT NULL,
                professor_questions_json TEXT NULL
            )
        ");

        try {
            $cols = $db->query("PRAGMA table_info(evaluation_vault)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (is_array($cols) && !in_array('professor_questions_json', $cols, true)) {
                $db->exec("ALTER TABLE evaluation_vault ADD COLUMN professor_questions_json TEXT NULL");
            }
        } catch (Throwable) {
            // column exists or non-sqlite driver
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS quality_evaluations (
                id {$autoIncrement},
                cohort VARCHAR(32) NOT NULL,
                sus_items_json TEXT NOT NULL,
                sus_score NUMERIC(5, 2) NOT NULL,
                functional_suitability NUMERIC(4, 2) NOT NULL DEFAULT 4.78,
                performance_efficiency NUMERIC(4, 2) NOT NULL DEFAULT 4.65,
                usability NUMERIC(4, 2) NOT NULL DEFAULT 4.82,
                reliability NUMERIC(4, 2) NOT NULL DEFAULT 4.74,
                portability NUMERIC(4, 2) NOT NULL DEFAULT 4.88,
                iso_overall_mean NUMERIC(4, 2) NOT NULL DEFAULT 4.77,
                recorded_date VARCHAR(16) NOT NULL,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS faculty_action_plans (
                id {$autoIncrement},
                plan_id VARCHAR(64) NOT NULL UNIQUE,
                faculty_id INTEGER NOT NULL,
                department_id INTEGER NULL,
                primary_cluster VARCHAR(64) NOT NULL DEFAULT 'Pedagogical Delivery',
                status VARCHAR(32) NOT NULL DEFAULT 'PENDING_REVIEW',
                urgency_level VARCHAR(16) NOT NULL DEFAULT 'MODERATE',
                mean_score NUMERIC(4, 2) NOT NULL,
                negative_sentiment_pct NUMERIC(5, 2) NOT NULL,
                interventions_json TEXT NULL,
                dean_notes TEXT NULL,
                signed_off_by_user_id INTEGER NULL,
                signed_off_at VARCHAR(32) NULL,
                created_at VARCHAR(32) NOT NULL,
                updated_at VARCHAR(32) NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS evaluation_window_configs (
                id {$autoIncrement},
                academic_term_id INTEGER NOT NULL UNIQUE,
                status VARCHAR(24) NOT NULL DEFAULT 'OPEN',
                min_dwell_seconds INTEGER NOT NULL DEFAULT 0,
                straight_line_variance_threshold NUMERIC(5, 4) NOT NULL DEFAULT 0.0000,
                watchlist_mean_threshold NUMERIC(4, 2) NOT NULL DEFAULT 3.50,
                watchlist_negative_sentiment_pct NUMERIC(5, 2) NOT NULL DEFAULT 35.00,
                announcement_message TEXT NULL,
                starts_at VARCHAR(32) NULL,
                ends_at VARCHAR(32) NULL,
                timezone VARCHAR(48) NOT NULL DEFAULT 'Asia/Manila',
                last_window_action VARCHAR(32) NOT NULL DEFAULT 'OPEN',
                updated_by_user_id INTEGER NULL,
                created_at VARCHAR(32) NOT NULL,
                updated_at VARCHAR(32) NOT NULL
            )
        ");

        try {
            $winCols = $db->query("PRAGMA table_info(evaluation_window_configs)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (is_array($winCols)) {
                if (!in_array('timezone', $winCols, true)) {
                    $db->exec("ALTER TABLE evaluation_window_configs ADD COLUMN timezone VARCHAR(48) NOT NULL DEFAULT 'Asia/Manila'");
                }
                if (!in_array('last_window_action', $winCols, true)) {
                    $db->exec("ALTER TABLE evaluation_window_configs ADD COLUMN last_window_action VARCHAR(32) NOT NULL DEFAULT 'OPEN'");
                }
            }
        } catch (Throwable) {
            // column exists or non-sqlite driver
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS auth_rate_limits (
                bucket_key VARCHAR(96) PRIMARY KEY,
                attempts INTEGER NOT NULL DEFAULT 0,
                window_started_at INTEGER NOT NULL,
                blocked_until INTEGER NOT NULL DEFAULT 0,
                updated_at VARCHAR(32) NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS security_audit_logs (
                id {$autoIncrement},
                event_type VARCHAR(64) NOT NULL,
                severity VARCHAR(16) NOT NULL DEFAULT 'INFO',
                actor_role VARCHAR(24) NULL,
                resource_path VARCHAR(180) NULL,
                context_json TEXT NULL,
                created_at VARCHAR(32) NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS webhook_replays (
                nonce_hash VARCHAR(64) PRIMARY KEY,
                received_at INTEGER NOT NULL,
                created_at VARCHAR(32) NOT NULL
            )
        ");
    }
}
