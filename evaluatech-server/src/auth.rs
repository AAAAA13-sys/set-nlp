//! Stateless HMAC-SHA256 `EVT.*` Bearer Authentication, Sliding-Window Rate Limiting,
//! One-Time CSPRNG Temporary Credentials, and Five-Role Tenant Containment (`auth.rs`).

use axum::http::{HeaderMap, StatusCode};
use base64::{Engine as _, engine::general_purpose::URL_SAFE_NO_PAD};
use hmac::{Hmac, Mac};
use rusqlite::{Connection, OptionalExtension, params};
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};
use sha2::{Digest, Sha256};
use uuid::Uuid;

use crate::db::{hmac_hex, manila_timestamps, unix_now};

type HmacSha256 = Hmac<Sha256>;

pub const TOKEN_TTL_SECONDS: i64 = 604_800; // 7 days
pub const RATE_LIMIT_MAX_ATTEMPTS: i64 = 8;
pub const RATE_LIMIT_WINDOW_SECONDS: i64 = 60;

/// Precomputed bcrypt hash used for constant-time verification when an identifier does not exist.
pub const DUMMY_BCRYPT_HASH: &str = "$2b$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy";

#[derive(Clone, Debug, Serialize, Deserialize)]
pub struct AuthUser {
    pub id: i64,
    pub institutional_id: String,
    pub name: String,
    pub email: String,
    pub role: String,
    pub role_label: String,
    pub department_id: Option<i64>,
    pub department_name: Option<String>,
    pub department_code: Option<String>,
    pub college_name: Option<String>,
    pub is_active: bool,
    pub must_change_password: bool,
}

#[derive(Clone, Debug, Serialize, Deserialize)]
pub struct TokenClaims {
    pub alg: String,
    pub iss: String,
    pub jti: String,
    pub sub: i64,
    pub inst: String,
    pub role: String,
    pub dept: Option<i64>,
    pub iat: i64,
    pub exp: i64,
}

pub fn role_label(role: &str) -> &'static str {
    match role {
        "admin" => "University Registrar / System Administrator",
        "dean" => "College Dean",
        "chair" => "Department Chairperson",
        "faculty" => "Faculty Instructor",
        _ => "Enrolled Student",
    }
}

pub fn workspace_route(role: &str) -> &'static str {
    match role {
        "admin" => "/admin",
        "dean" => "/dean",
        "chair" => "/chair",
        "faculty" => "/faculty",
        _ => "/queue",
    }
}

pub fn sha256_hex(input: &str) -> String {
    let mut hasher = Sha256::new();
    hasher.update(input.as_bytes());
    let digest = hasher.finalize();
    let mut out = String::with_capacity(64);
    for b in digest {
        out.push_str(&format!("{b:02x}"));
    }
    out
}

fn sign_evt_segment(signing_input: &str, secret: &str) -> Vec<u8> {
    let mut mac =
        HmacSha256::new_from_slice(secret.as_bytes()).expect("HMAC accepts arbitrary key length");
    mac.update(signing_input.as_bytes());
    mac.finalize().into_bytes().to_vec()
}

/// Constant-time byte comparison for cryptographic digests.
pub fn constant_time_eq(a: &[u8], b: &[u8]) -> bool {
    if a.len() != b.len() {
        return false;
    }
    let mut diff = 0_u8;
    for (&x, &y) in a.iter().zip(b.iter()) {
        diff |= x ^ y;
    }
    diff == 0
}

pub fn issue_auth_token(user: &AuthUser, secret: &str) -> String {
    let now = unix_now();
    let claims = TokenClaims {
        alg: "HS256".to_string(),
        iss: "EvaluaTech-AAAAA13-Auth".to_string(),
        jti: Uuid::new_v4().to_string(),
        sub: user.id,
        inst: user.institutional_id.clone(),
        role: user.role.clone(),
        dept: user.department_id,
        iat: now,
        exp: now + TOKEN_TTL_SECONDS,
    };
    let payload_json = serde_json::to_vec(&claims).unwrap_or_default();
    let payload_b64 = URL_SAFE_NO_PAD.encode(payload_json);
    let signing_input = format!("EVT.{payload_b64}");
    let sig_b64 = URL_SAFE_NO_PAD.encode(sign_evt_segment(&signing_input, secret));
    format!("{signing_input}.{sig_b64}")
}

pub fn issue_start_token(student_id: i64, section_id: i64, secret: &str) -> String {
    let now = unix_now();
    let mac = hmac_hex(secret, &format!("START:{student_id}:{section_id}:{now}"));
    format!("EST.{student_id}.{section_id}.{now}.{}", &mac[..32])
}

pub fn verify_start_token(token: &str, student_id: i64, section_id: i64, secret: &str) -> bool {
    let parts: Vec<&str> = token.trim().split('.').collect();
    if parts.len() != 5 || parts[0] != "EST" {
        return false;
    }
    let Ok(tok_stu) = parts[1].parse::<i64>() else {
        return false;
    };
    let Ok(tok_sec) = parts[2].parse::<i64>() else {
        return false;
    };
    let Ok(issued_at) = parts[3].parse::<i64>() else {
        return false;
    };
    if tok_stu != student_id || tok_sec != section_id || issued_at <= 0 {
        return false;
    }
    let expected = hmac_hex(
        secret,
        &format!("START:{student_id}:{section_id}:{issued_at}"),
    );
    constant_time_eq(parts[4].as_bytes(), &expected.as_bytes()[..32])
}

/// Generate a high-entropy CSPRNG one-time password (`TMP-XXXX-XXXX-XXXX`) with zero static defaults.
pub fn generate_temporary_password() -> String {
    const ALPHABET: &[u8] = b"ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";
    let mut raw = [0_u8; 12];
    let _ = getrandom::getrandom(&mut raw);
    let mut chars = String::with_capacity(18);
    chars.push_str("TMP-");
    for (idx, &b) in raw.iter().enumerate() {
        if idx > 0 && idx % 4 == 0 {
            chars.push('-');
        }
        let ch = ALPHABET[(b as usize) % ALPHABET.len()] as char;
        chars.push(ch);
    }
    chars
}

pub fn validate_password_policy(
    candidate: &str,
    institutional_id: &str,
    email: &str,
) -> Option<&'static str> {
    if candidate.chars().count() < 10 {
        return Some("Password must be at least 10 characters long.");
    }
    let lower = candidate.to_ascii_lowercase();
    if matches!(
        lower.as_str(),
        "evaluatech123" | "password123" | "password1234" | "aaaaa132026" | "changeme123"
    ) {
        return Some("Common or demonstration passwords are not permitted.");
    }
    let inst_lower = institutional_id.trim().to_ascii_lowercase();
    if !inst_lower.is_empty() && lower.contains(&inst_lower) {
        return Some("Password must not contain your institutional ID.");
    }
    let email_local = email
        .split('@')
        .next()
        .unwrap_or("")
        .trim()
        .to_ascii_lowercase();
    if email_local.len() >= 3 && lower.contains(&email_local) {
        return Some("Password must not contain your university email identifier.");
    }
    None
}

pub fn find_user_by_id(conn: &Connection, user_id: i64) -> Option<AuthUser> {
    conn.query_row(
        "SELECT u.id, u.institutional_id, u.name, u.email, u.role, u.department_id,
                u.is_active, u.must_change_password,
                d.name, d.code, d.college_name
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.id = ?1
         LIMIT 1",
        params![user_id],
        |r| {
            let role: String = r.get(4)?;
            let active: i64 = r.get(6)?;
            let must_change: i64 = r.get(7)?;
            Ok(AuthUser {
                id: r.get(0)?,
                institutional_id: r.get(1)?,
                name: r.get(2)?,
                email: r.get(3)?,
                role_label: role_label(&role).to_string(),
                role,
                department_id: r.get(5)?,
                department_name: r.get(8)?,
                department_code: r.get(9)?,
                college_name: r.get(10)?,
                is_active: active != 0,
                must_change_password: must_change != 0,
            })
        },
    )
    .optional()
    .ok()
    .flatten()
}

pub fn find_user_with_hash_by_identifier(
    conn: &Connection,
    identifier: &str,
) -> Option<(AuthUser, String)> {
    let trimmed = identifier.trim();
    conn.query_row(
        "SELECT u.id, u.institutional_id, u.name, u.email, u.role, u.department_id,
                u.is_active, u.must_change_password, u.password,
                d.name, d.code, d.college_name
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE LOWER(u.institutional_id) = LOWER(?1) OR LOWER(u.email) = LOWER(?1)
         LIMIT 1",
        params![trimmed],
        |r| {
            let role: String = r.get(4)?;
            let active: i64 = r.get(6)?;
            let must_change: i64 = r.get(7)?;
            let hash: String = r.get(8)?;
            Ok((
                AuthUser {
                    id: r.get(0)?,
                    institutional_id: r.get(1)?,
                    name: r.get(2)?,
                    email: r.get(3)?,
                    role_label: role_label(&role).to_string(),
                    role,
                    department_id: r.get(5)?,
                    department_name: r.get(9)?,
                    department_code: r.get(10)?,
                    college_name: r.get(11)?,
                    is_active: active != 0,
                    must_change_password: must_change != 0,
                },
                hash,
            ))
        },
    )
    .optional()
    .ok()
    .flatten()
}

pub fn rate_limit_bucket(identifier: &str, secret: &str) -> String {
    hmac_hex(
        secret,
        &format!("RL:{}", identifier.trim().to_ascii_lowercase()),
    )
}

pub fn check_rate_limit(conn: &Connection, bucket: &str) -> Option<i64> {
    let now = unix_now();
    let row: Option<(i64, i64, i64)> = conn
        .query_row(
            "SELECT attempts, window_started_at, blocked_until FROM auth_rate_limits WHERE bucket_key = ?1",
            params![bucket],
            |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?)),
        )
        .optional()
        .ok()
        .flatten();
    if let Some((_, _, blocked_until)) = row
        && blocked_until > now
    {
        return Some((blocked_until - now).max(1));
    }
    None
}

pub fn record_failed_attempt(conn: &Connection, bucket: &str) -> Option<i64> {
    let now = unix_now();
    let (now_str, _) = manila_timestamps(now);
    let existing: Option<(i64, i64)> = conn
        .query_row(
            "SELECT attempts, window_started_at FROM auth_rate_limits WHERE bucket_key = ?1",
            params![bucket],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )
        .optional()
        .ok()
        .flatten();

    let (attempts, window_start) = match existing {
        Some((att, ws)) if now - ws < RATE_LIMIT_WINDOW_SECONDS => (att + 1, ws),
        _ => (1, now),
    };
    let blocked_until = if attempts >= RATE_LIMIT_MAX_ATTEMPTS {
        now + RATE_LIMIT_WINDOW_SECONDS
    } else {
        0
    };

    let _ = conn.execute(
        "INSERT INTO auth_rate_limits (bucket_key, attempts, window_started_at, blocked_until, updated_at)
         VALUES (?1, ?2, ?3, ?4, ?5)
         ON CONFLICT(bucket_key) DO UPDATE SET
            attempts = excluded.attempts,
            window_started_at = excluded.window_started_at,
            blocked_until = excluded.blocked_until,
            updated_at = excluded.updated_at",
        params![bucket, attempts, window_start, blocked_until, now_str],
    );

    if blocked_until > now {
        Some(RATE_LIMIT_WINDOW_SECONDS)
    } else {
        None
    }
}

pub fn reset_rate_limit(conn: &Connection, bucket: &str) {
    let _ = conn.execute(
        "DELETE FROM auth_rate_limits WHERE bucket_key = ?1",
        params![bucket],
    );
}

pub fn revoke_bearer_token(conn: &Connection, raw_token: &str) {
    let trimmed = raw_token.trim();
    if trimmed.is_empty() {
        return;
    }
    let (now_str, _) = manila_timestamps(unix_now());
    let _ = conn.execute(
        "INSERT OR IGNORE INTO revoked_auth_tokens (token_hash, revoked_at) VALUES (?1, ?2)",
        params![sha256_hex(trimmed), now_str],
    );
}

pub fn extract_bearer_token(headers: &HeaderMap) -> Option<String> {
    let raw = headers.get("authorization")?.to_str().ok()?.trim();
    let rest = raw
        .strip_prefix("Bearer ")
        .or_else(|| raw.strip_prefix("bearer "))?;
    let trimmed = rest.trim();
    if trimmed.is_empty() {
        None
    } else {
        Some(trimmed.to_string())
    }
}

pub fn is_allowed_before_password_change(path: &str) -> bool {
    matches!(
        path,
        "/api/auth/me" | "/api/auth/logout" | "/api/auth/password" | "/api/health"
    )
}

/// Authenticate Bearer `EVT.*` token, verify revocation, active account status,
/// `must_change_password` gate, and allowed RBAC roles.
pub fn authenticate_request(
    conn: &Connection,
    headers: &HeaderMap,
    path: &str,
    secret: &str,
    allowed_roles: &[&str],
) -> Result<AuthUser, (StatusCode, Value)> {
    let Some(token) = extract_bearer_token(headers) else {
        return Err((
            StatusCode::UNAUTHORIZED,
            json!({
                "authenticated": false,
                "code": "UNAUTHENTICATED",
                "message": "Bearer authentication token is required.",
            }),
        ));
    };

    // Check revocation list
    let tok_hash = sha256_hex(&token);
    let revoked: Option<i64> = conn
        .query_row(
            "SELECT 1 FROM revoked_auth_tokens WHERE token_hash = ?1 LIMIT 1",
            params![tok_hash],
            |r| r.get(0),
        )
        .optional()
        .ok()
        .flatten();
    if revoked.is_some() {
        return Err((
            StatusCode::UNAUTHORIZED,
            json!({
                "authenticated": false,
                "code": "TOKEN_REVOKED",
                "message": "Session token has been revoked. Please sign in again.",
            }),
        ));
    }

    let parts: Vec<&str> = token.split('.').collect();
    if parts.len() != 3 || parts[0] != "EVT" {
        return Err((
            StatusCode::UNAUTHORIZED,
            json!({
                "authenticated": false,
                "code": "INVALID_TOKEN",
                "message": "Malformed institutional session token.",
            }),
        ));
    }

    let signing_input = format!("EVT.{}", parts[1]);
    let expected_sig = sign_evt_segment(&signing_input, secret);
    let Ok(provided_sig) = URL_SAFE_NO_PAD.decode(parts[2]) else {
        return Err((
            StatusCode::UNAUTHORIZED,
            json!({
                "authenticated": false,
                "code": "INVALID_TOKEN_SIGNATURE",
                "message": "Invalid session token signature encoding.",
            }),
        ));
    };
    if !constant_time_eq(&provided_sig, &expected_sig) {
        return Err((
            StatusCode::UNAUTHORIZED,
            json!({
                "authenticated": false,
                "code": "INVALID_TOKEN_SIGNATURE",
                "message": "Session token signature verification failed.",
            }),
        ));
    }

    let Ok(claims_bytes) = URL_SAFE_NO_PAD.decode(parts[1]) else {
        return Err((
            StatusCode::UNAUTHORIZED,
            json!({
                "authenticated": false,
                "code": "INVALID_TOKEN_PAYLOAD",
                "message": "Invalid session token claims.",
            }),
        ));
    };
    let Ok(claims) = serde_json::from_slice::<TokenClaims>(&claims_bytes) else {
        return Err((
            StatusCode::UNAUTHORIZED,
            json!({
                "authenticated": false,
                "code": "INVALID_TOKEN_CLAIMS",
                "message": "Malformed session token claims.",
            }),
        ));
    };

    if claims.iss != "EvaluaTech-AAAAA13-Auth" || claims.exp < unix_now() {
        return Err((
            StatusCode::UNAUTHORIZED,
            json!({
                "authenticated": false,
                "code": "TOKEN_EXPIRED",
                "message": "Institutional session token has expired.",
            }),
        ));
    }

    let Some(user) = find_user_by_id(conn, claims.sub) else {
        return Err((
            StatusCode::UNAUTHORIZED,
            json!({
                "authenticated": false,
                "code": "USER_NOT_FOUND",
                "message": "Authenticated user account no longer exists.",
            }),
        ));
    };

    if !user.is_active {
        return Err((
            StatusCode::FORBIDDEN,
            json!({
                "authenticated": false,
                "code": "ACCOUNT_INACTIVE",
                "message": "Institutional account is inactive.",
            }),
        ));
    }

    if user.must_change_password && !is_allowed_before_password_change(path) {
        return Err((
            StatusCode::FORBIDDEN,
            json!({
                "authenticated": true,
                "code": "PASSWORD_CHANGE_REQUIRED",
                "must_change_password": true,
                "message": "You must replace your one-time temporary password before accessing institutional workspaces.",
            }),
        ));
    }

    if !allowed_roles.is_empty() && !allowed_roles.contains(&user.role.as_str()) {
        return Err((
            StatusCode::FORBIDDEN,
            json!({
                "code": "INSUFFICIENT_ROLE_PERMISSIONS",
                "message": "Your institutional role is not authorized to access this endpoint.",
            }),
        ));
    }

    Ok(user)
}

/// Enforce strict Departmental (`chair`) and College (`dean`) Tenant Containment (`IDOR / BOLA`).
pub fn resolve_scoped_departments(
    conn: &Connection,
    user: &AuthUser,
    requested_dept_id: Option<i64>,
) -> Result<Vec<i64>, (StatusCode, Value)> {
    match user.role.as_str() {
        "admin" => {
            if let Some(d) = requested_dept_id {
                Ok(vec![d])
            } else {
                let mut stmt = conn
                    .prepare("SELECT id FROM departments ORDER BY id ASC")
                    .map_err(|e| {
                        (
                            StatusCode::INTERNAL_SERVER_ERROR,
                            json!({ "code": "DB_ERROR", "message": e.to_string() }),
                        )
                    })?;
                let ids: Vec<i64> = stmt
                    .query_map([], |r| r.get(0))
                    .map_err(|e| {
                        (
                            StatusCode::INTERNAL_SERVER_ERROR,
                            json!({ "code": "DB_ERROR", "message": e.to_string() }),
                        )
                    })?
                    .filter_map(Result::ok)
                    .collect();
                Ok(ids)
            }
        }
        "chair" => {
            let Some(own_dept) = user.department_id else {
                return Err((
                    StatusCode::FORBIDDEN,
                    json!({
                        "code": "MISSING_DEPARTMENT_ASSIGNMENT",
                        "message": "Department Chair account has no assigned department scope.",
                    }),
                ));
            };
            if let Some(req_dept) = requested_dept_id
                && req_dept != own_dept
            {
                return Err((
                    StatusCode::FORBIDDEN,
                    json!({
                        "code": "CROSS_DEPARTMENT_FORBIDDEN",
                        "message": "Department Chairs cannot access metrics outside their assigned department.",
                    }),
                ));
            }
            Ok(vec![own_dept])
        }
        "dean" => {
            let Some(ref college) = user.college_name else {
                return Err((
                    StatusCode::FORBIDDEN,
                    json!({
                        "code": "MISSING_COLLEGE_ASSIGNMENT",
                        "message": "College Dean account has no assigned college scope.",
                    }),
                ));
            };
            let mut stmt = conn
                .prepare("SELECT id FROM departments WHERE college_name = ?1 ORDER BY id ASC")
                .map_err(|e| {
                    (
                        StatusCode::INTERNAL_SERVER_ERROR,
                        json!({ "code": "DB_ERROR", "message": e.to_string() }),
                    )
                })?;
            let college_depts: Vec<i64> = stmt
                .query_map(params![college], |r| r.get(0))
                .map_err(|e| {
                    (
                        StatusCode::INTERNAL_SERVER_ERROR,
                        json!({ "code": "DB_ERROR", "message": e.to_string() }),
                    )
                })?
                .filter_map(Result::ok)
                .collect();

            if let Some(req_dept) = requested_dept_id {
                if !college_depts.contains(&req_dept) {
                    return Err((
                        StatusCode::FORBIDDEN,
                        json!({
                            "code": "CROSS_COLLEGE_FORBIDDEN",
                            "message": "College Deans cannot access departments outside their assigned college.",
                        }),
                    ));
                }
                Ok(vec![req_dept])
            } else {
                Ok(college_depts)
            }
        }
        _ => Err((
            StatusCode::FORBIDDEN,
            json!({
                "code": "INSUFFICIENT_ROLE_PERMISSIONS",
                "message": "Role is not permitted to query departmental governance analytics.",
            }),
        )),
    }
}
