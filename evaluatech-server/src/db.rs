//! Relational SQLite Persistence, Zero-Knowledge Schema & Canonical Institutional Demo Seeder.
//!
//! Enforces strict physical separation between `evaluation_clearances` (student compliance ledger)
//! and `evaluation_vault` (stripped, salted, zero-PII faculty feedback store).

use std::time::{SystemTime, UNIX_EPOCH};

use evaluatech_nlp::{compute_likert_stats, scrub_pii};
use hmac::{Hmac, Mac};
use rusqlite::{Connection, params};
use serde_json::json;
use sha2::Sha256;
use uuid::Uuid;

type HmacSha256 = Hmac<Sha256>;

pub const TESTING_FALLBACK_SECRET: &str = "evaluatech-testing-fallback-secret-2026";

/// Resolve the institutional HMAC-SHA256 signing secret.
/// Refuses to run with a hardcoded fallback when `APP_ENV=production`.
pub fn resolve_signing_secret(explicit: Option<&str>) -> Result<String, String> {
    if let Some(s) = explicit
        && !s.trim().is_empty()
        && s != "evaluatech-hmac-signing-secret-2026"
    {
        return Ok(s.to_string());
    }
    if let Ok(s) = std::env::var("EVALUATECH_SIGNING_SECRET")
        && !s.trim().is_empty()
    {
        return Ok(s);
    }
    if let Ok(s) = std::env::var("APP_KEY")
        && !s.trim().is_empty()
    {
        return Ok(s);
    }
    let app_env = std::env::var("APP_ENV")
        .unwrap_or_else(|_| "local".to_string())
        .to_ascii_lowercase();
    if cfg!(test) || app_env == "local" || app_env == "testing" {
        return Ok(TESTING_FALLBACK_SECRET.to_string());
    }
    Err("Security Configuration Exception: EVALUATECH_SIGNING_SECRET or APP_KEY is required in production environment.".to_string())
}

pub fn unix_now() -> i64 {
    SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .map(|d| d.as_secs() as i64)
        .unwrap_or(1_764_115_200)
}

/// Convert UNIX epoch seconds to `(YYYY-MM-DD HH:MM:SS, YYYY-MM-DD)` in `Asia/Manila` (`UTC+8`).
pub fn manila_timestamps(epoch_secs: i64) -> (String, String) {
    let manila_secs = epoch_secs + 8 * 3600;
    let days = manila_secs.div_euclid(86_400);
    let rem = manila_secs.rem_euclid(86_400);
    let hour = rem / 3600;
    let minute = (rem % 3600) / 60;
    let second = rem % 60;

    // Howard Hinnant civil-from-days algorithm
    let z = days + 719_468;
    let era = if z >= 0 { z } else { z - 146_096 } / 146_097;
    let doe = (z - era * 146_097) as u64;
    let yoe = (doe - doe / 1460 + doe / 36524 - doe / 146_096) / 365;
    let y = (yoe as i64) + era * 400;
    let doy = doe - (365 * yoe + yoe / 4 - yoe / 100);
    let mp = (5 * doy + 2) / 153;
    let d = doy - (153 * mp + 2) / 5 + 1;
    let m = if mp < 10 { mp + 3 } else { mp - 9 };
    let year = if m <= 2 { y + 1 } else { y };

    let date_only = format!("{year:04}-{m:02}-{d:02}");
    let full = format!("{date_only} {hour:02}:{minute:02}:{second:02}");
    (full, date_only)
}

pub fn hmac_hex(secret: &str, message: &str) -> String {
    let mut mac =
        HmacSha256::new_from_slice(secret.as_bytes()).expect("HMAC accepts arbitrary key length");
    mac.update(message.as_bytes());
    let bytes = mac.finalize().into_bytes();
    let mut out = String::with_capacity(64);
    for b in bytes {
        out.push_str(&format!("{b:02x}"));
    }
    out
}

pub fn issue_clearance_token(
    student_id: i64,
    section_id: i64,
    term_id: i64,
    submission_key: &str,
    secret: &str,
) -> String {
    let digest = hmac_hex(
        secret,
        &format!("{student_id}:{section_id}:{term_id}:{submission_key}"),
    );
    format!("CLR-{}", digest[..24].to_ascii_uppercase())
}

pub fn compute_comment_hash(comment: &str, section_id: i64, term_id: i64, secret: &str) -> String {
    hmac_hex(secret, &format!("VAULT:{section_id}:{term_id}:{comment}"))
}

/// Initialize all 15 relational tables with strict zero-PII separation on `evaluation_vault`
/// and `security_audit_logs`.
pub fn initialize_schema(conn: &Connection) -> rusqlite::Result<()> {
    conn.execute_batch(
        "
        PRAGMA foreign_keys = ON;
        PRAGMA journal_mode = WAL;

        CREATE TABLE IF NOT EXISTS departments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            college_name VARCHAR(160) NOT NULL DEFAULT 'College of Computer Studies',
            created_at VARCHAR(32) NULL,
            updated_at VARCHAR(32) NULL
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            institutional_id VARCHAR(32) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(160) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(24) NOT NULL,
            department_id INTEGER NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            must_change_password INTEGER NOT NULL DEFAULT 0,
            remember_token VARCHAR(100) NULL,
            created_at VARCHAR(32) NULL,
            updated_at VARCHAR(32) NULL
        );

        CREATE TABLE IF NOT EXISTS academic_terms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(32) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            academic_year VARCHAR(24) NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 0,
            evaluation_open INTEGER NOT NULL DEFAULT 1,
            starts_on VARCHAR(16) NULL,
            ends_on VARCHAR(16) NULL,
            created_at VARCHAR(32) NULL,
            updated_at VARCHAR(32) NULL
        );

        CREATE TABLE IF NOT EXISTS courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            department_id INTEGER NOT NULL,
            code VARCHAR(24) NOT NULL UNIQUE,
            title VARCHAR(180) NOT NULL,
            units INTEGER NOT NULL DEFAULT 3,
            created_at VARCHAR(32) NULL,
            updated_at VARCHAR(32) NULL
        );

        CREATE TABLE IF NOT EXISTS course_sections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
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
        );

        CREATE TABLE IF NOT EXISTS enrollments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            course_section_id INTEGER NOT NULL,
            academic_term_id INTEGER NOT NULL,
            enrollment_status VARCHAR(24) NOT NULL DEFAULT 'ENROLLED',
            created_at VARCHAR(32) NULL,
            updated_at VARCHAR(32) NULL,
            UNIQUE (student_id, course_section_id)
        );

        CREATE TABLE IF NOT EXISTS evaluation_clearances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
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
        );

        -- ZERO-KNOWLEDGE VAULT: Never add student_id, user_id, clearance_id, ip_address, session_id, or sub-second timestamps!
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
        );

        CREATE TABLE IF NOT EXISTS quality_evaluations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
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
        );

        CREATE TABLE IF NOT EXISTS faculty_action_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
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
        );

        CREATE TABLE IF NOT EXISTS evaluation_window_configs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
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
        );

        CREATE TABLE IF NOT EXISTS auth_rate_limits (
            bucket_key VARCHAR(96) PRIMARY KEY,
            attempts INTEGER NOT NULL DEFAULT 0,
            window_started_at INTEGER NOT NULL,
            blocked_until INTEGER NOT NULL DEFAULT 0,
            updated_at VARCHAR(32) NOT NULL
        );

        CREATE TABLE IF NOT EXISTS security_audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type VARCHAR(64) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'INFO',
            actor_role VARCHAR(24) NULL,
            resource_path VARCHAR(180) NULL,
            context_json TEXT NULL,
            created_at VARCHAR(32) NOT NULL
        );

        CREATE TABLE IF NOT EXISTS webhook_replays (
            nonce_hash VARCHAR(64) PRIMARY KEY,
            received_at INTEGER NOT NULL,
            created_at VARCHAR(32) NOT NULL
        );

        CREATE TABLE IF NOT EXISTS revoked_auth_tokens (
            token_hash VARCHAR(64) PRIMARY KEY,
            revoked_at VARCHAR(32) NOT NULL
        );
        ",
    )
}

/// Seed the canonical School AAAAA13 EvaluaTech dataset when `APP_ENV` is `local`/`testing`
/// and the database is empty.
pub fn seed_demo_data_if_empty(conn: &Connection, signing_secret: &str) -> rusqlite::Result<bool> {
    let app_env = std::env::var("APP_ENV")
        .unwrap_or_else(|_| "local".to_string())
        .to_ascii_lowercase();
    if !cfg!(test) && app_env != "local" && app_env != "testing" {
        return Ok(false);
    }

    let user_count: i64 = conn.query_row("SELECT COUNT(*) FROM users", [], |r| r.get(0))?;
    if user_count > 0 {
        return Ok(false);
    }

    let (now, today) = manila_timestamps(unix_now());
    let bcrypt_cost = if cfg!(test) { 4 } else { 10 };
    let password_hash =
        bcrypt::hash("evaluatech123", bcrypt_cost).unwrap_or_else(|_| String::new());

    conn.execute_batch(&format!(
        "
        INSERT INTO departments (id, code, name, college_name, created_at, updated_at) VALUES
            (1, 'CCS', 'Department of Computer Science', 'College of Computer Studies', '{now}', '{now}'),
            (2, 'CAS', 'Department of General Education', 'College of Arts and Sciences', '{now}', '{now}');

        INSERT INTO academic_terms (id, code, name, academic_year, is_active, evaluation_open, starts_on, ends_on, created_at, updated_at) VALUES
            (1, '1SEM-2026', '1st Semester', '2026-2027', 1, 1, '2026-08-15', '2027-12-20', '{now}', '{now}'),
            (2, '2SEM-2026', '2nd Semester', '2026-2027', 0, 0, '2027-01-15', '2027-05-30', '{now}', '{now}');

        INSERT INTO evaluation_window_configs (
            academic_term_id, status, min_dwell_seconds, straight_line_variance_threshold,
            watchlist_mean_threshold, watchlist_negative_sentiment_pct, announcement_message,
            starts_at, ends_at, timezone, last_window_action, created_at, updated_at
        ) VALUES (
            1, 'OPEN', 0, 0.0, 3.50, 35.0,
            'School AAAAA13 Student Evaluation of Teachers (SET) is currently active for 1st Semester AY 2026-2027.',
            '2026-08-15 08:00:00', '2027-12-20 23:59:59', 'Asia/Manila', 'OPEN', '{now}', '{now}'
        );
        "
    ))?;

    let users = [
        (
            1_i64,
            "ADM-00001",
            "University Registrar Admin",
            "admin@aaaaa13.edu",
            "admin",
            1_i64,
        ),
        (
            2,
            "DEAN-0001",
            "Dr. V. Mendoza",
            "dean@aaaaa13.edu",
            "dean",
            1,
        ),
        (
            3,
            "CHR-00001",
            "Dr. L. Bautista",
            "chair@aaaaa13.edu",
            "chair",
            1,
        ),
        (
            4,
            "FAC-10001",
            "Prof. A. Dela Cruz",
            "adelacruz@aaaaa13.edu",
            "faculty",
            1,
        ),
        (
            5,
            "FAC-10002",
            "Dr. M. Santos",
            "msantos@aaaaa13.edu",
            "faculty",
            1,
        ),
        (
            6,
            "FAC-10003",
            "Prof. E. Rivera",
            "erivera@aaaaa13.edu",
            "faculty",
            2,
        ),
        (
            7,
            "FAC-10004",
            "Dr. R. Garcia",
            "rgarcia@aaaaa13.edu",
            "faculty",
            2,
        ),
        (
            8,
            "FAC-10005",
            "Prof. K. Gomez",
            "kgomez@aaaaa13.edu",
            "faculty",
            1,
        ),
        (
            9,
            "FAC-10006",
            "Engr. J. Luna",
            "jluna@aaaaa13.edu",
            "faculty",
            2,
        ),
        (
            10,
            "2024-10892",
            "Juan Dela Cruz",
            "2024-10892@aaaaa13.edu",
            "student",
            1,
        ),
        (
            11,
            "2024-10955",
            "Maria Clara Reyes",
            "2024-10955@aaaaa13.edu",
            "student",
            1,
        ),
    ];

    for (id, inst_id, name, email, role, dept_id) in users {
        conn.execute(
            "INSERT INTO users (id, institutional_id, name, email, password, role, department_id, is_active, must_change_password, created_at, updated_at)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, 1, 0, ?8, ?8)",
            params![id, inst_id, name, email, password_hash, role, dept_id, now],
        )?;
    }

    let courses = [
        (
            1_i64,
            1_i64,
            "CS-401",
            "Design and Analysis of Algorithms",
            3_i64,
            4_i64,
            1_i64,
            1_i64,
            42_i64,
        ),
        (
            2,
            1,
            "CS-405",
            "Artificial Intelligence & NLP",
            3,
            5,
            1,
            1,
            38,
        ),
        (
            3,
            2,
            "ENG-102",
            "Academic & Technical Communication",
            3,
            6,
            1,
            0,
            45,
        ),
        (
            4,
            2,
            "MATH-201",
            "Discrete Mathematics & Probability",
            3,
            7,
            0,
            0,
            40,
        ),
        (
            5,
            1,
            "CS-408",
            "Software Engineering & Quality Assurance",
            3,
            8,
            0,
            0,
            36,
        ),
        (
            6,
            2,
            "PHYS-101",
            "University Physics for Computing",
            3,
            9,
            0,
            0,
            44,
        ),
    ];

    for (cid, dept_id, code, title, units, fac_id, grades_sub, reg_lock, enrolled) in courses {
        conn.execute(
            "INSERT INTO courses (id, department_id, code, title, units, created_at, updated_at)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?6)",
            params![cid, dept_id, code, title, units, now],
        )?;
        conn.execute(
            "INSERT INTO course_sections (id, course_id, faculty_id, academic_term_id, section_code, term_grades_submitted, registrar_lock, enrolled_count, created_at, updated_at)
             VALUES (?1, ?1, ?2, 1, 'SEC-A', ?3, ?4, ?5, ?6, ?6)",
            params![cid, fac_id, grades_sub, reg_lock, enrolled, now],
        )?;
        conn.execute(
            "INSERT INTO enrollments (student_id, course_section_id, academic_term_id, enrollment_status, created_at, updated_at)
             VALUES (10, ?1, 1, 'ENROLLED', ?2, ?2)",
            params![cid, now],
        )?;
        conn.execute(
            "INSERT INTO enrollments (student_id, course_section_id, academic_term_id, enrollment_status, created_at, updated_at)
             VALUES (11, ?1, 1, 'ENROLLED', ?2, ?2)",
            params![cid, now],
        )?;
    }

    // Section 7: CS-401 SEC-B for Prof. A. Dela Cruz (shows both unlocked & locked sections in Faculty Portal)
    conn.execute(
        "INSERT INTO course_sections (id, course_id, faculty_id, academic_term_id, section_code, term_grades_submitted, registrar_lock, enrolled_count, created_at, updated_at)
         VALUES (7, 1, 4, 1, 'SEC-B', 0, 0, 39, ?1, ?1)",
        params![now],
    )?;

    type SeedEval<'a> = (
        i64,
        i64,
        i64,
        &'a str,
        [i32; 25],
        i64,
        &'a str,
        f64,
        &'a str,
        &'a str,
    );
    let seed_evals: &[SeedEval<'_>] = &[
        // Student 10 (2024-10892): 3 of 6 completed
        (
            10,
            1,
            4,
            "SEED-2024-10892-CS401",
            [
                5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5,
            ],
            185,
            "Explains complex algorithms very clearly and provides great practical code examples.",
            0.78,
            "Positive",
            "Pedagogical Delivery",
        ),
        (
            10,
            2,
            5,
            "SEED-2024-10892-CS405",
            [
                5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5,
            ],
            210,
            "Very organized lectures and helpful guidance on NLP lab exercises.",
            0.69,
            "Positive",
            "Pedagogical Delivery",
        ),
        (
            10,
            3,
            6,
            "SEED-2024-10892-ENG102",
            [
                4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4,
            ],
            160,
            "Respectful and supportive classroom atmosphere with clear writing rubrics.",
            0.74,
            "Positive",
            "Professional Conduct",
        ),
        // Student 11 (2024-10955): 6 of 6 completed
        (
            11,
            1,
            4,
            "SEED-2024-10955-CS401",
            [
                5, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 5, 4, 5, 5,
            ],
            195,
            "Excellent mastery of algorithmic complexity and fair problem set grading.",
            0.76,
            "Positive",
            "Assessment & Grading",
        ),
        (
            11,
            2,
            5,
            "SEED-2024-10955-CS405",
            [
                4, 5, 4, 5, 4, 5, 4, 5, 4, 4, 5, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 5, 4,
            ],
            178,
            "Great hands-on NLP laboratory sessions and clear project expectations.",
            0.71,
            "Positive",
            "Pedagogical Delivery",
        ),
        (
            11,
            3,
            6,
            "SEED-2024-10955-ENG102",
            [
                4, 4, 5, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4,
            ],
            152,
            "Helpful feedback on technical documentation structure and oral defense.",
            0.62,
            "Positive",
            "Assessment & Grading",
        ),
        (
            11,
            4,
            7,
            "SEED-2024-10955-MATH201",
            [
                4, 4, 4, 3, 4, 4, 4, 3, 4, 4, 4, 3, 4, 4, 4, 3, 4, 4, 4, 3, 4, 4, 4, 3, 4,
            ],
            168,
            "Good coverage of discrete probability proofs, though pacing on combinatorics can improve.",
            0.28,
            "Positive",
            "Pedagogical Delivery",
        ),
        (
            11,
            5,
            8,
            "SEED-2024-10955-CS408",
            [
                3, 3, 3, 4, 3, 3, 3, 3, 4, 3, 3, 3, 3, 3, 4, 3, 3, 3, 3, 3, 3, 3, 4, 3, 3,
            ],
            204,
            "Takes over a month to return sprint grading rubrics and arrives late to morning lectures.",
            -0.58,
            "Negative",
            "Assessment & Grading",
        ),
        (
            11,
            6,
            9,
            "SEED-2024-10955-PHYS101",
            [
                4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4,
            ],
            172,
            "Clear physics laboratory demonstrations and fair problem-solving exams.",
            0.66,
            "Positive",
            "Pedagogical Delivery",
        ),
    ];

    for (stu_id, sec_id, fac_id, sub_key, ratings, dwell, comment, pol, val, tag) in seed_evals {
        let stats = compute_likert_stats(ratings).expect("valid seed ratings");
        let clean = scrub_pii(comment);
        let tok = issue_clearance_token(*stu_id, *sec_id, 1, sub_key, signing_secret);
        conn.execute(
            "INSERT INTO evaluation_clearances (student_id, course_section_id, academic_term_id, submission_key, status, clearance_token, cleared_at, created_at, updated_at)
             VALUES (?1, ?2, 1, ?3, 'CLEARED', ?4, ?5, ?5, ?5)",
            params![stu_id, sec_id, sub_key, tok, now],
        )?;
        conn.execute(
            "INSERT INTO evaluation_vault (id, course_section_id, faculty_id, academic_term_id, ratings_json, mean_score, rating_variance, is_straight_lined, dwell_time_seconds, comment_text, comment_hash, sentiment_polarity, sentiment_valence, pedagogical_tag, recorded_date, professor_questions_json)
             VALUES (?1, ?2, ?3, 1, ?4, ?5, ?6, 0, ?7, ?8, ?9, ?10, ?11, ?12, ?13, NULL)",
            params![
                Uuid::new_v4().to_string(),
                sec_id,
                fac_id,
                json!(ratings).to_string(),
                stats.mean,
                stats.variance,
                dwell,
                clean,
                compute_comment_hash(&clean, *sec_id, 1, signing_secret),
                pol,
                val,
                tag,
                today,
            ],
        )?;
    }

    // Additional anonymized CS-408 watchlist vault entries for Prof. K. Gomez (FAC-10005)
    let extra_watchlist = [
        (
            "00000000-0000-4000-8000-000000000005",
            5_i64,
            8_i64,
            3.10_f64,
            0.0900_f64,
            "Takes over a month to return quiz scores and provides no helpful feedback.",
            -0.6200_f64,
            "Negative",
            "Assessment & Grading",
        ),
        (
            "00000000-0000-4000-8000-000000000006",
            5,
            8,
            3.10,
            0.0900,
            "Arrives to morning lectures 15 minutes late consistently without prior notice.",
            -0.4500,
            "Negative",
            "Classroom Management",
        ),
    ];
    for (vid, sec_id, fac_id, mean, var, text, pol, val, tag) in extra_watchlist {
        conn.execute(
            "INSERT OR IGNORE INTO evaluation_vault (id, course_section_id, faculty_id, academic_term_id, ratings_json, mean_score, rating_variance, is_straight_lined, dwell_time_seconds, comment_text, comment_hash, sentiment_polarity, sentiment_valence, pedagogical_tag, recorded_date)
             VALUES (?1, ?2, ?3, 1, '[3,3,3,3,4,3,3,3,3,3,3,3,3,3,4,3,3,3,3,3]', ?4, ?5, 0, 195, ?6, ?7, ?8, ?9, ?10, ?11)",
            params![
                vid,
                sec_id,
                fac_id,
                mean,
                var,
                text,
                compute_comment_hash(text, sec_id, 1, signing_secret),
                pol,
                val,
                tag,
                today,
            ],
        )?;
    }

    // Cohort clearances for realistic institutional completion heatmaps (skip in #[cfg(test)] if desired? No, fast SQL inserts take <3ms!)
    let target_clearances = [
        (1_i64, 39_i64),
        (2, 36),
        (3, 38),
        (4, 30),
        (5, 34),
        (6, 37),
        (7, 35),
    ];
    for (sec_id, target_count) in target_clearances {
        let existing: i64 = conn.query_row(
            "SELECT COUNT(*) FROM evaluation_clearances WHERE course_section_id = ?1 AND status = 'CLEARED'",
            params![sec_id],
            |r| r.get(0),
        )?;
        let sec_dept = if matches!(sec_id, 3 | 4 | 6) {
            2_i64
        } else {
            1_i64
        };
        for i in (existing + 1)..=target_count {
            let stu_id = 1000 + sec_id * 100 + i;
            let sub_key = format!("SEED-SEC{sec_id}-STU{stu_id}");
            let digest = hmac_hex(signing_secret, &sub_key);
            let tok = format!("EVT.CLR.SEC{sec_id}.STU{stu_id}.{}", &digest[..32]);
            conn.execute(
                "INSERT OR IGNORE INTO users (id, institutional_id, name, email, password, role, department_id, is_active, must_change_password, created_at, updated_at)
                 VALUES (?1, ?2, ?3, ?4, ?5, 'student', ?6, 1, 0, ?7, ?7)",
                params![
                    stu_id,
                    format!("STU-{stu_id}"),
                    format!("Cohort Student {stu_id}"),
                    format!("stu{stu_id}@aaaaa13.edu"),
                    password_hash,
                    sec_dept,
                    now,
                ],
            )?;
            conn.execute(
                "INSERT OR IGNORE INTO enrollments (student_id, course_section_id, academic_term_id, enrollment_status, created_at, updated_at)
                 VALUES (?1, ?2, 1, 'ENROLLED', ?3, ?3)",
                params![stu_id, sec_id, now],
            )?;
            conn.execute(
                "INSERT OR IGNORE INTO evaluation_clearances (student_id, course_section_id, academic_term_id, submission_key, status, clearance_token, cleared_at, created_at, updated_at)
                 VALUES (?1, ?2, 1, ?3, 'CLEARED', ?4, ?5, ?5, ?5)",
                params![stu_id, sec_id, sub_key, tok, now],
            )?;
        }
    }

    Ok(true)
}
