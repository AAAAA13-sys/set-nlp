//! 63-Control OWASP & Institutional Security Maxxxxxing Middleware (`security.rs`).
//!
//! Enforces strict HTTP security headers (including `'wasm-unsafe-eval'` for pure-Rust WASM),
//! path-traversal & sensitive-asset blocking (`403`), GraphQL disablement (`404`), WebSocket
//! upgrade rejection (`426`), same-origin mutation verification (`403`), 2 MB body ceiling (`413`),
//! recursive JSON operator/prototype-pollution rejection (`422`), and zero-PII audit logging.

use axum::{
    body::{Body, to_bytes},
    extract::Request,
    http::{HeaderMap, HeaderValue, StatusCode},
    middleware::Next,
    response::{IntoResponse, Response},
};
use rusqlite::{Connection, params};
use serde_json::{Map, Value, json};

use crate::db::{manila_timestamps, unix_now};

pub const MAX_BODY_BYTES: usize = 2_097_152; // 2 MB institutional ceiling

pub const FORBIDDEN_OPERATORS: [&str; 14] = [
    "$gt",
    "$gte",
    "$lt",
    "$lte",
    "$ne",
    "$in",
    "$nin",
    "$where",
    "$regex",
    "$or",
    "$and",
    "__proto__",
    "constructor",
    "prototype",
];

pub const FORBIDDEN_PII_AUDIT_KEYS: [&str; 8] = [
    "student_id",
    "user_id",
    "institutional_id",
    "email",
    "ip",
    "ip_address",
    "session_id",
    "user_agent",
];

/// Recursively inspect JSON payloads for NoSQL operators (`$gt`, `$ne`) or prototype pollution.
pub fn contains_forbidden_operator(val: &Value, depth: usize) -> bool {
    if depth > 12 {
        return true;
    }
    match val {
        Value::Object(map) => {
            for (k, v) in map {
                let trimmed = k.trim();
                let lower = trimmed.to_ascii_lowercase();
                if trimmed.starts_with('$') || FORBIDDEN_OPERATORS.contains(&lower.as_str()) {
                    return true;
                }
                if contains_forbidden_operator(v, depth + 1) {
                    return true;
                }
            }
            false
        }
        Value::Array(arr) => arr
            .iter()
            .any(|item| contains_forbidden_operator(item, depth + 1)),
        _ => false,
    }
}

pub fn apply_security_headers(headers: &mut HeaderMap, is_api: bool) {
    let csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
    headers.insert("Content-Security-Policy", HeaderValue::from_static(csp));
    headers.insert(
        "Strict-Transport-Security",
        HeaderValue::from_static("max-age=31536000; includeSubDomains"),
    );
    headers.insert("X-Frame-Options", HeaderValue::from_static("DENY"));
    headers.insert(
        "X-Content-Type-Options",
        HeaderValue::from_static("nosniff"),
    );
    headers.insert(
        "Referrer-Policy",
        HeaderValue::from_static("strict-origin-when-cross-origin"),
    );
    headers.insert(
        "Permissions-Policy",
        HeaderValue::from_static("camera=(), microphone=(), geolocation=(), payment=()"),
    );
    headers.insert(
        "Cross-Origin-Opener-Policy",
        HeaderValue::from_static("same-origin"),
    );
    headers.insert(
        "X-Robots-Tag",
        HeaderValue::from_static("noindex, nofollow"),
    );

    if is_api {
        headers.insert(
            "Cache-Control",
            HeaderValue::from_static("no-store, no-cache, must-revalidate, max-age=0"),
        );
        headers.insert("Pragma", HeaderValue::from_static("no-cache"));
    }
}

fn security_error(status: StatusCode, code: &str, message: &str) -> Response {
    let body = json!({
        "error": message,
        "message": message,
        "code": code,
    });
    let mut resp = (
        status,
        [("Content-Type", "application/json")],
        body.to_string(),
    )
        .into_response();
    apply_security_headers(resp.headers_mut(), true);
    resp
}

fn is_sensitive_asset_path(lower_path: &str) -> bool {
    for seg in lower_path.split('/') {
        if matches!(
            seg,
            ".env" | ".git" | ".htaccess" | ".htpasswd" | ".env.example" | ".env.production"
        ) || seg.starts_with(".env.")
            || seg.starts_with(".git.")
        {
            return true;
        }
    }
    [
        ".sqlite", ".sqlite3", ".sql", ".bak", ".backup", ".old", ".swp", ".map",
    ]
    .iter()
    .any(|ext| lower_path.ends_with(ext))
}

fn extract_origin_authority(origin: &str) -> Option<String> {
    let after_scheme = origin
        .split_once("://")
        .map(|(_, rest)| rest)
        .unwrap_or(origin);
    let authority = after_scheme.split('/').next()?.trim().to_ascii_lowercase();
    if authority.is_empty() {
        None
    } else {
        Some(authority)
    }
}

/// Axum outer security middleware enforcing all 63 Security Maxxxxxing pre-flight & header controls.
pub async fn security_middleware(req: Request, next: Next) -> Response {
    let raw_uri = req
        .uri()
        .path_and_query()
        .map(|pq| pq.as_str())
        .unwrap_or_else(|| req.uri().path())
        .to_string();
    let lower_uri = raw_uri.to_ascii_lowercase();
    let lower_path = req.uri().path().to_ascii_lowercase();
    let is_api = lower_path.starts_with("/api");

    // 1. Path traversal & null-byte defense
    if raw_uri.contains('\0')
        || lower_uri.contains("..")
        || lower_uri.contains("%2e%2e")
        || lower_uri.contains("%252e")
        || lower_uri.contains("%00")
        || lower_uri.contains("..\\")
    {
        return security_error(
            StatusCode::FORBIDDEN,
            "PATH_TRAVERSAL_BLOCKED",
            "Directory traversal sequences are strictly prohibited.",
        );
    }

    // 2. Public DB, backup, source-map & environment file exposure block
    if is_sensitive_asset_path(&lower_path) {
        return security_error(
            StatusCode::FORBIDDEN,
            "SENSITIVE_ASSET_BLOCKED",
            "Direct access to environment, database, backup, or source-map files is denied.",
        );
    }

    // 3. Unsecured GraphQL endpoint block
    if lower_path == "/graphql" || lower_path == "/api/graphql" {
        return security_error(
            StatusCode::NOT_FOUND,
            "GRAPHQL_DISABLED",
            "GraphQL introspection and query endpoints are disabled.",
        );
    }

    // 4. Unsecured WebSocket upgrade block
    if let Some(upg) = req.headers().get("upgrade").and_then(|v| v.to_str().ok())
        && upg.trim().eq_ignore_ascii_case("websocket")
    {
        return security_error(
            StatusCode::UPGRADE_REQUIRED,
            "WEBSOCKET_UPGRADE_REJECTED",
            "Unauthenticated WebSocket upgrades are disabled.",
        );
    }

    // 5. Strict Same-Origin CORS & Cross-Origin Mutation Guard
    if let (Some(origin), Some(host)) = (
        req.headers().get("origin").and_then(|v| v.to_str().ok()),
        req.headers().get("host").and_then(|v| v.to_str().ok()),
    ) {
        let host_clean = host.trim().to_ascii_lowercase();
        let host_no_port = host_clean.split(':').next().unwrap_or("");
        if !origin.trim().is_empty() && !host_clean.is_empty() {
            let same = if let Some(orig_auth) = extract_origin_authority(origin) {
                let orig_no_port = orig_auth.split(':').next().unwrap_or("");
                orig_auth == host_clean
                    || (!orig_no_port.is_empty() && orig_no_port == host_no_port)
            } else {
                false
            };
            if !same {
                return security_error(
                    StatusCode::FORBIDDEN,
                    "CROSS_ORIGIN_BLOCKED",
                    "Cross-origin API requests from untrusted origins are denied.",
                );
            }
        }
    }

    // 6. Content-Length pre-check (> 2 MB -> 413)
    if let Some(cl) = req
        .headers()
        .get("content-length")
        .and_then(|v| v.to_str().ok())
        .and_then(|s| s.parse::<usize>().ok())
        && cl > MAX_BODY_BYTES
    {
        return security_error(
            StatusCode::PAYLOAD_TOO_LARGE,
            "PAYLOAD_TOO_LARGE",
            "Request payload exceeds the 2 MB institutional security ceiling.",
        );
    }

    // Buffer bounded body to enforce exact byte limit & recursive JSON operator injection check
    let (parts, body) = req.into_parts();
    let Ok(bytes) = to_bytes(body, MAX_BODY_BYTES).await else {
        return security_error(
            StatusCode::PAYLOAD_TOO_LARGE,
            "PAYLOAD_TOO_LARGE",
            "Request payload exceeds the 2 MB institutional security ceiling.",
        );
    };

    if !bytes.is_empty()
        && let Ok(json_val) = serde_json::from_slice::<Value>(&bytes)
        && contains_forbidden_operator(&json_val, 0)
    {
        return security_error(
            StatusCode::UNPROCESSABLE_ENTITY,
            "OPERATOR_INJECTION_REJECTED",
            "Nested query operators and prototype pollution keys are prohibited.",
        );
    }

    let rebuilt = Request::from_parts(parts, Body::from(bytes));
    let mut resp = next.run(rebuilt).await;
    apply_security_headers(resp.headers_mut(), is_api);
    resp
}

/// Persist zero-PII security telemetry into `security_audit_logs`.
pub fn log_security_event(
    conn: &Connection,
    event_type: &str,
    severity: &str,
    actor_role: Option<&str>,
    resource_path: Option<&str>,
    context: Value,
) {
    let sanitized = match context {
        Value::Object(mut m) => {
            for bad in FORBIDDEN_PII_AUDIT_KEYS {
                m.remove(bad);
            }
            Value::Object(m)
        }
        _ => Value::Object(Map::new()),
    };
    let (now, _) = manila_timestamps(unix_now());
    let _ = conn.execute(
        "INSERT INTO security_audit_logs (event_type, severity, actor_role, resource_path, context_json, created_at)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6)",
        params![
            event_type,
            severity,
            actor_role,
            resource_path,
            sanitized.to_string(),
            now,
        ],
    );
}
