//! Standalone Pure-Rust EvaluaTech NLP Microservice (`127.0.0.1:5001`).
//!
//! HTTP JSON analytics daemon powered by `axum` + `evaluatech-nlp`.

use axum::{
    Router,
    body::Bytes,
    extract::DefaultBodyLimit,
    http::{Method, StatusCode},
    response::IntoResponse,
    routing::any,
};
use evaluatech_nlp::{
    CALAMANCY_DEFAULT_MODEL, CALAMANCY_REPO_URL, MAX_BATCH_SIZE, MAX_COMMENT_LENGTH,
    MAX_HTTP_BODY_BYTES, MAX_PROFESSOR_QUESTIONS, PEDAGOGICAL_TAGS, TAG_UNCLASSIFIED,
    analyze_comment_str, analyze_comment_value, analyze_professor_questions,
    analyze_section_dossier, generate_pedagogical_action_plan, get_engine_provenance, scrub_pii,
    summarize_comments, synthesize_professor_questionnaires,
};
use serde_json::{Value, json};

fn json_resp(status: StatusCode, body: Value) -> impl IntoResponse {
    (
        status,
        [("Content-Type", "application/json; charset=utf-8")],
        body.to_string(),
    )
}

fn parse_json_bytes(bytes: &Bytes) -> Result<Option<Value>, ()> {
    if bytes.is_empty() {
        return Ok(None);
    }
    let s = std::str::from_utf8(bytes).map_err(|_| ())?.trim();
    if s.is_empty() {
        return Ok(None);
    }
    serde_json::from_str(s).map(Some).map_err(|_| ())
}

async fn handle_health(method: Method) -> impl IntoResponse {
    if method != Method::GET {
        return json_resp(
            StatusCode::METHOD_NOT_ALLOWED,
            json!({"error": "Method not allowed."}),
        );
    }
    let prov = get_engine_provenance();
    let mut all_tags: Vec<&str> = PEDAGOGICAL_TAGS.to_vec();
    all_tags.push(TAG_UNCLASSIFIED);
    json_resp(
        StatusCode::OK,
        json!({
            "status": "ok",
            "service": "evaluatech-nlp-analytics",
            "version": "2.0.0-rust",
            "nlp_engine": "calamanCy+VADER",
            "runtime_engine": prov["runtime_engine"],
            "calamancy_loaded": false,
            "fallback_heuristic": false,
            "vader_loaded": true,
            "ml_model_loaded": true,
            "calamancy_model": CALAMANCY_DEFAULT_MODEL,
            "calamancy_repo": CALAMANCY_REPO_URL,
            "pedagogical_tags": PEDAGOGICAL_TAGS,
            "unclassified_tag": TAG_UNCLASSIFIED,
            "all_pedagogical_tags": all_tags,
            "max_professor_questions": MAX_PROFESSOR_QUESTIONS,
        }),
    )
}

async fn handle_analyze(method: Method, body: Bytes) -> impl IntoResponse {
    if method != Method::POST {
        return json_resp(
            StatusCode::METHOD_NOT_ALLOWED,
            json!({"error": "Method not allowed."}),
        );
    }
    if body.len() > MAX_HTTP_BODY_BYTES {
        return json_resp(
            StatusCode::PAYLOAD_TOO_LARGE,
            json!({"error": "Payload exceeds maximum allowed size.", "code": "PAYLOAD_TOO_LARGE"}),
        );
    }
    let Ok(Some(Value::Object(map))) = parse_json_bytes(&body) else {
        return json_resp(
            StatusCode::BAD_REQUEST,
            json!({"error": "Invalid JSON object payload."}),
        );
    };
    let Some(raw_field) = map.get("comment").or_else(|| map.get("text")) else {
        return json_resp(
            StatusCode::UNPROCESSABLE_ENTITY,
            json!({"error": "Field 'comment' or 'text' is required."}),
        );
    };
    if raw_field.is_object() || raw_field.is_array() {
        return json_resp(
            StatusCode::UNPROCESSABLE_ENTITY,
            json!({"error": "Field 'comment' must be a scalar string.", "code": "INVALID_COMMENT_TYPE"}),
        );
    }
    let text = raw_field.as_str().unwrap_or("");
    if text.chars().count() > MAX_COMMENT_LENGTH {
        return json_resp(
            StatusCode::UNPROCESSABLE_ENTITY,
            json!({
                "error": format!("Comment exceeds maximum length of {MAX_COMMENT_LENGTH} characters."),
                "code": "COMMENT_TOO_LONG"
            }),
        );
    }
    let mut res = analyze_comment_str(text);
    if let Value::Object(ref mut out) = res {
        out.insert("scrubbed_preview".to_string(), json!(scrub_pii(text)));
        if let Some(pq) = map.get("professor_questions")
            && pq.is_object()
        {
            out.insert(
                "professor_questions".to_string(),
                analyze_professor_questions(pq),
            );
        }
    }
    json_resp(StatusCode::OK, res)
}

async fn handle_batch(method: Method, body: Bytes) -> impl IntoResponse {
    if method != Method::POST {
        return json_resp(
            StatusCode::METHOD_NOT_ALLOWED,
            json!({"error": "Method not allowed."}),
        );
    }
    let Ok(Some(payload)) = parse_json_bytes(&body) else {
        return json_resp(
            StatusCode::BAD_REQUEST,
            json!({"error": "Invalid JSON payload."}),
        );
    };
    let comments_opt = match &payload {
        Value::Array(arr) => Some(arr),
        Value::Object(m) => m
            .get("comments")
            .or_else(|| m.get("texts"))
            .and_then(Value::as_array),
        _ => None,
    };
    let Some(comments) = comments_opt else {
        return json_resp(
            StatusCode::UNPROCESSABLE_ENTITY,
            json!({"error": "Field 'comments' must be a list."}),
        );
    };
    if comments.len() > MAX_BATCH_SIZE {
        return json_resp(
            StatusCode::UNPROCESSABLE_ENTITY,
            json!({
                "error": format!("Batch size exceeds maximum of {MAX_BATCH_SIZE} comments."),
                "code": "BATCH_LIMIT_EXCEEDED"
            }),
        );
    }
    let results: Vec<Value> = comments.iter().map(analyze_comment_value).collect();
    let summary = summarize_comments(&results);
    json_resp(
        StatusCode::OK,
        json!({
            "count": results.len(),
            "results": results,
            "summary": summary,
        }),
    )
}

async fn handle_questionnaire(method: Method, body: Bytes) -> impl IntoResponse {
    if method != Method::POST {
        return json_resp(
            StatusCode::METHOD_NOT_ALLOWED,
            json!({"error": "Method not allowed."}),
        );
    }
    let Ok(Some(payload)) = parse_json_bytes(&body) else {
        return json_resp(
            StatusCode::BAD_REQUEST,
            json!({"error": "Invalid JSON object payload."}),
        );
    };
    if let Value::Array(arr) = payload {
        return json_resp(StatusCode::OK, synthesize_professor_questionnaires(&arr));
    }
    if let Some(arr) = payload.get("questionnaires").and_then(Value::as_array) {
        return json_resp(StatusCode::OK, synthesize_professor_questionnaires(arr));
    }
    if !payload.is_object() {
        return json_resp(
            StatusCode::BAD_REQUEST,
            json!({"error": "Invalid JSON object payload."}),
        );
    }
    let analysis = analyze_professor_questions(&payload);
    let status = if analysis.get("error_code").is_some() {
        StatusCode::UNPROCESSABLE_ENTITY
    } else {
        StatusCode::OK
    };
    json_resp(status, analysis)
}

async fn handle_synthesize(method: Method, body: Bytes) -> impl IntoResponse {
    if method != Method::POST {
        return json_resp(
            StatusCode::METHOD_NOT_ALLOWED,
            json!({"error": "Method not allowed."}),
        );
    }
    let Ok(Some(payload)) = parse_json_bytes(&body) else {
        return json_resp(
            StatusCode::BAD_REQUEST,
            json!({"error": "Invalid JSON payload."}),
        );
    };
    let items: Vec<Value> = match payload {
        Value::Array(arr) => arr,
        Value::Object(ref m) => m
            .get("questionnaires")
            .or_else(|| m.get("items"))
            .and_then(Value::as_array)
            .cloned()
            .unwrap_or_else(|| vec![payload]),
        _ => Vec::new(),
    };
    json_resp(StatusCode::OK, synthesize_professor_questionnaires(&items))
}

async fn handle_dossier(method: Method, body: Bytes) -> impl IntoResponse {
    if method != Method::POST {
        return json_resp(
            StatusCode::METHOD_NOT_ALLOWED,
            json!({"error": "Method not allowed."}),
        );
    }
    let Ok(Some(Value::Object(map))) = parse_json_bytes(&body) else {
        return json_resp(
            StatusCode::BAD_REQUEST,
            json!({"error": "Invalid JSON object payload."}),
        );
    };
    let comments: Vec<Value> = match map.get("comments").or_else(|| map.get("comment")) {
        Some(Value::Array(arr)) => arr.clone(),
        Some(Value::String(s)) if !s.trim().is_empty() => vec![Value::String(s.clone())],
        _ => Vec::new(),
    };
    let ratings: Option<Vec<i32>> = map
        .get("cluster_ratings")
        .or_else(|| map.get("ratings"))
        .and_then(Value::as_array)
        .map(|arr| {
            arr.iter()
                .filter_map(|v| v.as_i64().map(|x| x as i32))
                .collect()
        });
    let pq = map
        .get("professor_questions")
        .or_else(|| map.get("qualitative_questions"));
    json_resp(
        StatusCode::OK,
        analyze_section_dossier(&comments, ratings.as_deref(), pq),
    )
}

async fn handle_intervention(method: Method, body: Bytes) -> impl IntoResponse {
    if method != Method::POST {
        return json_resp(
            StatusCode::METHOD_NOT_ALLOWED,
            json!({"error": "Method not allowed."}),
        );
    }
    let Ok(Some(Value::Object(map))) = parse_json_bytes(&body) else {
        return json_resp(
            StatusCode::BAD_REQUEST,
            json!({"error": "Invalid JSON object payload."}),
        );
    };
    let name = map
        .get("faculty_name")
        .and_then(Value::as_str)
        .unwrap_or("Faculty Member");
    let mean = map
        .get("mean_score")
        .and_then(Value::as_f64)
        .unwrap_or(3.10);
    let neg = map
        .get("negative_sentiment_pct")
        .and_then(Value::as_f64)
        .unwrap_or(38.0);
    let tag = map.get("primary_tag").and_then(Value::as_str);
    json_resp(
        StatusCode::OK,
        generate_pedagogical_action_plan(name, mean, neg, tag),
    )
}

pub fn build_nlp_router() -> Router {
    Router::new()
        .route("/health", any(handle_health))
        .route("/analyze", any(handle_analyze))
        .route("/analyze/batch", any(handle_batch))
        .route("/analyze/questionnaire", any(handle_questionnaire))
        .route("/analyze/questionnaires", any(handle_questionnaire))
        .route("/analyze/synthesize", any(handle_synthesize))
        .route("/analyze/questionnaires/synthesize", any(handle_synthesize))
        .route("/analyze/questionnaire/synthesize", any(handle_synthesize))
        .route("/analyze/dossier", any(handle_dossier))
        .route("/analyze-dossier", any(handle_dossier))
        .route("/analyze/intervention-plan", any(handle_intervention))
        .route("/analyze/action-plan", any(handle_intervention))
        .route("/analyze/intervention", any(handle_intervention))
        .layer(DefaultBodyLimit::max(MAX_HTTP_BODY_BYTES + 1024))
}

#[tokio::main]
async fn main() {
    let host = std::env::var("NLP_HOST").unwrap_or_else(|_| "127.0.0.1".to_string());
    let port: u16 = std::env::var("NLP_PORT")
        .ok()
        .and_then(|p| p.parse().ok())
        .unwrap_or(5001);
    let addr = format!("{host}:{port}");
    let listener = tokio::net::TcpListener::bind(&addr)
        .await
        .expect("bind evaluatech-nlp-server");
    println!("EvaluaTech Pure-Rust NLP + 10k ML Server listening on http://{addr}");
    axum::serve(listener, build_nlp_router())
        .await
        .expect("serve evaluatech-nlp-server");
}
