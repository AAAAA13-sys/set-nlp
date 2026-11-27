//! School AAAAA13 EvaluaTech — Hybrid Rust Systems + Python ML Server Library (`evaluatech-server`).
//!
//! Exports the SQLite schema & seeder (`db`), 63-control OWASP middleware (`security`),
//! HMAC-SHA256 Bearer authentication & tenant guards (`auth`), warm Python `calamanCy` + VADER +
//! `scikit-learn` NLP bridge (`python_nlp`), and Axum router (`routes`).

pub mod auth;
pub mod db;
pub mod python_nlp;
pub mod routes;
pub mod security;

pub use self::routes::{AppState, build_router};

#[cfg(test)]
mod tests {
    use super::*;
    use axum::{
        body::Body,
        http::{Request, StatusCode},
    };
    use http_body_util::BodyExt;
    use serde_json::{Value, json};
    use tower::ServiceExt;

    async fn send_req(
        app: &axum::Router,
        method: &str,
        uri: &str,
        token: Option<&str>,
        body: Option<Value>,
    ) -> (StatusCode, axum::http::HeaderMap, Value) {
        let mut builder = Request::builder().method(method).uri(uri);
        if let Some(t) = token {
            builder = builder.header("Authorization", format!("Bearer {t}"));
        }
        let req_body = if let Some(b) = body {
            builder = builder.header("Content-Type", "application/json");
            Body::from(b.to_string())
        } else {
            Body::empty()
        };
        let req = builder.body(req_body).expect("valid request");
        let resp = app.clone().oneshot(req).await.expect("oneshot response");
        let status = resp.status();
        let headers = resp.headers().clone();
        let bytes = resp
            .into_body()
            .collect()
            .await
            .expect("body bytes")
            .to_bytes();
        let val = serde_json::from_slice::<Value>(&bytes)
            .unwrap_or_else(|_| json!({ "raw_len": bytes.len() }));
        (status, headers, val)
    }

    async fn login_as(app: &axum::Router, identifier: &str, password: &str) -> String {
        let (st, _, body) = send_req(
            app,
            "POST",
            "/api/auth/login",
            None,
            Some(json!({ "identifier": identifier, "password": password })),
        )
        .await;
        assert_eq!(st, StatusCode::OK, "login failed: {body}");
        body["token"].as_str().expect("token string").to_string()
    }

    #[tokio::test]
    async fn serves_embedded_wasm_bundle_and_63_security_controls() {
        let app = build_router(AppState::new_in_memory(None).expect("in-memory state"));

        // 1. Landing HTML + OWASP headers
        let (st, hdrs, _) = send_req(&app, "GET", "/", None, None).await;
        assert_eq!(st, StatusCode::OK);
        assert_eq!(hdrs.get("x-frame-options").unwrap(), "DENY");
        assert_eq!(hdrs.get("x-content-type-options").unwrap(), "nosniff");
        assert_eq!(hdrs.get("x-robots-tag").unwrap(), "noindex, nofollow");
        assert!(
            hdrs.get("content-security-policy")
                .unwrap()
                .to_str()
                .unwrap()
                .contains("'wasm-unsafe-eval'")
        );

        // 2. Compiled Pure-Rust WebAssembly binary (`application/wasm`)
        let (st_w, hdrs_w, body_w) =
            send_req(&app, "GET", "/pkg/evaluatech_web_bg.wasm", None, None).await;
        assert_eq!(st_w, StatusCode::OK);
        assert_eq!(hdrs_w.get("content-type").unwrap(), "application/wasm");
        assert!(body_w["raw_len"].as_u64().unwrap() > 100_000);

        // 3. Path traversal, sensitive assets, GraphQL, and JSON operator injection blocked
        let (st_trav, _, _) = send_req(&app, "GET", "/api/..%2f.env", None, None).await;
        assert_eq!(st_trav, StatusCode::FORBIDDEN);

        let (st_env, _, _) = send_req(&app, "GET", "/.env", None, None).await;
        assert_eq!(st_env, StatusCode::FORBIDDEN);

        let (st_map, _, _) = send_req(&app, "GET", "/pkg/app.js.map", None, None).await;
        assert_eq!(st_map, StatusCode::FORBIDDEN);

        let (st_gql, _, _) = send_req(&app, "POST", "/graphql", None, Some(json!({}))).await;
        assert_eq!(st_gql, StatusCode::NOT_FOUND);

        let (st_op, _, b_op) = send_req(
            &app,
            "POST",
            "/api/auth/login",
            None,
            Some(json!({ "identifier": { "$ne": "" }, "password": "x" })),
        )
        .await;
        assert_eq!(st_op, StatusCode::UNPROCESSABLE_ENTITY);
        assert_eq!(b_op["code"], "OPERATOR_INJECTION_REJECTED");
    }

    #[tokio::test]
    async fn enforces_dual_token_decoupling_idempotency_and_straight_lining_guard() {
        let app = build_router(AppState::new_in_memory(None).expect("in-memory state"));
        let stu_tok = login_as(&app, "2024-10892", "evaluatech123").await;
        let adm_tok = login_as(&app, "admin@aaaaa13.edu", "evaluatech123").await;

        // Queue starts at 3 of 6 completed
        let (st_q, _, q) = send_req(&app, "GET", "/api/student/queue", Some(&stu_tok), None).await;
        assert_eq!(st_q, StatusCode::OK);
        assert_eq!(q["completed_count"], 3);
        assert_eq!(q["total_count"], 6);

        // Uniform 5s without confirm_uniform -> 422 ZERO_VARIANCE_CONFIRMATION_REQUIRED
        let uniform = vec![5; 25];
        let (st_sl, _, b_sl) = send_req(
            &app,
            "POST",
            "/api/evaluation/submit",
            Some(&stu_tok),
            Some(json!({
                "course_section_id": 4,
                "submission_key": "TEST-STU10-SEC4",
                "ratings": uniform,
                "dwell_time_seconds": 0,
                "confirm_uniform": false,
                "comment_text": "Contact me at 2024-10892 or juan@aaaaa13.edu — mahusay magturo!"
            })),
        )
        .await;
        assert_eq!(st_sl, StatusCode::UNPROCESSABLE_ENTITY);
        assert_eq!(b_sl["code"], "ZERO_VARIANCE_CONFIRMATION_REQUIRED");

        // Confirm intentional uniform rating -> 201 Created + PII scrubbed + immediate 0s dwell accepted
        let (st_ok, _, b_ok) = send_req(
            &app,
            "POST",
            "/api/evaluation/submit",
            Some(&stu_tok),
            Some(json!({
                "course_section_id": 4,
                "submission_key": "TEST-STU10-SEC4",
                "ratings": uniform,
                "dwell_time_seconds": 0,
                "confirm_uniform": true,
                "comment_text": "Contact me at 2024-10892 or juan@aaaaa13.edu — mahusay magturo!"
            })),
        )
        .await;
        assert_eq!(st_ok, StatusCode::CREATED);
        assert_eq!(b_ok["is_straight_lined"], true);
        let clr_tok = b_ok["clearance_token"].as_str().unwrap().to_string();
        let preview = b_ok["sentiment"]["scrubbed_preview"].as_str().unwrap();
        assert!(!preview.contains("2024-10892"));
        assert!(!preview.contains("juan@aaaaa13.edu"));
        assert!(preview.contains("[REDACTED-ID]"));
        assert!(preview.contains("[REDACTED-EMAIL]"));
        assert_eq!(
            b_ok["sentiment"]["runtime_provenance"]["language_runtime"],
            "Python"
        );
        assert_eq!(
            b_ok["sentiment"]["runtime_provenance"]["calamancy_loaded"],
            true
        );

        // FT-02 Idempotent replay returns 200 with identical clearance_token
        let (st_rep, _, b_rep) = send_req(
            &app,
            "POST",
            "/api/evaluation/submit",
            Some(&stu_tok),
            Some(json!({
                "course_section_id": 4,
                "submission_key": "TEST-STU10-SEC4",
                "ratings": uniform,
                "dwell_time_seconds": 0,
                "confirm_uniform": true,
                "comment_text": "Retry click"
            })),
        )
        .await;
        assert_eq!(st_rep, StatusCode::OK);
        assert_eq!(b_rep["code"], "IDEMPOTENT_REPLAY");
        assert_eq!(b_rep["clearance_token"], clr_tok);

        // Public clearance verification succeeds with zero vault linkage
        let (st_ver, _, b_ver) = send_req(
            &app,
            "POST",
            "/api/clearance/verify",
            None,
            Some(json!({ "clearance_token": clr_tok })),
        )
        .await;
        assert_eq!(st_ver, StatusCode::OK);
        assert_eq!(b_ver["valid"], true);

        // Vault schema anonymity audit confirms zero student identity columns
        let (st_aud, _, b_aud) = send_req(
            &app,
            "GET",
            "/api/security/vault-audit",
            Some(&adm_tok),
            None,
        )
        .await;
        assert_eq!(st_aud, StatusCode::OK);
        assert_eq!(b_aud["decoupled"], true);
    }

    #[tokio::test]
    async fn enforces_double_blind_lock_tenant_isolation_and_temporary_credential_rotation() {
        let app = build_router(AppState::new_in_memory(None).expect("in-memory state"));
        let fac_tok = login_as(&app, "FAC-10001", "evaluatech123").await;
        let chr_tok = login_as(&app, "chair@aaaaa13.edu", "evaluatech123").await;
        let dean_tok = login_as(&app, "dean@aaaaa13.edu", "evaluatech123").await;
        let adm_tok = login_as(&app, "admin@aaaaa13.edu", "evaluatech123").await;

        // Section 1 (unlocked: 1,1) -> 200; Section 7 (locked: 0,0) -> 403 DOUBLE_BLIND_LOCKED
        let (st_u, _, _) = send_req(
            &app,
            "GET",
            "/api/faculty/sections/1/report",
            Some(&fac_tok),
            None,
        )
        .await;
        assert_eq!(st_u, StatusCode::OK);

        let (st_l, _, b_l) = send_req(
            &app,
            "GET",
            "/api/faculty/sections/7/report",
            Some(&fac_tok),
            None,
        )
        .await;
        assert_eq!(st_l, StatusCode::FORBIDDEN);
        assert_eq!(b_l["code"], "DOUBLE_BLIND_LOCKED");

        // Chair (dept 1 CCS) probing dept 2 (CAS) -> 403 CROSS_DEPARTMENT_FORBIDDEN
        let (st_c_ok, _, _) = send_req(
            &app,
            "GET",
            "/api/chair/overview?department_id=1",
            Some(&chr_tok),
            None,
        )
        .await;
        assert_eq!(st_c_ok, StatusCode::OK);

        let (st_c_bad, _, b_c_bad) = send_req(
            &app,
            "GET",
            "/api/chair/overview?department_id=2",
            Some(&chr_tok),
            None,
        )
        .await;
        assert_eq!(st_c_bad, StatusCode::FORBIDDEN);
        assert_eq!(b_c_bad["code"], "CROSS_DEPARTMENT_FORBIDDEN");

        // Dean watchlist flags Prof. K. Gomez (CS-408 < 3.50) and seals AACCUP Level III dossier
        let (st_wl, _, b_wl) =
            send_req(&app, "GET", "/api/dean/watchlist", Some(&dean_tok), None).await;
        assert_eq!(st_wl, StatusCode::OK);
        assert!(b_wl["flagged_count"].as_u64().unwrap() >= 1);

        // Zero-Permits Participation Tracking lists pending students without response linkage
        let (st_pt, _, b_pt) = send_req(
            &app,
            "GET",
            "/api/dean/participation",
            Some(&dean_tok),
            None,
        )
        .await;
        assert_eq!(st_pt, StatusCode::OK);
        assert_eq!(b_pt["zero_permits_policy"], true);
        assert!(b_pt["pending_students"].is_array());
        assert!(!b_pt.to_string().contains("comment_text"));

        let (st_dos, _, b_dos) = send_req(
            &app,
            "POST",
            "/api/dean/accreditation/compile",
            Some(&dean_tok),
            Some(json!({})),
        )
        .await;
        assert_eq!(st_dos, StatusCode::OK);
        assert!(
            b_dos["dossier_id"]
                .as_str()
                .unwrap()
                .starts_with("AAAAA13-AACCUP3-")
        );

        // Roster import issues random CSPRNG one-time password + blocks API until rotated
        let (st_imp, _, b_imp) = send_req(
            &app,
            "POST",
            "/api/admin/rosters/import",
            Some(&adm_tok),
            Some(json!({ "csv": "2024-99001,Ana Reyes,2024-99001@aaaaa13.edu,CS-401,SEC-A" })),
        )
        .await;
        assert_eq!(st_imp, StatusCode::OK);
        let tmp_pw = b_imp["issued_credentials"][0]["temporary_password"]
            .as_str()
            .unwrap()
            .to_string();
        assert!(tmp_pw.starts_with("TMP-"));

        let new_stu_tok = login_as(&app, "2024-99001", &tmp_pw).await;
        let (st_gate, _, b_gate) =
            send_req(&app, "GET", "/api/student/queue", Some(&new_stu_tok), None).await;
        assert_eq!(st_gate, StatusCode::FORBIDDEN);
        assert_eq!(b_gate["code"], "PASSWORD_CHANGE_REQUIRED");

        let (st_rot, _, _) = send_req(
            &app,
            "POST",
            "/api/auth/password",
            Some(&new_stu_tok),
            Some(json!({
                "current_password": tmp_pw,
                "new_password": "StrongInstitutionalPass#2026"
            })),
        )
        .await;
        assert_eq!(st_rot, StatusCode::OK);

        let (st_after, _, _) =
            send_req(&app, "GET", "/api/student/queue", Some(&new_stu_tok), None).await;
        assert_eq!(st_after, StatusCode::OK);
    }

    #[tokio::test]
    async fn enforces_sliding_window_login_rate_limiting_and_webhook_replay_guard() {
        let state = AppState::new_in_memory(None).expect("in-memory state");
        let secret = state.signing_secret.clone();
        let app = build_router(state);

        // 8 failed attempts trigger HTTP 429 RATE_LIMITED
        let mut last_status = StatusCode::UNAUTHORIZED;
        for _ in 0..8 {
            let (st, _, _) = send_req(
                &app,
                "POST",
                "/api/auth/login",
                None,
                Some(json!({ "identifier": "2024-10892", "password": "wrong-password" })),
            )
            .await;
            last_status = st;
        }
        assert_eq!(last_status, StatusCode::TOO_MANY_REQUESTS);

        // Signed Registrar webhook succeeds once, then rejects replay with 409
        let payload = json!({ "course_section_id": 3, "registrar_lock": true }).to_string();
        let nonce = "NONCE-2026-0001";
        let sig = db::hmac_hex(&secret, &format!("{nonce}.{payload}"));

        let build_wh = || {
            Request::builder()
                .method("POST")
                .uri("/api/webhooks/registrar-sync")
                .header("Content-Type", "application/json")
                .header("X-EvaluaTech-Nonce", nonce)
                .header("X-EvaluaTech-Signature", &sig)
                .body(Body::from(payload.clone()))
                .unwrap()
        };

        let r1 = app.clone().oneshot(build_wh()).await.unwrap();
        assert_eq!(r1.status(), StatusCode::OK);

        let r2 = app.clone().oneshot(build_wh()).await.unwrap();
        assert_eq!(r2.status(), StatusCode::CONFLICT);
    }
}
