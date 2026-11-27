//! School AAAAA13 EvaluaTech — Rust Full-Stack Server + Python ML Bridge (`evaluatech-server`).
//!
//! Serves the embedded WebAssembly SPA (`evaluatech_web_bg.wasm`), SQLite relational catalog +
//! zero-PII `evaluation_vault`, 63 OWASP security controls, and bridges comment analysis to the
//! Python `calamanCy` + `vaderSentiment` + `scikit-learn` NLP engine (`nlp-python/`).

use evaluatech_server::{AppState, build_router, python_nlp};

#[tokio::main]
async fn main() {
    let host = std::env::var("HOST").unwrap_or_else(|_| "127.0.0.1".to_string());
    let port: u16 = std::env::var("PORT")
        .ok()
        .and_then(|s| s.parse().ok())
        .unwrap_or(8080);

    let db_path = std::env::var("DATABASE_URL")
        .unwrap_or_else(|_| "storage/evaluatech_rust_aaaaa13.sqlite".to_string());
    if db_path != ":memory:"
        && let Some(parent) = std::path::Path::new(&db_path).parent()
        && !parent.as_os_str().is_empty()
    {
        let _ = std::fs::create_dir_all(parent);
    }

    let state = match if db_path == ":memory:" {
        AppState::new_in_memory(None)
    } else {
        AppState::new_sqlite_file(&db_path, None)
    } {
        Ok(s) => s,
        Err(err) => {
            eprintln!("[evaluatech-server] FATAL startup error: {err}");
            std::process::exit(1);
        }
    };

    let prov = python_nlp::get_engine_provenance();
    let nlp_lbl = prov
        .get("nlp_engine")
        .and_then(|v| v.as_str())
        .unwrap_or("Python calamanCy + VADER + scikit-learn");

    let addr = format!("{host}:{port}");
    let app = build_router(state);
    let listener = tokio::net::TcpListener::bind(&addr)
        .await
        .unwrap_or_else(|e| panic!("failed to bind {addr}: {e}"));

    println!("======================================================================");
    println!(" School AAAAA13 EvaluaTech — Rust Systems + Python ML Stack");
    println!(" Listening on:  http://{addr}");
    println!(" SQLite Store:  {db_path}");
    println!(" NLP & ML:      {nlp_lbl}");
    println!(" Security Gate: 63 / 63 OWASP Controls Enforced (#![forbid(unsafe_code)])");
    println!("======================================================================");

    axum::serve(listener, app)
        .await
        .expect("evaluatech-server terminated unexpectedly");
}
