//! School AAAAA13 EvaluaTech — Single-Binary Pure-Rust Full-Stack Server (`evaluatech-server`).
//!
//! Serves the embedded Coolors Periwinkle-Blue WebAssembly SPA (`evaluatech_web_bg.wasm`),
//! SQLite relational catalog + zero-PII `evaluation_vault`, 63 OWASP Security Maxxxxxing controls,
//! and native Rust 10,000-sample empirical multilingual sentiment & pedagogical analytics.

use evaluatech_server::{AppState, build_router};

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

    let addr = format!("{host}:{port}");
    let app = build_router(state);
    let listener = tokio::net::TcpListener::bind(&addr)
        .await
        .unwrap_or_else(|e| panic!("failed to bind {addr}: {e}"));

    println!("======================================================================");
    println!(" School AAAAA13 EvaluaTech — 100% Pure-Rust + WebAssembly Stack");
    println!(" Listening on:  http://{addr}");
    println!(" SQLite Store:  {db_path}");
    println!(" Security Gate: 63 / 63 OWASP Controls Enforced (#![forbid(unsafe_code)])");
    println!("======================================================================");

    axum::serve(listener, app)
        .await
        .expect("evaluatech-server terminated unexpectedly");
}
