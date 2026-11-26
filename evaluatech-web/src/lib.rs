#![forbid(unsafe_code)]
//! School AAAAA13 — Official University Faculty Evaluation Portal (`evaluatech-web`).
//!
//! Provides an accessible, calm Academic Document Sheet interface across all five
//! institutional roles (`student`, `faculty`, `chair`, `dean`, `admin`) with
//! high-contrast typography, generous 44px+ controls, and strict student privacy.

pub mod questionnaire;

use std::cell::RefCell;

use evaluatech_nlp::{PEDAGOGICAL_TAGS, compute_likert_stats, sanitize_csv_cell};
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};
use wasm_bindgen::JsCast;
use wasm_bindgen::prelude::*;
use wasm_bindgen_futures::{JsFuture, spawn_local};
use web_sys::{
    Document, Element, Event, Headers, HtmlElement, HtmlInputElement, HtmlSelectElement,
    HtmlTextAreaElement, KeyboardEvent, Request, RequestInit, Response, ScrollBehavior,
    ScrollIntoViewOptions, Storage, Window,
};

use self::questionnaire::FIVE_CLUSTERS;

const SESSION_TOKEN_KEY: &str = "evaluatech_auth_token";
const SESSION_USER_KEY: &str = "evaluatech_auth_user";
const THEME_STORAGE_KEY: &str = "theme";
const FONT_SCALE_STORAGE_KEY: &str = "font_scale";

const SECTION_JUMP_SHORT: [&str; 5] = [
    "Teaching",
    "Grading",
    "Organization",
    "Professionalism",
    "Support",
];

const SECTION_TITLES: [(&str, &str, &str); 5] = [
    (
        "A",
        "Teaching & Explanation",
        "Clarity of explanations, mastery of subject matter, pacing, and helpful examples.",
    ),
    (
        "B",
        "Grading & Assessments",
        "Clear grading criteria, fair exams and projects, timely return of work, and helpful feedback.",
    ),
    (
        "C",
        "Class Organization",
        "Punctuality, adherence to the course syllabus, orderly class sessions, and clear announcements.",
    ),
    (
        "D",
        "Professionalism",
        "Mutual respect, welcoming classroom atmosphere, fairness, and academic integrity.",
    ),
    (
        "E",
        "Student Support",
        "Consultation availability, responsiveness to questions, academic guidance, and encouragement.",
    ),
];

#[derive(Clone, Debug, Default, Serialize, Deserialize)]
pub struct UserSession {
    pub id: i64,
    pub institutional_id: String,
    pub name: String,
    pub email: String,
    pub role: String,
    pub role_label: String,
    pub department_id: Option<i64>,
    pub department_name: Option<String>,
    pub college_name: Option<String>,
    pub must_change_password: bool,
}

#[derive(Clone, Debug, Serialize, Deserialize)]
struct DraftPayload {
    ratings: Vec<i32>,
    comment_text: String,
    three_words: String,
    improvements: String,
    strengths: String,
    recommendations: String,
}

impl Default for DraftPayload {
    fn default() -> Self {
        Self {
            ratings: vec![0; 25],
            comment_text: String::new(),
            three_words: String::new(),
            improvements: String::new(),
            strengths: String::new(),
            recommendations: String::new(),
        }
    }
}

struct AppState {
    route: String,
    theme: String,
    font_scale: String,
    token: Option<String>,
    user: Option<UserSession>,
    mobile_menu_open: bool,
    show_password: bool,
    show_rubric_modal: bool,
    show_discard_modal: bool,
    banner_msg: Option<(String, bool)>,
    verify_result: Option<Value>,
    // Student workspace
    queue_data: Option<Value>,
    queue_filter: String,
    queue_search: String,
    receipt_modal_course: Option<Value>,
    eval_section_id: i64,
    eval_step: u8,
    eval_started_ms: f64,
    eval_start_token: Option<String>,
    eval_submission_key: String,
    draft: DraftPayload,
    incomplete_indices: Vec<usize>,
    show_straight_line_modal: bool,
    submitting: bool,
    // Faculty workspace
    faculty_sections: Option<Value>,
    selected_faculty_sec_id: i64,
    selected_faculty_report: Option<Value>,
    // Chair / Dean workspace
    dean_tab: String,
    dean_overview: Option<Value>,
    dean_watchlist: Option<Value>,
    dean_heatmap: Option<Value>,
    dean_participation: Option<Value>,
    dean_trends: Option<Value>,
    dean_insights: Option<Value>,
    insights_query: String,
    insights_valence: String,
    compiled_dossier: Option<Value>,
    // Registrar workspace
    admin_tab: String,
    admin_sections: Option<Value>,
    admin_window: Option<Value>,
    admin_notice_override: Option<String>,
    admin_security: Option<Value>,
    admin_issued_creds: Option<Value>,
}

impl AppState {
    fn new() -> Self {
        Self {
            route: "/".to_string(),
            theme: "light".to_string(),
            font_scale: "normal".to_string(),
            token: None,
            user: None,
            mobile_menu_open: false,
            show_password: false,
            show_rubric_modal: false,
            show_discard_modal: false,
            banner_msg: None,
            verify_result: None,
            queue_data: None,
            queue_filter: "ALL".to_string(),
            queue_search: String::new(),
            receipt_modal_course: None,
            eval_section_id: 0,
            eval_step: 1,
            eval_started_ms: 0.0,
            eval_start_token: None,
            eval_submission_key: String::new(),
            draft: DraftPayload::default(),
            incomplete_indices: Vec::new(),
            show_straight_line_modal: false,
            submitting: false,
            faculty_sections: None,
            selected_faculty_sec_id: 0,
            selected_faculty_report: None,
            dean_tab: "summary".to_string(),
            dean_overview: None,
            dean_watchlist: None,
            dean_heatmap: None,
            dean_participation: None,
            dean_trends: None,
            dean_insights: None,
            insights_query: String::new(),
            insights_valence: String::new(),
            compiled_dossier: None,
            admin_tab: "schedule".to_string(),
            admin_sections: None,
            admin_window: None,
            admin_notice_override: None,
            admin_security: None,
            admin_issued_creds: None,
        }
    }
}

thread_local! {
    static STATE: RefCell<AppState> = RefCell::new(AppState::new());
}

/// Sanitize redirect targets: allow only relative single-slash paths (`/...`).
pub fn sanitize_redirect_path(raw: &str, fallback: &str) -> String {
    let trimmed = raw.trim();
    if trimmed.is_empty()
        || !trimmed.starts_with('/')
        || trimmed.starts_with("//")
        || trimmed.contains('\\')
        || trimmed.contains(':')
        || trimmed.chars().any(char::is_control)
    {
        return fallback.to_string();
    }
    trimmed.to_string()
}

pub fn default_route_for_role(role: &str) -> &'static str {
    match role {
        "admin" => "/admin",
        "dean" => "/dean",
        "chair" => "/chair",
        "faculty" => "/faculty",
        _ => "/queue",
    }
}

fn role_display_title(role: &str) -> &'static str {
    match role {
        "admin" => "Registrar",
        "dean" => "Dean",
        "chair" => "Chair",
        "faculty" => "Faculty",
        _ => "Student",
    }
}

pub fn sql_to_datetime_local(raw: &str) -> String {
    let t = raw.trim();
    if t.len() >= 16 {
        let s = t[..16].replace(' ', "T");
        if s.len() == 16 && s.as_bytes()[10] == b'T' {
            return s;
        }
    }
    "2026-08-15T08:00".to_string()
}

pub fn datetime_local_to_sql(raw: &str, default_secs: &str) -> String {
    let t = raw.trim();
    if t.contains('T') {
        let replaced = t.replace('T', " ");
        if replaced.len() == 16 {
            return format!("{replaced}:{default_secs}");
        }
        return replaced;
    }
    t.to_string()
}

fn scroll_top() {
    if let Some(w) = win() {
        w.scroll_to_with_x_and_y(0.0, 0.0);
    }
}

fn friendly_area_name(raw_tag: &str) -> &str {
    match raw_tag {
        "Pedagogical Delivery" => "Teaching & Explanation",
        "Assessment & Grading" => "Grading & Assessments",
        "Classroom Management" => "Class Organization",
        "Professional Conduct" => "Professionalism",
        "Student Support & Mentorship" => "Student Support",
        "Unclassified / Needs Review" => "General / Campus Facilities",
        other if !other.is_empty() => other,
        _ => "Teaching & Explanation",
    }
}

fn rating_scale_word(val: i32) -> &'static str {
    match val {
        5 => "Outstanding",
        4 => "Very Satisfactory",
        3 => "Satisfactory",
        2 => "Fair",
        _ => "Needs Improvement",
    }
}

fn perf_adjective(score: f64) -> &'static str {
    if score >= 4.50 {
        "Outstanding"
    } else if score >= 4.00 {
        "Very Satisfactory"
    } else if score >= 3.50 {
        "Satisfactory"
    } else if score >= 3.00 {
        "Fair"
    } else {
        "Needs Improvement"
    }
}

pub fn esc(s: &str) -> String {
    let mut out = String::with_capacity(s.len());
    for c in s.chars() {
        match c {
            '&' => out.push_str("&amp;"),
            '<' => out.push_str("&lt;"),
            '>' => out.push_str("&gt;"),
            '"' => out.push_str("&quot;"),
            '\'' => out.push_str("&#39;"),
            _ => out.push(c),
        }
    }
    out
}

fn percent_encode_uri(raw: &str) -> String {
    let mut out = String::with_capacity(raw.len() * 2);
    for &b in raw.as_bytes() {
        if b.is_ascii_alphanumeric() || matches!(b, b'-' | b'_' | b'.' | b'~') {
            out.push(b as char);
        } else {
            out.push_str(&format!("%{b:02X}"));
        }
    }
    out
}

fn win() -> Option<Window> {
    web_sys::window()
}

fn doc() -> Option<Document> {
    win()?.document()
}

fn session_storage() -> Option<Storage> {
    win()?.session_storage().ok().flatten()
}

fn local_storage() -> Option<Storage> {
    win()?.local_storage().ok().flatten()
}

fn apply_theme(theme: &str) {
    let normalized = if theme == "dark" { "dark" } else { "light" };
    if let Some(d) = doc()
        && let Some(root) = d.document_element()
    {
        let _ = root.set_attribute("data-theme", normalized);
    }
    if let Some(ls) = local_storage() {
        let _ = ls.set_item(THEME_STORAGE_KEY, normalized);
    } else if let Some(ss) = session_storage() {
        let _ = ss.set_item(THEME_STORAGE_KEY, normalized);
    }
    STATE.with(|c| c.borrow_mut().theme = normalized.to_string());
}

fn apply_font_scale(scale: &str) {
    let normalized = if scale == "large" { "large" } else { "normal" };
    if let Some(d) = doc()
        && let Some(root) = d.document_element()
    {
        if normalized == "large" {
            let _ = root.set_attribute("data-font-scale", "large");
        } else {
            let _ = root.remove_attribute("data-font-scale");
        }
    }
    if let Some(ls) = local_storage() {
        let _ = ls.set_item(FONT_SCALE_STORAGE_KEY, normalized);
    } else if let Some(ss) = session_storage() {
        let _ = ss.set_item(FONT_SCALE_STORAGE_KEY, normalized);
    }
    STATE.with(|c| c.borrow_mut().font_scale = normalized.to_string());
}

fn announce_sr(msg: &str) {
    if let Some(d) = doc()
        && let Some(el) = d.get_element_by_id("sr-announcer")
    {
        el.set_text_content(Some(msg));
    }
}

fn update_page_title(route: &str) {
    let section = match route.split('?').next().unwrap_or("/") {
        "/" => "Welcome",
        "/verify" => "Verify Completion Receipt",
        "/login" => "Sign In",
        "/account/password" => "Create Permanent Password",
        "/queue" => "My Enrolled Subjects",
        p if p.starts_with("/evaluate") => "Subject Evaluation Form",
        "/faculty" => "My Class Results",
        "/chair" => "Department Evaluation Summary",
        "/dean" => "College Evaluation Summary",
        "/admin" => "Registrar Evaluation Administration",
        "/access-denied" => "Access Restricted",
        _ => "Faculty Evaluation Portal",
    };
    let full = format!("{section} — School AAAAA13 Faculty Evaluation Portal");
    if let Some(d) = doc() {
        d.set_title(&full);
    }
    announce_sr(&format!(
        "{section}, School AAAAA13 Faculty Evaluation Portal"
    ));
}

fn trigger_csv_download(filename: &str, csv_content: &str) {
    let Some(d) = doc() else {
        return;
    };
    let Ok(el) = d.create_element("a") else {
        return;
    };
    let uri = format!(
        "data:text/csv;charset=utf-8,{}",
        percent_encode_uri(csv_content)
    );
    let _ = el.set_attribute("href", &uri);
    let _ = el.set_attribute("download", filename);
    if let Ok(he) = el.dyn_into::<HtmlElement>() {
        he.click();
    }
}

fn save_session(token: &str, user: &UserSession) {
    if let Some(st) = session_storage() {
        let _ = st.set_item(SESSION_TOKEN_KEY, token);
        if let Ok(u_json) = serde_json::to_string(user) {
            let _ = st.set_item(SESSION_USER_KEY, &u_json);
        }
    }
}

fn clear_session() {
    if let Some(st) = session_storage() {
        let _ = st.remove_item(SESSION_TOKEN_KEY);
        let _ = st.remove_item(SESSION_USER_KEY);
    }
}

fn restore_session_from_storage() {
    let saved_theme = local_storage()
        .and_then(|ls| ls.get_item(THEME_STORAGE_KEY).ok().flatten())
        .or_else(|| session_storage().and_then(|ss| ss.get_item(THEME_STORAGE_KEY).ok().flatten()))
        .filter(|v| v == "dark" || v == "light")
        .unwrap_or_else(|| "light".to_string());

    let saved_scale = local_storage()
        .and_then(|ls| ls.get_item(FONT_SCALE_STORAGE_KEY).ok().flatten())
        .or_else(|| {
            session_storage().and_then(|ss| ss.get_item(FONT_SCALE_STORAGE_KEY).ok().flatten())
        })
        .filter(|v| v == "large" || v == "normal")
        .unwrap_or_else(|| "normal".to_string());

    if let Some(st) = session_storage() {
        let tok = st.get_item(SESSION_TOKEN_KEY).ok().flatten();
        let usr = st
            .get_item(SESSION_USER_KEY)
            .ok()
            .flatten()
            .and_then(|raw| serde_json::from_str::<UserSession>(&raw).ok());
        STATE.with(|cell| {
            let mut s = cell.borrow_mut();
            s.token = tok;
            s.user = usr;
        });
    }
    apply_theme(&saved_theme);
    apply_font_scale(&saved_scale);
}

fn draft_key(section_id: i64) -> String {
    format!("evaluatech_draft_sec_{section_id}")
}

fn save_draft(section_id: i64, draft: &DraftPayload) {
    if section_id <= 0 {
        return;
    }
    if let Some(st) = session_storage()
        && let Ok(raw) = serde_json::to_string(draft)
    {
        let _ = st.set_item(&draft_key(section_id), &raw);
    }
}

fn load_draft(section_id: i64) -> DraftPayload {
    if let Some(st) = session_storage()
        && let Ok(Some(raw)) = st.get_item(&draft_key(section_id))
        && let Ok(mut parsed) = serde_json::from_str::<DraftPayload>(&raw)
    {
        if parsed.ratings.len() != 25 {
            parsed.ratings = vec![0; 25];
        }
        return parsed;
    }
    DraftPayload::default()
}

fn remove_draft(section_id: i64) {
    if let Some(st) = session_storage() {
        let _ = st.remove_item(&draft_key(section_id));
    }
}

fn input_val(id: &str) -> String {
    let Some(d) = doc() else {
        return String::new();
    };
    let Some(el) = d.get_element_by_id(id) else {
        return String::new();
    };
    if let Ok(inp) = el.clone().dyn_into::<HtmlInputElement>() {
        return inp.value();
    }
    if let Ok(ta) = el.clone().dyn_into::<HtmlTextAreaElement>() {
        return ta.value();
    }
    if let Ok(sel) = el.dyn_into::<HtmlSelectElement>() {
        return sel.value();
    }
    String::new()
}

fn humanize_api_error(body: &Value, fallback: &str) -> String {
    match body.get("code").and_then(Value::as_str).unwrap_or("") {
        "INVALID_CREDENTIALS" => {
            "The ID number, email address, or password you entered is incorrect. Please check and try again."
                .to_string()
        }
        "MISSING_CREDENTIALS" | "MISSING_OR_NON_SCALAR_CREDENTIALS" => {
            "Please enter both your university ID or email address and your password.".to_string()
        }
        "RATE_LIMITED" => {
            "Too many failed sign-in attempts. Please wait one minute before trying again."
                .to_string()
        }
        "DOUBLE_BLIND_LOCKED" => {
            "Class evaluation results remain sealed until final semester grades are submitted and verified by the Office of the University Registrar."
                .to_string()
        }
        "EVALUATION_WINDOW_CLOSED" | "EVALUATION_WINDOW_NOT_YET_OPEN" => {
            "The evaluation submission schedule is not currently open. Please check with the Office of the University Registrar."
                .to_string()
        }
        _ => body
            .get("message")
            .and_then(Value::as_str)
            .unwrap_or(fallback)
            .to_string(),
    }
}

async fn api_request(
    method: &str,
    path: &str,
    body: Option<Value>,
) -> Result<(u16, Value), String> {
    let window = win().ok_or_else(|| "Browser window unavailable.".to_string())?;
    let opts = RequestInit::new();
    opts.set_method(method);

    let headers = Headers::new().map_err(|_| "Could not prepare request headers.".to_string())?;
    let _ = headers.set("Accept", "application/json");

    let tok = STATE.with(|c| c.borrow().token.clone());
    if let Some(t) = tok {
        let _ = headers.set("Authorization", &format!("Bearer {t}"));
    }

    if let Some(b) = body {
        let _ = headers.set("Content-Type", "application/json");
        opts.set_body(&JsValue::from_str(&b.to_string()));
    }
    opts.set_headers(&headers);

    let req = Request::new_with_str_and_init(path, &opts)
        .map_err(|_| "Unable to create portal request.".to_string())?;
    let resp_js = JsFuture::from(window.fetch_with_request(&req))
        .await
        .map_err(|_| {
            "Unable to reach the university server. Please check your connection.".to_string()
        })?;
    let resp: Response = resp_js
        .dyn_into()
        .map_err(|_| "Unexpected response from server.".to_string())?;
    let status = resp.status();
    let text_promise = resp
        .text()
        .map_err(|_| "Could not read server response.".to_string())?;
    let text_js = JsFuture::from(text_promise)
        .await
        .map_err(|_| "Could not decode server response.".to_string())?;
    let raw_text = text_js.as_string().unwrap_or_default();
    let parsed =
        serde_json::from_str::<Value>(&raw_text).unwrap_or_else(|_| json!({ "raw": raw_text }));
    Ok((status, parsed))
}

fn push_route(target: &str) {
    let clean = sanitize_redirect_path(target, "/");
    let resolved = enforce_route_guards(&clean);
    if let Some(w) = win()
        && let Ok(hist) = w.history()
    {
        let _ = hist.push_state_with_url(&JsValue::NULL, "", Some(&resolved));
    }
    STATE.with(|c| {
        let mut s = c.borrow_mut();
        s.route = resolved.clone();
        s.mobile_menu_open = false;
        s.banner_msg = None;
    });
    update_page_title(&resolved);
    render();
    scroll_top();
    load_route_data(&resolved);
}

fn enforce_route_guards(requested: &str) -> String {
    let base_path = requested.split('?').next().unwrap_or("/");
    STATE.with(|c| {
        let s = c.borrow();
        let is_public = matches!(base_path, "/" | "/login" | "/verify" | "/access-denied");
        if base_path == "/login"
            && let Some(ref u) = s.user
        {
            if u.must_change_password {
                return "/account/password".to_string();
            }
            return default_route_for_role(&u.role).to_string();
        }
        if is_public {
            return requested.to_string();
        }
        let Some(ref u) = s.user else {
            return "/login".to_string();
        };
        if u.must_change_password && base_path != "/account/password" {
            return "/account/password".to_string();
        }
        let allowed = match base_path {
            "/account/password" => true,
            "/queue" => u.role == "student",
            p if p.starts_with("/evaluate") => u.role == "student",
            "/faculty" => u.role == "faculty" || u.role == "admin",
            "/chair" => u.role == "chair" || u.role == "dean" || u.role == "admin",
            "/dean" => u.role == "dean" || u.role == "admin",
            "/admin" => u.role == "admin",
            _ => true,
        };
        if !allowed {
            if u.role == "student" {
                "/queue".to_string()
            } else {
                "/access-denied".to_string()
            }
        } else {
            requested.to_string()
        }
    })
}

fn fetch_faculty_report_async(sec_id: i64) {
    if sec_id <= 0 {
        return;
    }
    spawn_local(async move {
        let url = format!("/api/faculty/sections/{sec_id}/report");
        if let Ok((200, rep)) = api_request("GET", &url, None).await {
            STATE.with(|c| {
                let mut s = c.borrow_mut();
                if s.selected_faculty_sec_id == sec_id {
                    s.selected_faculty_report = Some(rep);
                }
            });
            render();
        }
    });
}

fn load_route_data(route: &str) {
    let base = route.split('?').next().unwrap_or("/").to_string();
    match base.as_str() {
        "/queue" => {
            spawn_local(async {
                if let Ok((200, data)) = api_request("GET", "/api/student/queue", None).await {
                    STATE.with(|c| c.borrow_mut().queue_data = Some(data));
                    render();
                }
            });
        }
        p if p.starts_with("/evaluate/") => {
            let sec_id = p
                .trim_start_matches("/evaluate/")
                .parse::<i64>()
                .unwrap_or(1);
            let need_queue = STATE.with(|c| {
                let mut s = c.borrow_mut();
                if s.eval_section_id != sec_id {
                    s.eval_section_id = sec_id;
                    s.eval_step = 1;
                    s.eval_started_ms = js_sys::Date::now();
                    s.eval_submission_key =
                        format!("SUB-SEC{sec_id}-{}", (js_sys::Date::now() as u64));
                    s.draft = load_draft(sec_id);
                    s.incomplete_indices.clear();
                    s.show_straight_line_modal = false;
                }
                s.queue_data.is_none()
            });
            render();
            if need_queue {
                spawn_local(async {
                    if let Ok((200, qdata)) = api_request("GET", "/api/student/queue", None).await {
                        STATE.with(|c| c.borrow_mut().queue_data = Some(qdata));
                        render();
                    }
                });
            }
            spawn_local(async move {
                let url = format!("/api/student/evaluations/{sec_id}/start");
                if let Ok((200, data)) = api_request("POST", &url, Some(json!({}))).await {
                    STATE.with(|c| {
                        c.borrow_mut().eval_start_token = data
                            .get("start_token")
                            .and_then(Value::as_str)
                            .map(String::from);
                    });
                }
            });
        }
        "/faculty" => {
            spawn_local(async {
                if let Ok((200, data)) = api_request("GET", "/api/faculty/sections", None).await {
                    let auto_report_id = STATE.with(|c| {
                        let mut s = c.borrow_mut();
                        let secs = data
                            .get("sections")
                            .and_then(Value::as_array)
                            .cloned()
                            .unwrap_or_default();
                        let current_exists = secs.iter().any(|it| {
                            it.get("id").and_then(Value::as_i64) == Some(s.selected_faculty_sec_id)
                        });
                        if !current_exists {
                            s.selected_faculty_sec_id = secs
                                .first()
                                .and_then(|it| it.get("id"))
                                .and_then(Value::as_i64)
                                .unwrap_or(0);
                            s.selected_faculty_report = None;
                        }
                        let active_id = s.selected_faculty_sec_id;
                        let is_unlocked = secs.iter().any(|it| {
                            it.get("id").and_then(Value::as_i64) == Some(active_id)
                                && it
                                    .get("term_grades_submitted")
                                    .and_then(Value::as_bool)
                                    .unwrap_or(false)
                                && it
                                    .get("registrar_lock")
                                    .and_then(Value::as_bool)
                                    .unwrap_or(false)
                        });
                        s.faculty_sections = Some(data);
                        if is_unlocked { Some(active_id) } else { None }
                    });
                    render();
                    if let Some(rid) = auto_report_id {
                        fetch_faculty_report_async(rid);
                    }
                }
            });
        }
        "/chair" | "/dean" => {
            let prefix = if base == "/chair" {
                "/api/chair"
            } else {
                "/api/dean"
            };
            spawn_local(async move {
                let ov = api_request("GET", &format!("{prefix}/overview"), None).await;
                let wl = api_request("GET", &format!("{prefix}/watchlist"), None).await;
                let hm = api_request("GET", &format!("{prefix}/completion-heatmap"), None).await;
                let pt = api_request("GET", &format!("{prefix}/participation"), None).await;
                let tr = api_request("GET", &format!("{prefix}/longitudinal-trends"), None).await;
                let ins = api_request("GET", &format!("{prefix}/insights-search"), None).await;
                STATE.with(|c| {
                    let mut s = c.borrow_mut();
                    if let Ok((200, d)) = ov {
                        s.dean_overview = Some(d);
                    }
                    if let Ok((200, d)) = wl {
                        s.dean_watchlist = Some(d);
                    }
                    if let Ok((200, d)) = hm {
                        s.dean_heatmap = Some(d);
                    }
                    if let Ok((200, d)) = pt {
                        s.dean_participation = Some(d);
                    }
                    if let Ok((200, d)) = tr {
                        s.dean_trends = Some(d);
                    }
                    if let Ok((200, d)) = ins {
                        s.dean_insights = Some(d);
                    }
                });
                render();
            });
        }
        "/admin" => {
            spawn_local(async {
                let secs = api_request("GET", "/api/admin/sections", None).await;
                let win_r = api_request("GET", "/api/admin/evaluation-window", None).await;
                let sec_p = api_request("GET", "/api/admin/security-posture", None).await;
                STATE.with(|c| {
                    let mut s = c.borrow_mut();
                    if let Ok((200, d)) = secs {
                        s.admin_sections = Some(d);
                    }
                    if let Ok((200, d)) = win_r {
                        s.admin_window = Some(d);
                    }
                    if let Ok((200, d)) = sec_p {
                        s.admin_security = Some(d);
                    }
                });
                render();
            });
        }
        _ => {}
    }
}

fn render_navbar(s: &AppState) -> String {
    let collapse_cls = if s.mobile_menu_open {
        "nav-collapse open"
    } else {
        "nav-collapse"
    };
    let mobile_btn_label = if s.mobile_menu_open { "Close" } else { "Menu" };
    let theme_btn_label = if s.theme == "dark" {
        "Light View"
    } else {
        "Dark View"
    };

    let (font_btn_label, font_btn_cls, font_pressed) = if s.font_scale == "large" {
        ("Standard Text", "nav-theme-btn active", "true")
    } else {
        ("A+ Large Text", "nav-theme-btn", "false")
    };

    let (primary_links, utility_controls) = if let Some(ref u) = s.user {
        let user_display = format!("{} · {}", esc(&u.name), role_display_title(&u.role));
        let util = format!(
            r#"<button type="button" class="{font_btn_cls}" aria-pressed="{font_pressed}" data-action="toggle-font-scale">{font_btn_label}</button>
               <button type="button" class="nav-theme-btn" data-action="toggle-theme">{theme_btn_label}</button>
               <span class="nav-user-display" title="{user_display}">{user_display}</span>
               <button type="button" class="nav-link-btn" data-action="logout">Sign Out</button>"#
        );

        let links = match u.role.as_str() {
            "student" => {
                let on_subjects = s.route.starts_with("/queue") || s.route.starts_with("/evaluate");
                format!(
                    r#"<button type="button" class="nav-link-btn {act}" data-action="nav" data-path="/queue">My Subjects</button>
                       <button type="button" class="nav-link-btn" data-action="open-rubric">Rating Scale</button>"#,
                    act = if on_subjects { "active" } else { "" },
                )
            }
            "faculty" => {
                let on_fac = s.route.starts_with("/faculty");
                format!(
                    r#"<button type="button" class="nav-link-btn {act}" data-action="nav" data-path="/faculty">My Class Results</button>
                       <button type="button" class="nav-link-btn" data-action="open-rubric">Rating Scale</button>"#,
                    act = if on_fac { "active" } else { "" },
                )
            }
            "chair" => {
                let on_chair = s.route.starts_with("/chair");
                format!(
                    r#"<button type="button" class="nav-link-btn {act}" data-action="nav" data-path="/chair">Department Overview</button>
                       <button type="button" class="nav-link-btn" data-action="open-rubric">Rating Scale</button>"#,
                    act = if on_chair { "active" } else { "" },
                )
            }
            "dean" => {
                let on_dean = s.route.starts_with("/dean");
                format!(
                    r#"<button type="button" class="nav-link-btn {act}" data-action="nav" data-path="/dean">College Overview</button>
                       <button type="button" class="nav-link-btn" data-action="open-rubric">Rating Scale</button>"#,
                    act = if on_dean { "active" } else { "" },
                )
            }
            _ => {
                let on_adm = s.route.starts_with("/admin");
                format!(
                    r#"<button type="button" class="nav-link-btn {act}" data-action="nav" data-path="/admin">Registrar Console</button>
                       <button type="button" class="nav-link-btn" data-action="open-rubric">Rating Scale</button>"#,
                    act = if on_adm { "active" } else { "" },
                )
            }
        };
        (links, util)
    } else {
        let links = format!(
            r#"<button type="button" class="nav-link-btn {a_home}" data-action="nav" data-path="/">Home</button>
               <button type="button" class="nav-link-btn {a_ver}" data-action="goto-verify">Verify Receipt</button>
               <button type="button" class="nav-link-btn" data-action="open-rubric">Rating Scale</button>"#,
            a_home = if s.route == "/" { "active" } else { "" },
            a_ver = if s.route == "/verify" { "active" } else { "" },
        );
        let util = format!(
            r#"<button type="button" class="{font_btn_cls}" aria-pressed="{font_pressed}" data-action="toggle-font-scale">{font_btn_label}</button>
               <button type="button" class="nav-theme-btn" data-action="toggle-theme">{theme_btn_label}</button>
               <button type="button" class="nav-signin-btn" data-action="nav" data-path="/login">Sign In</button>"#
        );
        (links, util)
    };

    format!(
        r#"<header class="top-navbar" role="banner" aria-label="University Faculty Evaluation Portal Header">
            <div class="top-navbar-inner">
                <a href="/" class="brand-cluster" data-action="nav" data-path="/">
                    <span class="university-crest" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3L3 7.5v5.5c0 5.2 3.8 8.9 9 10 5.2-1.1 9-4.8 9-10V7.5L12 3z"/>
                            <path d="M8.5 11.5h7M8.5 15h4.5"/>
                        </svg>
                    </span>
                    <span class="brand-titles">
                        <span class="brand-subline">School AAAAA13 &middot; Office of Academic Affairs</span>
                        <span class="brand-title">Faculty Evaluation Portal</span>
                    </span>
                </a>
                <button type="button" class="mobile-menu-toggle" aria-expanded="{exp}" data-action="toggle-mobile-menu">{mobile_btn_label}</button>
                <div class="{collapse_cls}">
                    <nav class="nav-primary-links" aria-label="Portal Navigation">{primary_links}</nav>
                    <div class="nav-utility-area">{utility_controls}</div>
                </div>
            </div>
        </header>"#,
        exp = s.mobile_menu_open,
    )
}

fn render_global_modals(s: &AppState) -> String {
    let mut out = String::new();

    if s.show_rubric_modal {
        out.push_str(
            r#"<div class="modal-backdrop no-print" role="dialog" aria-modal="true" aria-labelledby="rubric-title">
                <div class="modal-dialog">
                    <div class="flex-between-center">
                        <h2 id="rubric-title" style="margin:0;">1–5 Faculty Evaluation Rating Scale</h2>
                        <button type="button" class="btn btn-secondary" data-action="close-modals">Close</button>
                    </div>
                    <hr class="section-divider">
                    <p style="color:var(--ink-secondary);">Please rate each of the 25 statements honestly using the five-point scale below:</p>
                    <table class="receipt-table" aria-label="1 to 5 Rating Scale Descriptions">
                        <tbody>
                            <tr><th>5 — Outstanding</th><td>Consistently demonstrated at an exemplary collegiate standard throughout the semester.</td></tr>
                            <tr><th>4 — Very Satisfactory</th><td>Frequently demonstrated with clear effectiveness and consistency.</td></tr>
                            <tr><th>3 — Satisfactory</th><td>Meets expected course and teaching standards adequately.</td></tr>
                            <tr><th>2 — Fair</th><td>Inconsistently demonstrated; would benefit from structured improvement.</td></tr>
                            <tr><th>1 — Needs Improvement</th><td>Rarely demonstrated; requires prompt attention and mentoring support.</td></tr>
                        </tbody>
                    </table>
                    <p style="font-size:0.9rem;color:var(--ink-secondary);margin-bottom:0;">
                        <strong>Helpful tip:</strong> While viewing any statement row on the evaluation form, you may click a numbered button or press keys <strong>1</strong> through <strong>5</strong> on your keyboard.
                    </p>
                </div>
            </div>"#,
        );
    }

    if let Some(ref c) = s.receipt_modal_course {
        let code = c.get("course_code").and_then(Value::as_str).unwrap_or("");
        let title = c.get("course_title").and_then(Value::as_str).unwrap_or("");
        let sec_code = c
            .get("section_code")
            .and_then(Value::as_str)
            .unwrap_or("SEC-A");
        let prof = c.get("faculty_name").and_then(Value::as_str).unwrap_or("");
        let tok = c
            .get("clearance_token")
            .and_then(Value::as_str)
            .unwrap_or("Recorded");
        let cleared_at = c
            .get("cleared_at")
            .and_then(Value::as_str)
            .filter(|v| !v.is_empty())
            .unwrap_or("1st Semester, AY 2026–2027");
        out.push_str(&format!(
            r#"<div class="modal-backdrop receipt-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="rcpt-title">
                <div class="modal-dialog">
                    <p class="institution-line">School AAAAA13 &middot; Office of the University Registrar</p>
                    <h2 id="rcpt-title" style="margin:0.15rem 0 0;">Evaluation Completion Receipt</h2>
                    <hr class="section-divider">
                    <table class="receipt-table" aria-label="Evaluation Completion Receipt Details">
                        <tbody>
                            <tr><th>Academic Term</th><td>1st Semester, AY 2026–2027</td></tr>
                            <tr><th>Subject &amp; Section</th><td><strong>{code}</strong> &mdash; {title} (Section {sec_code})</td></tr>
                            <tr><th>Instructor</th><td>{prof}</td></tr>
                            <tr><th>Status</th><td><span class="status-badge status-ok">Completed</span></td></tr>
                            <tr><th>Date Recorded</th><td class="tabular-nums">{cleared_at}</td></tr>
                            <tr><th>Receipt Reference Code</th><td><span class="ref-code">{tok}</span></td></tr>
                        </tbody>
                    </table>
                    <p style="font-size:0.9rem;color:var(--ink-secondary);">
                        This receipt confirms only that you completed the evaluation for this enrolled subject. Your individual ratings and written comments are stored separately without your name or student ID.
                    </p>
                    <div class="btn-row no-print">
                        <button type="button" class="btn btn-primary" data-action="print-cert">Print Receipt</button>
                        <button type="button" class="btn btn-secondary" data-action="close-modals">Close</button>
                    </div>
                </div>
            </div>"#,
            code = esc(code),
            title = esc(title),
            sec_code = esc(sec_code),
            prof = esc(prof),
            cleared_at = esc(cleared_at),
            tok = esc(tok),
        ));
    }

    out
}

fn render_banner(s: &AppState) -> String {
    let Some((ref msg, is_err)) = s.banner_msg else {
        return String::new();
    };
    let cls = if is_err {
        "alert-banner alert-error"
    } else {
        "alert-banner alert-success"
    };
    format!(r#"<div class="{cls}" role="status">{}</div>"#, esc(msg))
}

fn render_verification_result(v: &Value) -> String {
    let valid = v.get("valid").and_then(Value::as_bool).unwrap_or(false);
    let tok = v
        .get("clearance_token")
        .and_then(Value::as_str)
        .unwrap_or("");
    if valid {
        let detail = if let Some(c_code) = v.get("course_code").and_then(Value::as_str) {
            let when = v
                .get("cleared_at")
                .and_then(Value::as_str)
                .filter(|s| !s.is_empty())
                .unwrap_or("active semester");
            format!(
                "Confirmed: An enrolled student completed the subject evaluation for <strong>{}</strong> (recorded {}).",
                esc(c_code),
                esc(when)
            )
        } else if let (Some(done), Some(tot)) = (
            v.get("completed_evaluations").and_then(Value::as_u64),
            v.get("total_enrolled_courses").and_then(Value::as_u64),
        ) {
            format!(
                "Confirmed: All <strong>{done} of {tot}</strong> enrolled subjects for the semester have been evaluated."
            )
        } else {
            "Confirmed: This evaluation completion receipt is authentic and recorded with the Office of the University Registrar."
                .to_string()
        };
        format!(
            r#"<div class="subtle-box" style="margin-top:1rem;" role="status">
                <div class="flex-between-center" style="margin-bottom:0.45rem;">
                    <span class="status-badge status-ok">Verified Completion Receipt</span>
                    <span class="ref-code">{}</span>
                </div>
                <p style="margin:0;font-size:0.95rem;">{detail} Individual ratings and written comments remain strictly confidential.</p>
            </div>"#,
            esc(tok),
        )
    } else {
        r#"<div class="alert-banner alert-error" style="margin-top:1rem;" role="status">
            <strong>Receipt Not Found:</strong> We could not verify that reference code. Please check the characters on your receipt and try again.
        </div>"#
            .to_string()
    }
}

fn render_receipt_checker_box(s: &AppState) -> String {
    let res_html = s
        .verify_result
        .as_ref()
        .map(render_verification_result)
        .unwrap_or_default();

    format!(
        r#"<section id="receipt-check-section" class="doc-sheet" aria-labelledby="verify-heading">
            <h2 id="verify-heading" style="margin-bottom:0.35rem;">Check a Completion Receipt</h2>
            <p style="color:var(--ink-secondary);font-size:0.95rem;">
                Enter a subject receipt code or semester completion reference code to confirm that an evaluation was recorded.
            </p>
            <div class="form-group" style="margin-bottom:0.75rem;">
                <label class="form-label" for="verify-token-input">Receipt Reference Code</label>
                <div class="input-action-row">
                    <input id="verify-token-input" class="form-input" type="text" placeholder="For example: CLR-... or PERMIT-...">
                    <button type="button" class="btn btn-primary" data-action="verify-token">Verify Receipt</button>
                </div>
            </div>
            {res_html}
        </section>"#
    )
}

fn render_landing(s: &AppState) -> String {
    let primary_cta = if let Some(ref u) = s.user {
        let dest = default_route_for_role(&u.role);
        format!(
            r#"<button type="button" class="btn btn-primary btn-lg" data-action="nav" data-path="{dest}">Go to My Portal</button>"#
        )
    } else {
        r#"<button type="button" class="btn btn-primary btn-lg" data-action="nav" data-path="/login">Sign In to Portal</button>"#
            .to_string()
    };

    format!(
        r#"<main id="main-workspace" class="workspace-container">
            {banner}
            <div class="portal-welcome-grid">
                <section class="doc-sheet" aria-labelledby="welcome-heading">
                    <p class="institution-line">1st Semester, AY 2026–2027 &middot; Official University Notice</p>
                    <h1 id="welcome-heading" class="sheet-title" style="margin-bottom:0.65rem;">End-of-Semester Student Evaluation of Teachers</h1>
                    <hr class="section-divider">
                    <p style="font-size:1.03rem;color:var(--ink);">
                        Welcome to the School AAAAA13 Faculty Evaluation Portal. Each semester, students are invited to provide thoughtful, constructive feedback on their enrolled subjects to support teaching quality across all academic departments.
                    </p>
                    <p style="color:var(--ink-secondary);">
                        Your responses are strictly confidential. Your name and student number are never attached to your ratings or written comments, and instructors receive aggregated class summaries only after final semester grades have been submitted and verified by the Office of the University Registrar.
                    </p>
                    <div class="btn-row" style="margin:1.35rem 0;">
                        {primary_cta}
                        <button type="button" class="btn btn-secondary btn-lg" data-action="open-rubric">View 1–5 Rating Scale</button>
                    </div>
                    <hr class="section-divider">
                    <h2 style="font-size:1.18rem;margin-bottom:0.5rem;">How to Complete Your Subject Evaluations</h2>
                    <ol class="step-list">
                        <li class="step-list-item">
                            <span class="step-number" aria-hidden="true">1</span>
                            <div><strong>Sign in with your University ID.</strong> Enter your School AAAAA13 student number or university email address to open your list of enrolled subjects for 1st Semester, AY 2026–2027.</div>
                        </li>
                        <li class="step-list-item">
                            <span class="step-number" aria-hidden="true">2</span>
                            <div><strong>Complete the two-part form for each subject.</strong> Rate 25 clear teaching statements from 1 to 5 and share optional written comments in English or Filipino at your own pace.</div>
                        </li>
                        <li class="step-list-item">
                            <span class="step-number" aria-hidden="true">3</span>
                            <div><strong>Keep your completion receipt.</strong> After each submission, an official completion receipt is issued for your personal records with no link to your answers.</div>
                        </li>
                    </ol>
                </section>

                <div class="right-column-stack">
                    <section class="doc-sheet" aria-labelledby="period-heading">
                        <div class="flex-between-center" style="margin-bottom:0.55rem;">
                            <h2 id="period-heading" style="margin:0;">Evaluation Period Status</h2>
                            <span class="status-badge status-ok">Open for Submission</span>
                        </div>
                        <p style="margin-bottom:0.9rem;color:var(--ink-secondary);">
                            <strong>Active Term:</strong> 1st Semester, Academic Year 2026–2027 (Philippine Time). Students may evaluate their enrolled subjects whenever ready.
                        </p>
                        <hr class="section-divider">
                        <h3 style="margin-bottom:0.4rem;">Confidentiality &amp; Student Rights</h3>
                        <ul class="rights-list">
                            <li><strong>Protected Student Identity:</strong> Participation records and submitted evaluation answers are stored separately so no rating or comment can ever be traced back to an individual student.</li>
                            <li><strong>Post-Grade Release Only:</strong> Faculty members cannot view class ratings or student comments until final semester grades are officially submitted and closed by the Registrar.</li>
                            <li><strong>Never Blocks Examination Permits:</strong> Pending or completed evaluations never block examination permits, class attendance, or enrollment clearance at School AAAAA13.</li>
                        </ul>
                    </section>

                    {receipt_box}
                </div>
            </div>
        </main>"#,
        banner = render_banner(s),
        receipt_box = render_receipt_checker_box(s),
    )
}

fn render_login(s: &AppState) -> String {
    let pw_type = if s.show_password { "text" } else { "password" };
    let pw_btn = if s.show_password { "Hide" } else { "Show" };
    let pw_aria = if s.show_password {
        "Hide password"
    } else {
        "Show password"
    };
    format!(
        r#"<main id="main-workspace" class="workspace-container">
            <div class="centered-auth-sheet">
                <section class="doc-sheet" aria-labelledby="signin-title">
                    <p class="institution-line">School AAAAA13 &middot; Office of Academic Affairs</p>
                    <h1 id="signin-title" class="sheet-title" style="margin-bottom:0.35rem;">Sign In to Faculty Evaluation Portal</h1>
                    <p style="color:var(--ink-secondary);margin-bottom:0;">
                        1st Semester, AY 2026–2027 &middot; For students, faculty members, and academic administrators.
                    </p>
                    <hr class="section-divider">
                    {banner}
                    <div class="form-group">
                        <label class="form-label" for="login-identifier">University ID Number or Email Address</label>
                        <input id="login-identifier" class="form-input" type="text" autocomplete="username" placeholder="e.g., 2024-10892 or name@aaaaa13.edu">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="login-password">Password</label>
                        <div class="input-action-row">
                            <input id="login-password" class="form-input" type="{pw_type}" autocomplete="current-password" placeholder="Enter your password">
                            <button type="button" class="btn btn-secondary" aria-label="{pw_aria}" data-action="toggle-password">{pw_btn}</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" style="width:100%;margin-top:0.25rem;" data-action="login-submit">Sign In</button>
                    <hr class="section-divider">
                    <p style="font-size:0.88rem;color:var(--ink-secondary);margin-bottom:0;">
                        <strong>Data Privacy Notice (Republic Act No. 10173):</strong> Signing in verifies your current class enrollment or academic role only. Your identity is never attached to any evaluation rating or written comment.
                    </p>
                </section>
            </div>
        </main>"#,
        banner = render_banner(s),
    )
}

fn render_password_change(s: &AppState) -> String {
    let pw_type = if s.show_password { "text" } else { "password" };
    let pw_btn = if s.show_password { "Hide" } else { "Show" };
    format!(
        r#"<main id="main-workspace" class="workspace-container">
            <div class="centered-auth-sheet">
                <section class="doc-sheet" aria-labelledby="pw-change-title">
                    <p class="institution-line">School AAAAA13 &middot; Account Security</p>
                    <h1 id="pw-change-title" class="sheet-title" style="margin-bottom:0.4rem;">Create Your Permanent Password</h1>
                    <p style="color:var(--ink-secondary);margin-bottom:0;">
                        Because you signed in using a one-time temporary password from the Office of the University Registrar, please choose a personal password of at least 10 characters before continuing.
                    </p>
                    <hr class="section-divider">
                    {banner}
                    <div class="form-group">
                        <label class="form-label" for="pw-current">Temporary Password</label>
                        <input id="pw-current" class="form-input" type="{pw_type}" autocomplete="current-password" placeholder="Enter your temporary password">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pw-next">New Personal Password (at least 10 characters)</label>
                        <div class="input-action-row">
                            <input id="pw-next" class="form-input" type="{pw_type}" autocomplete="new-password" placeholder="Enter a new strong password">
                            <button type="button" class="btn btn-secondary" data-action="toggle-password">{pw_btn}</button>
                        </div>
                        <p class="form-hint">Do not include your student/employee ID number or email address in your new password.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" style="width:100%;" data-action="password-submit">Save New Password &amp; Continue</button>
                </section>
            </div>
        </main>"#,
        banner = render_banner(s),
    )
}

fn render_student_queue(s: &AppState) -> String {
    let Some(ref q) = s.queue_data else {
        return format!(
            r#"<main id="main-workspace" class="workspace-container">{banner}<div class="doc-sheet">Loading your enrolled subjects&hellip;</div></main>"#,
            banner = render_banner(s)
        );
    };
    let completed = q
        .get("completed_count")
        .and_then(Value::as_u64)
        .unwrap_or(0);
    let total = q.get("total_count").and_then(Value::as_u64).unwrap_or(0);
    let pending = total.saturating_sub(completed);
    let pct = q
        .get("completion_percentage")
        .and_then(Value::as_f64)
        .unwrap_or(0.0);
    let items = q
        .get("courses")
        .and_then(Value::as_array)
        .cloned()
        .unwrap_or_default();

    let all_done_banner = if total > 0 && completed == total {
        let cert_tok = q
            .get("overall_clearance_token")
            .and_then(Value::as_str)
            .unwrap_or("COMPLETED");
        format!(
            r#"<div class="alert-banner alert-success" style="margin-bottom:1.25rem;" role="status">
                <div class="flex-between-center">
                    <div>
                        <strong>All Enrolled Subjects Completed!</strong><br>
                        Thank you for completing all {total} of your subject evaluations for 1st Semester, AY 2026–2027. Semester Reference Code: <span class="ref-code">{cert_tok}</span>
                    </div>
                    <button type="button" class="btn btn-secondary no-print" data-action="print-cert">Print Summary</button>
                </div>
            </div>"#,
            cert_tok = esc(cert_tok),
        )
    } else {
        String::new()
    };

    // Sort pending subjects first while keeping original index for receipt lookup
    let mut indexed_items: Vec<(usize, &Value)> = items.iter().enumerate().collect();
    indexed_items.sort_by_key(|(_, item)| {
        if item.get("status").and_then(Value::as_str) == Some("CLEARED") {
            1_u8
        } else {
            0_u8
        }
    });

    let needle = s.queue_search.trim().to_ascii_lowercase();
    let mut rows = String::new();
    for (orig_idx, item) in indexed_items {
        let sec_id = item
            .get("course_section_id")
            .and_then(Value::as_i64)
            .unwrap_or(0);
        let code = item
            .get("course_code")
            .and_then(Value::as_str)
            .unwrap_or("");
        let title = item
            .get("course_title")
            .and_then(Value::as_str)
            .unwrap_or("");
        let sec_code = item
            .get("section_code")
            .and_then(Value::as_str)
            .unwrap_or("SEC-A");
        let prof = item
            .get("faculty_name")
            .and_then(Value::as_str)
            .unwrap_or("");
        let is_done = item.get("status").and_then(Value::as_str) == Some("CLEARED");

        if s.queue_filter == "PENDING" && is_done {
            continue;
        }
        if s.queue_filter == "CLEARED" && !is_done {
            continue;
        }
        if !needle.is_empty() {
            let hay = format!("{code} {title} {sec_code} {prof}").to_ascii_lowercase();
            if !hay.contains(&needle) {
                continue;
            }
        }

        let (status_badge, action_btn) = if is_done {
            (
                r#"<span class="status-badge status-ok">Completed</span>"#.to_string(),
                format!(
                    r#"<button type="button" class="btn btn-secondary" data-action="view-receipt" data-course-idx="{orig_idx}">View Receipt</button>"#
                ),
            )
        } else {
            (
                r#"<span class="status-badge status-pending">Pending</span>"#.to_string(),
                format!(
                    r#"<button type="button" class="btn btn-primary" data-action="start-eval" data-section-id="{sec_id}">Evaluate</button>"#
                ),
            )
        };

        rows.push_str(&format!(
            r#"<tr>
                <td data-label="Subject Code"><span class="subject-code-tag">{code}</span></td>
                <td data-label="Course &amp; Section">
                    <div>
                        <strong>{title}</strong>
                        <div style="font-size:0.88rem;color:var(--ink-secondary);">Section {sec_code}</div>
                    </div>
                </td>
                <td data-label="Instructor">{prof}</td>
                <td data-label="Status">{status_badge}</td>
                <td data-label="Action">{action_btn}</td>
            </tr>"#,
            code = esc(code),
            title = esc(title),
            sec_code = esc(sec_code),
            prof = esc(prof),
        ));
    }

    if rows.is_empty() {
        rows.push_str(
            r#"<tr><td colspan="5" style="text-align:center;color:var(--ink-secondary);padding:1.75rem;">No subjects match your selected filter.</td></tr>"#,
        );
    }

    format!(
        r#"<main id="main-workspace" class="workspace-container">
            {banner}
            <section class="doc-sheet" aria-labelledby="subjects-heading">
                {all_done_banner}
                <div class="flex-between">
                    <div>
                        <p class="institution-line">1st Semester, AY 2026–2027</p>
                        <h1 id="subjects-heading" class="sheet-title" style="margin-bottom:0.25rem;">My Enrolled Subjects</h1>
                    </div>
                    <div class="btn-row no-print">
                        <button type="button" class="btn btn-secondary" data-action="export-queue-csv">Download My List (CSV)</button>
                        <button type="button" class="btn btn-secondary" data-action="open-rubric">1–5 Rating Scale</button>
                    </div>
                </div>

                <div class="subtle-box" style="margin:1.1rem 0;">
                    <div class="flex-between-center" style="margin-bottom:0.45rem;">
                        <strong>{completed} of {total} subjects completed</strong>
                        <span class="tabular-nums" style="font-weight:700;">{pct:.0}%</span>
                    </div>
                    <div class="progress-track" role="progressbar" aria-valuenow="{pct:.0}" aria-valuemin="0" aria-valuemax="100" aria-label="Subject evaluation completion progress">
                        <div class="progress-fill" style="width:{pct:.1}%;"></div>
                    </div>
                    <p style="margin:0.55rem 0 0;font-size:0.92rem;color:var(--ink-secondary);">
                        Your name and student ID are never attached to your answers, and instructors view class summaries only after final semester grades are officially released.
                    </p>
                </div>

                <div class="flex-between-center no-print" style="margin-bottom:1rem;align-items:flex-end;">
                    <div>
                        <span class="form-label">Show Subjects</span>
                        <div class="filter-tab-row" role="tablist" aria-label="Filter subjects by completion status">
                            <button type="button" role="tab" aria-selected="{sa}" class="btn {fa}" data-action="set-queue-filter" data-filter="ALL">All Subjects ({total})</button>
                            <button type="button" role="tab" aria-selected="{sp}" class="btn {fp}" data-action="set-queue-filter" data-filter="PENDING">Pending ({pending})</button>
                            <button type="button" role="tab" aria-selected="{sd}" class="btn {fd}" data-action="set-queue-filter" data-filter="CLEARED">Completed ({completed})</button>
                        </div>
                    </div>
                    <div style="min-width:270px;flex:0 1 320px;">
                        <label class="form-label" for="queue-search-input">Find Subject or Instructor</label>
                        <input id="queue-search-input" class="form-input" type="search" data-action="queue-search" value="{q_search}" placeholder="Filter by subject or instructor...">
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="data-table" aria-label="Enrolled Subjects">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Course Title &amp; Section</th>
                                <th>Instructor</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>{rows}</tbody>
                    </table>
                </div>
            </section>
        </main>"#,
        banner = render_banner(s),
        sa = s.queue_filter == "ALL",
        fa = if s.queue_filter == "ALL" {
            "btn-primary"
        } else {
            "btn-secondary"
        },
        sp = s.queue_filter == "PENDING",
        fp = if s.queue_filter == "PENDING" {
            "btn-primary"
        } else {
            "btn-secondary"
        },
        sd = s.queue_filter == "CLEARED",
        fd = if s.queue_filter == "CLEARED" {
            "btn-primary"
        } else {
            "btn-secondary"
        },
        q_search = esc(&s.queue_search),
    )
}

fn render_evaluation_form(s: &AppState) -> String {
    let answered = s
        .draft
        .ratings
        .iter()
        .filter(|&&r| (1..=5).contains(&r))
        .count();

    let course_info = s
        .queue_data
        .as_ref()
        .and_then(|q| q.get("courses"))
        .and_then(Value::as_array)
        .and_then(|arr| {
            arr.iter().find(|c| {
                c.get("course_section_id").and_then(Value::as_i64) == Some(s.eval_section_id)
            })
        });

    let subject_heading = if let Some(ci) = course_info {
        let code = ci.get("course_code").and_then(Value::as_str).unwrap_or("");
        let title = ci.get("course_title").and_then(Value::as_str).unwrap_or("");
        format!("{} — {}", esc(code), esc(title))
    } else {
        format!("Enrolled Class Section #{}", s.eval_section_id)
    };

    let subject_meta = if let Some(ci) = course_info {
        let sec = ci
            .get("section_code")
            .and_then(Value::as_str)
            .unwrap_or("SEC-A");
        let prof = ci
            .get("faculty_name")
            .and_then(Value::as_str)
            .unwrap_or("Assigned Instructor");
        format!(
            "Section {} &middot; Instructor: <strong>{}</strong>",
            esc(sec),
            esc(prof)
        )
    } else {
        "1st Semester, AY 2026–2027".to_string()
    };

    let step_label = if s.eval_step == 1 {
        "Part 1 of 2: Rating Statements"
    } else {
        "Part 2 of 2: Written Comments"
    };

    let header_sheet = format!(
        r#"<section class="doc-sheet" aria-labelledby="eval-subject-title">
            <div class="flex-between-center no-print" style="margin-bottom:0.85rem;">
                <button type="button" class="btn btn-secondary" data-action="nav" data-path="/queue">&larr; Back to My Subjects</button>
                <div class="btn-row">
                    <button type="button" class="btn btn-secondary" data-action="open-rubric">1–5 Rating Scale</button>
                    <button type="button" class="btn btn-secondary" data-action="ask-discard-draft">Clear Saved Answers</button>
                </div>
            </div>
            <h1 id="eval-subject-title" class="sheet-title" style="margin-bottom:0.25rem;">{subject_heading}</h1>
            <p style="color:var(--ink-secondary);margin-bottom:0.9rem;">{subject_meta}</p>
            <div class="subtle-box flex-between-center">
                <strong>{step_label}</strong>
                <span class="tabular-nums"><strong>Answered: {answered} of 25</strong></span>
            </div>
        </section>"#
    );

    let body = if s.eval_step == 1 {
        let mut jump_btns = String::new();
        let mut sections_html = String::new();

        for (c_idx, cluster) in FIVE_CLUSTERS.iter().enumerate() {
            let (letter, plain_title, helper_desc) = SECTION_TITLES[c_idx];
            let short_jump = SECTION_JUMP_SHORT[c_idx];
            let c_done = s.draft.ratings[c_idx * 5..(c_idx + 1) * 5]
                .iter()
                .filter(|&&r| (1..=5).contains(&r))
                .count();
            let jump_cls = if c_done == 5 {
                "section-jump-btn done"
            } else {
                "section-jump-btn"
            };
            jump_btns.push_str(&format!(
                r#"<button type="button" class="{jump_cls}" data-action="jump-cluster" data-cluster="{c_idx}">{letter}. {short_jump} ({c_done}/5)</button>"#
            ));

            let mut rows_html = String::new();
            for (i_idx, prompt) in cluster.items.iter().enumerate() {
                let flat_idx = c_idx * 5 + i_idx;
                let q_num = flat_idx + 1;
                let current = s.draft.ratings[flat_idx];
                let is_missing = s.incomplete_indices.contains(&flat_idx);
                let row_cls = if is_missing {
                    "statement-row incomplete-highlight"
                } else {
                    "statement-row"
                };
                let missing_note = if is_missing {
                    r#"<div style="font-size:0.88rem;font-weight:600;color:var(--err-fg);margin-top:0.25rem;">Please select a rating from 1 to 5 for this statement.</div>"#
                } else {
                    ""
                };

                let mut btns = String::new();
                for val in 1..=5 {
                    let sel_cls = if current == val {
                        "rating-btn selected"
                    } else {
                        "rating-btn"
                    };
                    let word = rating_scale_word(val);
                    btns.push_str(&format!(
                        r#"<button type="button" class="{sel_cls}" aria-pressed="{pressed}" aria-label="Statement {q_num}: {val} ({word})" data-action="set-rating" data-idx="{flat_idx}" data-val="{val}">{val}</button>"#,
                        pressed = current == val,
                    ));
                }

                rows_html.push_str(&format!(
                    r#"<div id="likert-item-{flat_idx}" class="{row_cls}" tabindex="0" data-likert-row="{flat_idx}" role="group" aria-label="Statement {q_num}">
                        <div>
                            <span><strong>{q_num}.</strong> {prompt}</span>
                            {missing_note}
                        </div>
                        <div class="rating-btn-group">{btns}</div>
                    </div>"#,
                    prompt = esc(prompt),
                ));
            }

            sections_html.push_str(&format!(
                r#"<section id="cluster-card-{c_idx}" class="doc-sheet" aria-labelledby="sec-head-{c_idx}">
                    <div class="flex-between-center">
                        <h2 id="sec-head-{c_idx}" style="margin:0;">{letter}. {plain_title}</h2>
                        <span style="font-size:0.92rem;color:var(--ink-secondary);" class="tabular-nums">{c_done} of 5 answered</span>
                    </div>
                    <p style="color:var(--ink-secondary);font-size:0.94rem;margin:0.3rem 0 0;">{helper_desc}</p>
                    <hr class="section-divider">
                    {rows_html}
                </section>"#
            ));
        }

        format!(
            r#"<div class="scale-legend-sheet no-print" role="region" aria-label="1 to 5 Rating Scale Legend and Section Navigation">
                <div class="scale-legend-items">
                    <span><strong>Rating Scale:</strong></span>
                    <span><strong>5</strong> = Outstanding</span>
                    <span><strong>4</strong> = Very Satisfactory</span>
                    <span><strong>3</strong> = Satisfactory</span>
                    <span><strong>2</strong> = Fair</span>
                    <span><strong>1</strong> = Needs Improvement</span>
                </div>
                <nav class="section-jump-row" aria-label="Jump to Evaluation Section">
                    {jump_btns}
                </nav>
            </div>
            {sections_html}
            <div class="doc-sheet flex-between-center">
                <span><strong>Answered: {answered} of 25</strong> rating statements</span>
                <button type="button" class="btn btn-primary btn-lg" data-action="step-next">Continue to Part 2: Written Comments &rarr;</button>
            </div>"#
        )
    } else {
        format!(
            r#"<section class="doc-sheet" aria-labelledby="part2-heading">
                <h2 id="part2-heading" style="margin-bottom:0.35rem;">Part 2 of 2: Written Comments</h2>
                <p style="color:var(--ink-secondary);">
                    Share constructive observations to help your instructor understand what worked well and what can be improved. Written responses below are optional, and you may write in English or Filipino.
                </p>
                <hr class="section-divider">

                <div class="form-group">
                    <label class="form-label" for="eval-q1">1. Three words describing the class</label>
                    <input id="eval-q1" class="form-input" type="text" data-action="draft-text-input" value="{q1}" placeholder="For example: Clear, organized, supportive">
                </div>

                <div class="form-group">
                    <label class="form-label" for="eval-q2">2. Areas for improvement</label>
                    <input id="eval-q2" class="form-input" type="text" data-action="draft-text-input" value="{q2}" placeholder="Topics, pacing, or class activities that could be improved (separate multiple items with semicolons)">
                </div>

                <div class="form-group">
                    <label class="form-label" for="eval-q3">3. Teaching strengths to continue</label>
                    <input id="eval-q3" class="form-input" type="text" data-action="draft-text-input" value="{q3}" placeholder="Teaching approaches, examples, or guidance that helped you learn effectively">
                </div>

                <div class="form-group">
                    <label class="form-label" for="eval-q4">4. Suggestions for future offerings</label>
                    <input id="eval-q4" class="form-input" type="text" data-action="draft-text-input" value="{q4}" placeholder="Helpful recommendations for future offerings of this subject">
                </div>

                <div class="form-group">
                    <label class="form-label" for="eval-comment">5. Additional comments (in English or Filipino)</label>
                    <textarea id="eval-comment" class="form-textarea" data-action="draft-text-input" placeholder="Write any additional constructive comments about your learning experience in this subject...">{comment}</textarea>
                    <p class="form-hint" style="margin-top:0.5rem;">
                        <strong>Student Privacy Assurance:</strong> Your name and student number are never attached to this evaluation. Any email addresses, student ID numbers, or phone numbers accidentally included in written comments are automatically removed before saving.
                    </p>
                </div>

                <hr class="section-divider">
                <div class="flex-between-center">
                    <button type="button" class="btn btn-secondary btn-lg" data-action="step-back">&larr; Back to Part 1: Rating Statements</button>
                    <button type="button" class="btn btn-primary btn-lg" {disabled} data-action="eval-submit">{sub_label}</button>
                </div>
            </section>"#,
            q1 = esc(&s.draft.three_words),
            q2 = esc(&s.draft.improvements),
            q3 = esc(&s.draft.strengths),
            q4 = esc(&s.draft.recommendations),
            comment = esc(&s.draft.comment_text),
            disabled = if s.submitting { "disabled" } else { "" },
            sub_label = if s.submitting {
                "Submitting Evaluation..."
            } else {
                "Submit Evaluation"
            },
        )
    };

    let sl_modal = if s.show_straight_line_modal {
        r#"<div class="modal-backdrop no-print" role="dialog" aria-modal="true" aria-labelledby="sl-modal-title">
            <div class="modal-dialog">
                <h2 id="sl-modal-title" style="margin-bottom:0.5rem;">Confirm Identical Ratings?</h2>
                <p style="color:var(--ink-secondary);">
                    You selected the same rating for all 25 statements across all five sections. If this reflects your honest overall assessment of the class, you may submit your evaluation now, or return to Part 1 to review individual statements.
                </p>
                <div class="btn-row" style="margin-top:1.25rem;">
                    <button type="button" class="btn btn-secondary" data-action="cancel-straight-line">Review My Ratings</button>
                    <button type="button" class="btn btn-primary" data-action="confirm-straight-line">Submit My Evaluation</button>
                </div>
            </div>
        </div>"#
    } else {
        ""
    };

    let discard_modal = if s.show_discard_modal {
        r#"<div class="modal-backdrop no-print" role="dialog" aria-modal="true" aria-labelledby="disc-title">
            <div class="modal-dialog">
                <h2 id="disc-title" style="margin-bottom:0.5rem;">Clear Saved Answers?</h2>
                <p style="color:var(--ink-secondary);">
                    This will clear all ratings and written notes you have entered so far for this subject so you can start fresh.
                </p>
                <div class="btn-row" style="margin-top:1.25rem;">
                    <button type="button" class="btn btn-secondary" data-action="close-modals">Keep My Answers</button>
                    <button type="button" class="btn btn-primary" data-action="confirm-discard-draft">Clear Answers</button>
                </div>
            </div>
        </div>"#
    } else {
        ""
    };

    format!(
        r#"<main id="main-workspace" class="workspace-container">
            {banner}
            {header_sheet}
            {body}
            {sl_modal}
            {discard_modal}
        </main>"#,
        banner = render_banner(s),
    )
}

fn render_faculty_portal(s: &AppState) -> String {
    let secs = s
        .faculty_sections
        .as_ref()
        .and_then(|v| v.get("sections"))
        .and_then(Value::as_array)
        .cloned()
        .unwrap_or_default();

    if secs.is_empty() {
        let loading_or_empty = if s.faculty_sections.is_none() {
            "Loading your assigned class sections&hellip;"
        } else {
            "No assigned class sections were found for 1st Semester, AY 2026–2027."
        };
        return format!(
            r#"<main id="main-workspace" class="workspace-container">
                {banner}
                <section class="doc-sheet">
                    <p class="institution-line">1st Semester, AY 2026–2027 &middot; Faculty Portal</p>
                    <h1 class="sheet-title">My Class Evaluation Results</h1>
                    <p style="color:var(--ink-secondary);margin-bottom:0;">{loading_or_empty}</p>
                </section>
            </main>"#,
            banner = render_banner(s),
        );
    }

    let active_sec = secs
        .iter()
        .find(|it| it.get("id").and_then(Value::as_i64) == Some(s.selected_faculty_sec_id))
        .unwrap_or(&secs[0]);

    let active_id = active_sec.get("id").and_then(Value::as_i64).unwrap_or(0);
    let active_code = active_sec
        .get("course_code")
        .and_then(Value::as_str)
        .unwrap_or("");
    let active_title = active_sec
        .get("course_title")
        .and_then(Value::as_str)
        .unwrap_or("");
    let active_scode = active_sec
        .get("section_code")
        .and_then(Value::as_str)
        .unwrap_or("");
    let grades_done = active_sec
        .get("term_grades_submitted")
        .and_then(Value::as_bool)
        .unwrap_or(false);
    let reg_released = active_sec
        .get("registrar_lock")
        .and_then(Value::as_bool)
        .unwrap_or(false);
    let is_released = grades_done && reg_released;

    let mut select_opts = String::new();
    for sec in &secs {
        let sid = sec.get("id").and_then(Value::as_i64).unwrap_or(0);
        let code = sec.get("course_code").and_then(Value::as_str).unwrap_or("");
        let title = sec
            .get("course_title")
            .and_then(Value::as_str)
            .unwrap_or("");
        let scode = sec
            .get("section_code")
            .and_then(Value::as_str)
            .unwrap_or("");
        let g = sec
            .get("term_grades_submitted")
            .and_then(Value::as_bool)
            .unwrap_or(false);
        let l = sec
            .get("registrar_lock")
            .and_then(Value::as_bool)
            .unwrap_or(false);
        let st_txt = if g && l {
            "Released"
        } else {
            "Pending Release"
        };
        select_opts.push_str(&format!(
            r#"<option value="{sid}" {sel}>{code} ({scode}) [{st_txt}] — {title}</option>"#,
            sel = if sid == active_id { "selected" } else { "" },
            code = esc(code),
            scode = esc(scode),
            title = esc(title),
        ));
    }

    let detail_sheet = if !is_released {
        let step1_status = if grades_done {
            r#"<span class="status-badge status-ok">Completed</span>"#.to_string()
        } else {
            format!(
                r#"<div class="btn-row">
                    <span class="status-badge status-pending">Pending</span>
                    <button type="button" class="btn btn-primary" data-action="faculty-submit-grades" data-section-id="{active_id}">Confirm Final Grades Submitted</button>
                </div>"#
            )
        };
        let step2_status = if reg_released {
            r#"<span class="status-badge status-ok">Verified by Registrar</span>"#
        } else {
            r#"<span class="status-badge status-pending">Awaiting Registrar Verification</span>"#
        };

        format!(
            r#"<section class="doc-sheet" aria-labelledby="pending-release-heading">
                <h2 id="pending-release-heading" style="margin-bottom:0.35rem;">Results Pending Final Grade Release</h2>
                <p style="color:var(--ink-secondary);">
                    <strong>{code} (Section {scode}) &mdash; {title}</strong><br>
                    In accordance with university policy, class evaluation summaries and anonymous student comments are released only after both steps below are completed.
                </p>
                <div class="checklist-box">
                    <div class="checklist-step">
                        <div>
                            <strong>Step 1: Final Semester Grades Submitted by Instructor</strong>
                            <div style="font-size:0.9rem;color:var(--ink-secondary);">Confirm that all final student grades for this class section have been submitted.</div>
                        </div>
                        {step1_status}
                    </div>
                    <div class="checklist-step">
                        <div>
                            <strong>Step 2: Grade Release Verified by the Office of the University Registrar</strong>
                            <div style="font-size:0.9rem;color:var(--ink-secondary);">The University Registrar verifies grade submission and unlocks the class report.</div>
                        </div>
                        {step2_status}
                    </div>
                </div>
            </section>"#,
            code = esc(active_code),
            scode = esc(active_scode),
            title = esc(active_title),
        )
    } else if let Some(ref rep) = s.selected_faculty_report {
        let mean = rep.get("mean_score").and_then(Value::as_f64).unwrap_or(0.0);
        let adj = perf_adjective(mean);
        let count = rep
            .get("submission_count")
            .and_then(Value::as_u64)
            .unwrap_or(0);
        let pos = rep["sentiment_summary"]["positive_pct"]
            .as_f64()
            .unwrap_or(0.0);
        let neu = rep["sentiment_summary"]["neutral_pct"]
            .as_f64()
            .unwrap_or(0.0);
        let neg = rep["sentiment_summary"]["negative_pct"]
            .as_f64()
            .unwrap_or(0.0);

        let mut area_rows = String::new();
        for tag in PEDAGOGICAL_TAGS {
            let cm = rep["cluster_means"]
                .get(tag)
                .and_then(Value::as_f64)
                .unwrap_or(mean);
            area_rows.push_str(&format!(
                r#"<tr>
                    <td data-label="Evaluation Area"><strong>{}</strong></td>
                    <td data-label="Average Rating" class="tabular-nums">{cm:.2} / 5.00</td>
                    <td data-label="Descriptive Rating">{}</td>
                </tr>"#,
                esc(friendly_area_name(tag)),
                perf_adjective(cm),
            ));
        }

        let mut comments_html = String::new();
        if let Some(arr) = rep.get("anonymized_comments").and_then(Value::as_array) {
            for c in arr {
                let txt = c.get("comment_text").and_then(Value::as_str).unwrap_or("");
                if txt.trim().is_empty() {
                    continue;
                }
                let val = c
                    .get("valence")
                    .and_then(Value::as_str)
                    .unwrap_or("Neutral");
                let tag = c
                    .get("pedagogical_tag")
                    .and_then(Value::as_str)
                    .unwrap_or("");
                let (tone_cls, tone_lbl) = match val {
                    "Positive" => ("status-badge status-ok", "Positive"),
                    "Negative" => ("status-badge status-concern", "Concern"),
                    _ => ("status-badge status-neutral", "Neutral"),
                };
                comments_html.push_str(&format!(
                    r#"<div class="subtle-box" style="margin-top:0.7rem;">
                        <div class="flex-between-center" style="margin-bottom:0.35rem;">
                            <strong style="font-size:0.93rem;">{}</strong>
                            <span class="{tone_cls}">{tone_lbl}</span>
                        </div>
                        <p style="margin:0;">{}</p>
                    </div>"#,
                    esc(friendly_area_name(tag)),
                    esc(txt),
                ));
            }
        }
        if comments_html.is_empty() {
            comments_html.push_str(
                r#"<p style="color:var(--ink-secondary);margin-top:0.5rem;">No written comments were submitted for this class section.</p>"#,
            );
        }

        format!(
            r#"<section class="doc-sheet" aria-labelledby="official-report-heading">
                <div class="flex-between">
                    <div>
                        <p class="institution-line">Official Class Evaluation Report &middot; 1st Semester, AY 2026–2027</p>
                        <h2 id="official-report-heading" style="margin-bottom:0.2rem;">{code} (Section {scode}) &mdash; {title}</h2>
                    </div>
                    <div class="btn-row no-print">
                        <button type="button" class="btn btn-secondary" data-action="export-faculty-csv">Download Summary (CSV)</button>
                        <button type="button" class="btn btn-secondary" data-action="print-cert">Print Report</button>
                    </div>
                </div>
                <hr class="section-divider">

                <div class="kpi-grid-3" style="margin-bottom:1.35rem;">
                    <div class="kpi-card">
                        <div class="kpi-label">Overall Rating</div>
                        <div class="kpi-value">{mean:.2} / 5.00</div>
                        <div class="kpi-sub">{adj}</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Student Responses</div>
                        <div class="kpi-value">{count}</div>
                        <div class="kpi-sub">Completed anonymous evaluations</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Positive Feedback</div>
                        <div class="kpi-value">{pos:.1}%</div>
                        <div class="kpi-sub">Neutral: {neu:.1}% &middot; Concerns: {neg:.1}%</div>
                    </div>
                </div>

                <h3 style="margin-bottom:0.6rem;">Ratings Across the 5 Evaluation Areas</h3>
                <div class="table-wrap" style="margin-bottom:1.4rem;">
                    <table class="data-table" aria-label="Ratings by Evaluation Area">
                        <thead>
                            <tr>
                                <th>Evaluation Area</th>
                                <th>Average Rating</th>
                                <th>Descriptive Rating</th>
                            </tr>
                        </thead>
                        <tbody>{area_rows}</tbody>
                    </table>
                </div>

                <h3 style="margin-bottom:0.35rem;">Anonymous Student Comments</h3>
                <p style="font-size:0.9rem;color:var(--ink-secondary);margin-bottom:0.5rem;">
                    Comments are presented in random/date order with all personal identifiers removed.
                </p>
                {comments_html}
            </section>"#,
            code = esc(active_code),
            scode = esc(active_scode),
            title = esc(active_title),
            adj = esc(adj),
        )
    } else {
        format!(
            r#"<section class="doc-sheet">
                <div class="flex-between-center">
                    <span>Loading official class evaluation report for <strong>{} (Section {})</strong>&hellip;</span>
                    <button type="button" class="btn btn-primary" data-action="faculty-view-report" data-section-id="{active_id}">Open Class Report</button>
                </div>
            </section>"#,
            esc(active_code),
            esc(active_scode),
        )
    };

    format!(
        r#"<main id="main-workspace" class="workspace-container">
            {banner}
            <section class="doc-sheet" aria-labelledby="fac-portal-title">
                <p class="institution-line">1st Semester, AY 2026–2027 &middot; Office of Academic Affairs</p>
                <h1 id="fac-portal-title" class="sheet-title" style="margin-bottom:0.35rem;">My Class Evaluation Results</h1>
                <p style="color:var(--ink-secondary);">
                    Select an assigned class section below to check grade verification status or review your official anonymous class evaluation summary.
                </p>
                <div class="form-group" style="margin-bottom:0;max-width:100%;">
                    <label class="form-label" for="faculty-section-select">Assigned Class Section</label>
                    <select id="faculty-section-select" class="form-select" data-action="faculty-select-section">
                        {select_opts}
                    </select>
                </div>
            </section>
            {detail_sheet}
        </main>"#,
        banner = render_banner(s),
    )
}

fn render_dean_or_chair(s: &AppState) -> String {
    let is_chair = s.route.starts_with("/chair");
    let scope_name = if is_chair {
        s.user
            .as_ref()
            .and_then(|u| u.department_name.as_deref())
            .filter(|n| !n.is_empty())
            .unwrap_or("Assigned Academic Department")
    } else {
        s.user
            .as_ref()
            .and_then(|u| u.college_name.as_deref())
            .filter(|n| !n.is_empty())
            .unwrap_or("Assigned Academic College")
    };
    let page_heading = if is_chair {
        format!("Department Evaluation Summary — {}", esc(scope_name))
    } else {
        format!("College Evaluation Summary — {}", esc(scope_name))
    };

    let ov = s
        .dean_overview
        .as_ref()
        .cloned()
        .unwrap_or_else(|| json!({}));
    let resp_rate = ov
        .get("response_rate_pct")
        .and_then(Value::as_f64)
        .unwrap_or(0.0);
    let total_subs = ov
        .get("total_vault_submissions")
        .and_then(Value::as_u64)
        .unwrap_or(0);
    let mean_score = ov
        .get("overall_mean_score")
        .and_then(Value::as_f64)
        .unwrap_or(0.0);
    let pos_pct = ov["sentiment"]["positive_pct"].as_f64().unwrap_or(0.0);
    let neu_pct = ov["sentiment"]["neutral_pct"].as_f64().unwrap_or(0.0);
    let neg_pct = ov["sentiment"]["negative_pct"].as_f64().unwrap_or(0.0);

    let dossier_html = if let Some(ref d) = s.compiled_dossier {
        let ref_id = d.get("dossier_id").and_then(Value::as_str).unwrap_or("");
        let compiled_at = d
            .get("compiled_at")
            .and_then(Value::as_str)
            .unwrap_or("1st Semester, AY 2026–2027");
        format!(
            r#"<section class="doc-sheet" role="status" aria-label="Official Accreditation Summary">
                <div class="flex-between-center" style="flex-wrap:nowrap;">
                    <div style="flex:1 1 0;min-width:0;">
                        <h2 style="margin:0 0 0.25rem;">AACCUP Level III Area III — Curriculum &amp; Instruction Summary</h2>
                        <p style="margin:0;color:var(--ink-secondary);font-size:0.94rem;">
                            Reference Number: <span class="ref-code">{ref_id}</span> &middot; Prepared: <span class="tabular-nums">{compiled_at}</span> &middot; Student Confidentiality Verified
                        </p>
                    </div>
                    <div class="btn-row no-print" style="flex-shrink:0;">
                        <span class="status-badge status-ok">Prepared</span>
                        <button type="button" class="btn btn-secondary" data-action="print-cert">Print Summary</button>
                    </div>
                </div>
            </section>"#,
            ref_id = esc(ref_id),
            compiled_at = esc(compiled_at),
        )
    } else {
        String::new()
    };

    let tab_content = match s.dean_tab.as_str() {
        "support" => {
            let watchlist_items = s
                .dean_watchlist
                .as_ref()
                .and_then(|w| w.get("watchlist"))
                .and_then(Value::as_array)
                .cloned()
                .unwrap_or_default();
            let mut wl_rows = String::new();
            if watchlist_items.is_empty() {
                wl_rows.push_str(
                    r#"<tr><td colspan="5" style="text-align:center;color:var(--ink-secondary);padding:1.75rem;">All instructors in your academic unit currently meet satisfactory teaching benchmarks.</td></tr>"#,
                );
            } else {
                for item in &watchlist_items {
                    let fid = item.get("faculty_id").and_then(Value::as_i64).unwrap_or(0);
                    let fname = item
                        .get("faculty_name")
                        .and_then(Value::as_str)
                        .unwrap_or("Instructor");
                    let m = item
                        .get("mean_score")
                        .and_then(Value::as_f64)
                        .unwrap_or(0.0);
                    let neg = item
                        .get("negative_pct")
                        .and_then(Value::as_f64)
                        .unwrap_or(0.0);
                    let ptag = item
                        .get("primary_tag")
                        .and_then(Value::as_str)
                        .unwrap_or("Pedagogical Delivery");
                    let plan_id = item["action_plan"]["plan_id"]
                        .as_str()
                        .unwrap_or("AAAAA13-ACT-DEFAULT");
                    let recommendation = item["action_plan"]["recommended_interventions"]
                        .as_array()
                        .and_then(|a| a.first())
                        .and_then(Value::as_str)
                        .unwrap_or(
                            "Schedule collegial syllabus, pacing, and rubric review consultation.",
                        );

                    wl_rows.push_str(&format!(
                        r#"<tr>
                            <td data-label="Instructor"><strong>{fname}</strong></td>
                            <td data-label="Average Rating" class="tabular-nums">{m:.2} / 5.00 ({adj})</td>
                            <td data-label="Concerns Share" class="tabular-nums">{neg:.1}%</td>
                            <td data-label="Suggested Focus">
                                <div>
                                    <strong>{area}</strong>
                                    <div style="font-size:0.88rem;color:var(--ink-secondary);margin-top:0.2rem;">{rec}</div>
                                </div>
                            </td>
                            <td data-label="Support Action">
                                <div class="btn-row">
                                    <button type="button" class="btn btn-secondary" data-action="signoff-plan" data-fac-id="{fid}" data-plan-id="{plan_id}" data-status="APPROVED">Approve Support Plan</button>
                                    <button type="button" class="btn btn-primary" data-action="signoff-plan" data-fac-id="{fid}" data-plan-id="{plan_id}" data-status="RESOLVED">Mark Completed</button>
                                </div>
                            </td>
                        </tr>"#,
                        fname = esc(fname),
                        adj = perf_adjective(m),
                        area = esc(friendly_area_name(ptag)),
                        rec = esc(recommendation),
                        plan_id = esc(plan_id),
                    ));
                }
            }
            format!(
                r#"<section class="doc-sheet" aria-labelledby="support-heading">
                    <h2 id="support-heading" style="margin-bottom:0.35rem;">Instructors Needing Support</h2>
                    <p style="color:var(--ink-secondary);">
                        Lists instructors for collegial mentoring and instructional support when their overall class average is below 3.50 out of 5.00 or when more than 35% of written comments raise student concerns.
                    </p>
                    <div class="table-wrap">
                        <table class="data-table" aria-label="Instructors Needing Support">
                            <thead>
                                <tr>
                                    <th>Instructor</th>
                                    <th>Average Rating</th>
                                    <th>Concerns Share</th>
                                    <th>Suggested Mentoring Focus</th>
                                    <th>Support Plan Action</th>
                                </tr>
                            </thead>
                            <tbody>{wl_rows}</tbody>
                        </table>
                    </div>
                </section>"#
            )
        }
        "participation" => {
            let hm_items = s
                .dean_heatmap
                .as_ref()
                .and_then(|h| h.get("sections"))
                .and_then(Value::as_array)
                .cloned()
                .unwrap_or_default();
            let mut hm_rows = String::new();
            if hm_items.is_empty() {
                hm_rows.push_str(
                    r#"<tr><td colspan="6" style="text-align:center;color:var(--ink-secondary);padding:1.75rem;">No class sections found in your academic unit.</td></tr>"#,
                );
            } else {
                for sec in &hm_items {
                    let code = sec.get("course_code").and_then(Value::as_str).unwrap_or("");
                    let title = sec
                        .get("course_title")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let scode = sec
                        .get("section_code")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let fname = sec
                        .get("faculty_name")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let enr = sec
                        .get("enrolled_count")
                        .and_then(Value::as_u64)
                        .unwrap_or(0);
                    let clr = sec
                        .get("cleared_count")
                        .and_then(Value::as_u64)
                        .unwrap_or(0);
                    let rate = sec
                        .get("completion_pct")
                        .and_then(Value::as_f64)
                        .unwrap_or(0.0);
                    hm_rows.push_str(&format!(
                        r#"<tr>
                            <td data-label="Subject &amp; Section"><span><span class="subject-code-tag">{code}</span> <span style="font-size:0.9rem;color:var(--ink-secondary);">({scode})</span></span></td>
                            <td data-label="Course Title"><strong>{title}</strong></td>
                            <td data-label="Instructor">{fname}</td>
                            <td data-label="Enrolled" class="tabular-nums">{enr}</td>
                            <td data-label="Completed" class="tabular-nums">{clr}</td>
                            <td data-label="Completion Rate">
                                <div class="Inline-progress-cell">
                                    <div class="progress-track"><div class="progress-fill" style="width:{rate:.1}%;"></div></div>
                                    <span class="tabular-nums" style="font-weight:600;">{rate:.1}%</span>
                                </div>
                            </td>
                        </tr>"#,
                        code = esc(code),
                        scode = esc(scode),
                        title = esc(title),
                        fname = esc(fname),
                    ));
                }
            }
            let pending_students = s
                .dean_participation
                .as_ref()
                .and_then(|p| p.get("pending_students"))
                .and_then(Value::as_array)
                .cloned()
                .unwrap_or_default();
            let mut pending_rows = String::new();
            if pending_students.is_empty() {
                pending_rows.push_str(
                    r#"<tr><td colspan="5" style="text-align:center;color:var(--ink-secondary);padding:1.5rem;">All enrolled students in your academic unit have completed their subject evaluations.</td></tr>"#,
                );
            } else {
                for st in &pending_students {
                    let iid = st
                        .get("institutional_id")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let sname = st.get("student_name").and_then(Value::as_str).unwrap_or("");
                    let email = st.get("email").and_then(Value::as_str).unwrap_or("");
                    let comp = st
                        .get("completed_count")
                        .and_then(Value::as_u64)
                        .unwrap_or(0);
                    let enr = st
                        .get("enrolled_count")
                        .and_then(Value::as_u64)
                        .unwrap_or(0);
                    let codes = st
                        .get("pending_courses")
                        .and_then(Value::as_array)
                        .map(|arr| {
                            arr.iter()
                                .filter_map(Value::as_str)
                                .collect::<Vec<_>>()
                                .join(", ")
                        })
                        .unwrap_or_default();
                    pending_rows.push_str(&format!(
                        r#"<tr>
                            <td data-label="Student ID" class="tabular-nums"><strong>{iid}</strong></td>
                            <td data-label="Student Name">{sname}</td>
                            <td data-label="University Email">{email}</td>
                            <td data-label="Progress" class="tabular-nums">{comp} of {enr} completed</td>
                            <td data-label="Pending Subjects"><span class="status-badge status-pending">{codes}</span></td>
                        </tr>"#,
                        iid = esc(iid),
                        sname = esc(sname),
                        email = esc(email),
                        codes = esc(&codes),
                    ));
                }
            }

            format!(
                r#"<section class="doc-sheet" aria-labelledby="part-heading">
                    <h2 id="part-heading" style="margin-bottom:0.35rem;">Class Participation by Section</h2>
                    <p style="color:var(--ink-secondary);">
                        Shows the number of enrolled students and completed evaluations per class section. Participation tracking never links student identities to ratings and never restricts examination permits.
                    </p>
                    <div class="table-wrap">
                        <table class="data-table" aria-label="Class Participation by Section">
                            <thead>
                                <tr>
                                    <th>Subject &amp; Section</th>
                                    <th>Course Title</th>
                                    <th>Instructor</th>
                                    <th>Enrolled</th>
                                    <th>Completed</th>
                                    <th>Completion Rate</th>
                                </tr>
                            </thead>
                            <tbody>{hm_rows}</tbody>
                        </table>
                    </div>
                </section>
                <section class="doc-sheet" aria-labelledby="pending-followup-heading">
                    <div class="flex-between">
                        <div>
                            <h2 id="pending-followup-heading" style="margin-bottom:0.35rem;">Students with Pending Subject Evaluations</h2>
                            <p style="color:var(--ink-secondary);max-width:65ch;">
                                Lists students with incomplete evaluations for polite academic follow-up only. Completion status is recorded separately from anonymous rating sheets.
                            </p>
                        </div>
                        <div class="btn-row no-print">
                            <button type="button" class="btn btn-secondary" data-action="export-pending-students-csv">Download Follow-Up List (CSV)</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table" aria-label="Students with Pending Evaluations">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>University Email</th>
                                    <th>Progress</th>
                                    <th>Pending Subjects</th>
                                </tr>
                            </thead>
                            <tbody>{pending_rows}</tbody>
                        </table>
                    </div>
                </section>"#
            )
        }
        "comments" => {
            let ins_items = s
                .dean_insights
                .as_ref()
                .and_then(|v| v.get("results"))
                .and_then(Value::as_array)
                .cloned()
                .unwrap_or_default();
            let mut comment_rows = String::new();
            if ins_items.is_empty() {
                comment_rows.push_str(
                    r#"<tr><td colspan="4" style="text-align:center;color:var(--ink-secondary);padding:1.75rem;">No student comments match your current search or tone filter.</td></tr>"#,
                );
            } else {
                for r in &ins_items {
                    let code = r.get("course_code").and_then(Value::as_str).unwrap_or("");
                    let tag = r
                        .get("pedagogical_tag")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let val = r
                        .get("valence")
                        .and_then(Value::as_str)
                        .unwrap_or("Neutral");
                    let txt = r.get("comment_text").and_then(Value::as_str).unwrap_or("");
                    let (tone_cls, tone_lbl) = match val {
                        "Positive" => ("status-badge status-ok", "Positive"),
                        "Negative" => ("status-badge status-concern", "Concerns"),
                        _ => ("status-badge status-neutral", "Neutral"),
                    };
                    comment_rows.push_str(&format!(
                        r#"<tr>
                            <td data-label="Subject Code"><span class="subject-code-tag">{code}</span></td>
                            <td data-label="Evaluation Area">{area}</td>
                            <td data-label="Tone"><span class="{tone_cls}">{tone_lbl}</span></td>
                            <td data-label="Comment">{txt}</td>
                        </tr>"#,
                        code = esc(code),
                        area = esc(friendly_area_name(tag)),
                        txt = esc(txt),
                    ));
                }
            }

            format!(
                r#"<section class="doc-sheet" aria-labelledby="comments-heading">
                    <h2 id="comments-heading" style="margin-bottom:0.35rem;">Anonymous Student Comments</h2>
                    <p style="color:var(--ink-secondary);">
                        Search and review anonymous student feedback across classes in your academic unit. Student names, ID numbers, email addresses, and phone numbers are never stored.
                    </p>
                    <div class="filter-toolbar no-print" style="margin-bottom:1.15rem;">
                        <div>
                            <label class="form-label" for="insights-q-input">Keyword Search</label>
                            <input id="insights-q-input" class="form-input" type="search" value="{iq}" placeholder="Search comments by topic (e.g., grading, examples, pacing)...">
                        </div>
                        <div>
                            <label class="form-label" for="insights-val-select">Feedback Tone</label>
                            <select id="insights-val-select" class="form-select" data-action="insights-valence-change">
                                <option value="" {va}>All</option>
                                <option value="Positive" {vp}>Positive</option>
                                <option value="Neutral" {vn}>Neutral</option>
                                <option value="Negative" {vg}>Concerns</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary btn-lg" data-action="search-insights">Filter Comments</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table" aria-label="Anonymous Student Comments">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Evaluation Area</th>
                                    <th>Tone</th>
                                    <th>Comment</th>
                                </tr>
                            </thead>
                            <tbody>{comment_rows}</tbody>
                        </table>
                    </div>
                </section>"#,
                iq = esc(&s.insights_query),
                va = if s.insights_valence.is_empty() {
                    "selected"
                } else {
                    ""
                },
                vp = if s.insights_valence == "Positive" {
                    "selected"
                } else {
                    ""
                },
                vn = if s.insights_valence == "Neutral" {
                    "selected"
                } else {
                    ""
                },
                vg = if s.insights_valence == "Negative" {
                    "selected"
                } else {
                    ""
                },
            )
        }
        _ => {
            let summaries = ov["instructor_summaries"]
                .as_array()
                .cloned()
                .unwrap_or_default();
            let mut sum_rows = String::new();
            if summaries.is_empty() {
                sum_rows.push_str(
                    r#"<tr><td colspan="6" style="text-align:center;color:var(--ink-secondary);padding:1.75rem;">No faculty evaluation summaries recorded yet for this semester.</td></tr>"#,
                );
            } else {
                for inst in &summaries {
                    let fname = inst
                        .get("faculty_name")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let n = inst
                        .get("submission_count")
                        .and_then(Value::as_u64)
                        .unwrap_or(0);
                    let m = inst
                        .get("mean_score")
                        .and_then(Value::as_f64)
                        .unwrap_or(0.0);
                    let neg = inst
                        .get("negative_pct")
                        .and_then(Value::as_f64)
                        .unwrap_or(0.0);
                    let flagged = inst
                        .get("watchlist_flagged")
                        .and_then(Value::as_bool)
                        .unwrap_or(false);
                    let st_badge = if flagged {
                        r#"<span class="status-badge status-concern">Needs Mentoring Support</span>"#
                    } else {
                        r#"<span class="status-badge status-ok">Good Standing</span>"#
                    };
                    sum_rows.push_str(&format!(
                        r#"<tr>
                            <td data-label="Instructor"><strong>{fname}</strong></td>
                            <td data-label="Responses" class="tabular-nums">{n}</td>
                            <td data-label="Average Rating" class="tabular-nums">{m:.2} / 5.00</td>
                            <td data-label="Descriptive Rating">{adj}</td>
                            <td data-label="Concerns %" class="tabular-nums">{neg:.1}%</td>
                            <td data-label="Standing">{st_badge}</td>
                        </tr>"#,
                        fname = esc(fname),
                        adj = perf_adjective(m),
                    ));
                }
            }
            let prior_mean = s
                .dean_trends
                .as_ref()
                .and_then(|t| t.get("prior_mean_score"))
                .and_then(Value::as_f64)
                .unwrap_or(4.14);
            let delta = mean_score - prior_mean;
            let delta_str = if delta >= 0.0 {
                format!("+{delta:.2} vs. prior semester ({prior_mean:.2})")
            } else {
                format!("{delta:.2} vs. prior semester ({prior_mean:.2})")
            };

            format!(
                r#"<div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-label">Student Participation</div>
                        <div class="kpi-value">{resp_rate:.1}%</div>
                        <div class="kpi-sub">Accreditation benchmark: 85.0%</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Completed Evaluations</div>
                        <div class="kpi-value">{total_subs}</div>
                        <div class="kpi-sub">1st Semester, AY 2026–2027</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Overall Faculty Average</div>
                        <div class="kpi-value">{mean_score:.2} / 5.00</div>
                        <div class="kpi-sub">{adj} &middot; {delta_str}</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Positive Comment Share</div>
                        <div class="kpi-value">{pos_pct:.1}%</div>
                        <div class="kpi-sub">Neutral: {neu_pct:.1}% &middot; Concerns: {neg_pct:.1}%</div>
                    </div>
                </div>
                <section class="doc-sheet" aria-labelledby="roster-ratings-heading">
                    <h2 id="roster-ratings-heading" style="margin-bottom:0.35rem;">Instructor Ratings Summary</h2>
                    <p style="color:var(--ink-secondary);">
                        Summary of student evaluation averages across instructors assigned to your academic unit.
                    </p>
                    <div class="table-wrap">
                        <table class="data-table" aria-label="Instructor Ratings Summary">
                            <thead>
                                <tr>
                                    <th>Instructor</th>
                                    <th>Responses</th>
                                    <th>Average Rating</th>
                                    <th>Descriptive Rating</th>
                                    <th>Concerns %</th>
                                    <th>Standing</th>
                                </tr>
                            </thead>
                            <tbody>{sum_rows}</tbody>
                        </table>
                    </div>
                </section>"#,
                adj = perf_adjective(mean_score),
            )
        }
    };

    format!(
        r#"<main id="main-workspace" class="workspace-container">
            {banner}
            <section class="doc-sheet">
                <div class="flex-between">
                    <div>
                        <p class="institution-line">1st Semester, AY 2026–2027 &middot; School AAAAA13</p>
                        <h1 class="sheet-title" style="margin-bottom:0.2rem;">{page_heading}</h1>
                    </div>
                    <div class="btn-row no-print">
                        <button type="button" class="btn btn-secondary" data-action="export-governance-csv">Download Summary (CSV)</button>
                        <button type="button" class="btn btn-primary" data-action="compile-dossier">Generate Accreditation Summary</button>
                    </div>
                </div>
            </section>
            {dossier_html}
            <div class="portal-tabs no-print" role="tablist" aria-label="Academic Summary Views">
                <button type="button" role="tab" aria-selected="{s1}" class="portal-tab-btn {t1}" data-action="set-dean-tab" data-tab="summary">Summary &amp; Faculty Ratings</button>
                <button type="button" role="tab" aria-selected="{s2}" class="portal-tab-btn {t2}" data-action="set-dean-tab" data-tab="support">Instructors Needing Support</button>
                <button type="button" role="tab" aria-selected="{s3}" class="portal-tab-btn {t3}" data-action="set-dean-tab" data-tab="participation">Class Participation</button>
                <button type="button" role="tab" aria-selected="{s4}" class="portal-tab-btn {t4}" data-action="set-dean-tab" data-tab="comments">Student Comments</button>
            </div>
            {tab_content}
        </main>"#,
        banner = render_banner(s),
        s1 = s.dean_tab == "summary",
        t1 = if s.dean_tab == "summary" {
            "active"
        } else {
            ""
        },
        s2 = s.dean_tab == "support",
        t2 = if s.dean_tab == "support" {
            "active"
        } else {
            ""
        },
        s3 = s.dean_tab == "participation",
        t3 = if s.dean_tab == "participation" {
            "active"
        } else {
            ""
        },
        s4 = s.dean_tab == "comments",
        t4 = if s.dean_tab == "comments" {
            "active"
        } else {
            ""
        },
    )
}

fn render_admin_console(s: &AppState) -> String {
    let win_v = s
        .admin_window
        .as_ref()
        .cloned()
        .unwrap_or_else(|| json!({}));
    let win_status = win_v
        .get("status")
        .and_then(Value::as_str)
        .unwrap_or("OPEN");
    let starts_at = win_v
        .get("starts_at")
        .and_then(Value::as_str)
        .filter(|v| !v.is_empty())
        .unwrap_or("2026-08-15 08:00:00");
    let ends_at = win_v
        .get("ends_at")
        .and_then(Value::as_str)
        .filter(|v| !v.is_empty())
        .unwrap_or("2027-12-20 23:59:59");
    let notice_text = s
        .admin_notice_override
        .as_deref()
        .or_else(|| win_v.get("announcement_message").and_then(Value::as_str))
        .unwrap_or("");

    let controls_enforced = s
        .admin_security
        .as_ref()
        .and_then(|p| p.get("enforced_controls"))
        .and_then(Value::as_u64)
        .unwrap_or(63);

    let tab_body = match s.admin_tab.as_str() {
        "grades" => {
            let secs = s
                .admin_sections
                .as_ref()
                .and_then(|v| v.get("sections"))
                .and_then(Value::as_array)
                .cloned()
                .unwrap_or_default();
            let mut sec_rows = String::new();
            if secs.is_empty() {
                sec_rows.push_str(
                    r#"<tr><td colspan="5" style="text-align:center;color:var(--ink-secondary);padding:1.75rem;">No class sections loaded.</td></tr>"#,
                );
            } else {
                for sec in &secs {
                    let id = sec.get("id").and_then(Value::as_i64).unwrap_or(0);
                    let code = sec.get("course_code").and_then(Value::as_str).unwrap_or("");
                    let title = sec
                        .get("course_title")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let scode = sec
                        .get("section_code")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let prof = sec
                        .get("faculty_name")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let grades = sec
                        .get("term_grades_submitted")
                        .and_then(Value::as_bool)
                        .unwrap_or(false);
                    let rlock = sec
                        .get("registrar_lock")
                        .and_then(Value::as_bool)
                        .unwrap_or(false);

                    let g_badge = if grades {
                        r#"<span class="status-badge status-ok">Submitted</span>"#
                    } else {
                        r#"<span class="status-badge status-pending">Awaiting Instructor</span>"#
                    };
                    let l_badge = if grades && rlock {
                        r#"<span class="status-badge status-ok">Released to Faculty</span>"#
                    } else if rlock {
                        r#"<span class="status-badge status-neutral">Approved (Awaiting Grades)</span>"#
                    } else {
                        r#"<span class="status-badge status-pending">On Hold</span>"#
                    };
                    let toggle_btn = if rlock {
                        format!(
                            r#"<button type="button" class="btn btn-secondary" data-action="admin-toggle-lock" data-section-id="{id}">Hold Report</button>"#
                        )
                    } else {
                        format!(
                            r#"<button type="button" class="btn btn-primary" data-action="admin-toggle-lock" data-section-id="{id}">Release Report</button>"#
                        )
                    };

                    sec_rows.push_str(&format!(
                        r#"<tr>
                            <td data-label="Subject &amp; Section">
                                <div>
                                    <span class="subject-code-tag">{code}</span>
                                    <div style="margin-top:0.2rem;"><strong>{title}</strong> (Section {scode})</div>
                                </div>
                            </td>
                            <td data-label="Instructor">{prof}</td>
                            <td data-label="Final Grades">{g_badge}</td>
                            <td data-label="Report Status">{l_badge}</td>
                            <td data-label="Action">{toggle_btn}</td>
                        </tr>"#,
                        code = esc(code),
                        title = esc(title),
                        scode = esc(scode),
                        prof = esc(prof),
                    ));
                }
            }

            format!(
                r#"<section class="doc-sheet" aria-labelledby="grade-release-heading">
                    <div class="flex-between">
                        <div>
                            <h2 id="grade-release-heading" style="margin-bottom:0.35rem;">Post-Grade Evaluation Report Release</h2>
                            <p style="color:var(--ink-secondary);max-width:65ch;">
                                Faculty members can open their class evaluation report only when final semester grades have been submitted and the Office of the University Registrar has released the section below.
                            </p>
                        </div>
                        <div class="btn-row no-print">
                            <button type="button" class="btn btn-primary" data-action="admin-bulk-lock" data-lock="1">Release All Verified</button>
                            <button type="button" class="btn btn-secondary" data-action="admin-bulk-lock" data-lock="0">Hold All</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table" aria-label="Class Section Grade Release Status">
                            <thead>
                                <tr>
                                    <th>Subject &amp; Section</th>
                                    <th>Instructor</th>
                                    <th>Final Grades</th>
                                    <th>Report Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>{sec_rows}</tbody>
                        </table>
                    </div>
                </section>"#
            )
        }
        "roster" => {
            let creds_html = if let Some(ref creds) = s.admin_issued_creds
                && let Some(arr) = creds.as_array()
                && !arr.is_empty()
            {
                let mut cred_rows = String::new();
                for c in arr {
                    let iid = c
                        .get("institutional_id")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    let em = c.get("email").and_then(Value::as_str).unwrap_or("");
                    let pw = c
                        .get("temporary_password")
                        .and_then(Value::as_str)
                        .unwrap_or("");
                    cred_rows.push_str(&format!(
                        r#"<tr>
                            <td data-label="Student ID" class="tabular-nums"><strong>{iid}</strong></td>
                            <td data-label="University Email">{em}</td>
                            <td data-label="Temporary Password"><span class="ref-code">{pw}</span></td>
                            <td data-label="Requirement">Must change password on first sign-in</td>
                        </tr>"#,
                        iid = esc(iid),
                        em = esc(em),
                        pw = esc(pw),
                    ));
                }
                format!(
                    r#"<hr class="section-divider">
                    <div class="alert-banner alert-info" style="margin-bottom:1rem;">
                        <div class="flex-between-center">
                            <div>
                                <strong>Issued One-Time Temporary Passwords</strong><br>
                                Please download or record these temporary passwords now. For security, they are displayed only once at the time of import.
                            </div>
                            <button type="button" class="btn btn-primary no-print" data-action="export-issued-creds-csv">Download Passwords (CSV)</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table" aria-label="Issued Temporary Passwords">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>University Email</th>
                                    <th>Temporary Password</th>
                                    <th>Sign-In Requirement</th>
                                </tr>
                            </thead>
                            <tbody>{cred_rows}</tbody>
                        </table>
                    </div>"#
                )
            } else {
                String::new()
            };

            format!(
                r#"<section class="doc-sheet" aria-labelledby="roster-import-heading">
                    <h2 id="roster-import-heading" style="margin-bottom:0.35rem;">Class Roster Import</h2>
                    <p style="color:var(--ink-secondary);">
                        Paste student enrollment rows below using five comma-separated columns: <strong>Student ID, Full Name, University Email, Subject Code, Section Code</strong>. Newly created student accounts automatically receive a unique one-time temporary password.
                    </p>
                    <div class="form-group">
                        <label class="form-label" for="admin-roster-csv">Student Enrollment Rows (CSV)</label>
                        <textarea id="admin-roster-csv" class="form-textarea" placeholder="2024-11901, Ana Santos, 2024-11901@aaaaa13.edu, CS-401, SEC-A"></textarea>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" data-action="admin-import-roster">Import Class Roster</button>
                    {creds_html}
                </section>"#
            )
        }
        "privacy" => {
            let decoupled = s
                .admin_security
                .as_ref()
                .and_then(|p| p["vault_anonymity"]["decoupled"].as_bool())
                .unwrap_or(true);
            let anon_status = if decoupled {
                "Verified"
            } else {
                "Review Needed"
            };

            format!(
                r#"<div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-label">Institutional Safeguards</div>
                        <div class="kpi-value">{controls_enforced} of 63</div>
                        <div class="kpi-sub">All active and verified</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Student Anonymity</div>
                        <div class="kpi-value">{anon_status}</div>
                        <div class="kpi-sub">Identity separated from ratings</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Post-Grade Release</div>
                        <div class="kpi-value">Enforced</div>
                        <div class="kpi-sub">Two-step Registrar verification</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Account Protection</div>
                        <div class="kpi-value">Active</div>
                        <div class="kpi-sub">Automatic sign-in attempt limits</div>
                    </div>
                </div>
                <section class="doc-sheet" aria-labelledby="privacy-summary-heading">
                    <h2 id="privacy-summary-heading" style="margin-bottom:0.35rem;">Institutional Privacy &amp; Student Anonymity Summary</h2>
                    <p style="color:var(--ink-secondary);">
                        Summary of active privacy, data protection, and academic governance safeguards compliant with the Data Privacy Act of 2012 (Republic Act No. 10173) and AACCUP Level III standards:
                    </p>
                    <div class="table-wrap">
                        <table class="data-table" aria-label="Privacy and Security Safeguards">
                            <thead>
                                <tr>
                                    <th>Safeguard Area</th>
                                    <th>Status</th>
                                    <th>Institutional Guarantee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td data-label="Safeguard Area"><strong>Separation of Student Identity and Responses</strong></td>
                                    <td data-label="Status"><span class="status-badge status-ok">{anon_status}</span></td>
                                    <td data-label="Guarantee">Completion receipts record only whether an enrolled student completed a subject evaluation. Submitted rating sheets and comments store zero student names, ID numbers, email addresses, IP addresses, or browser session IDs.</td>
                                </tr>
                                <tr>
                                    <td data-label="Safeguard Area"><strong>Automatic Personal Information Removal</strong></td>
                                    <td data-label="Status"><span class="status-badge status-ok">Active</span></td>
                                    <td data-label="Guarantee">Any student ID numbers, email addresses, or telephone numbers accidentally typed inside written comments are automatically removed prior to storage.</td>
                                </tr>
                                <tr>
                                    <td data-label="Safeguard Area"><strong>Post-Grade Faculty Confidentiality</strong></td>
                                    <td data-label="Status"><span class="status-badge status-ok">Active</span></td>
                                    <td data-label="Guarantee">Instructors cannot view class averages or student comments until final semester grades have been submitted and verified by the Office of the University Registrar.</td>
                                </tr>
                                <tr>
                                    <td data-label="Safeguard Area"><strong>Department &amp; College Scope Containment</strong></td>
                                    <td data-label="Status"><span class="status-badge status-ok">Active</span></td>
                                    <td data-label="Guarantee">Department Chairs access only courses within their assigned department, and College Deans access only departments within their assigned college.</td>
                                </tr>
                                <tr>
                                    <td data-label="Safeguard Area"><strong>One-Time Temporary Credentials</strong></td>
                                    <td data-label="Status"><span class="status-badge status-ok">Active</span></td>
                                    <td data-label="Guarantee">New accounts created during class roster import receive individual one-time temporary passwords and must establish a personal password on first sign-in.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>"#
            )
        }
        _ => {
            let status_badge = match win_status {
                "OPEN" => r#"<span class="status-badge status-ok">Open for Submission</span>"#,
                "SUSPENDED" => {
                    r#"<span class="status-badge status-pending">Temporarily Paused</span>"#
                }
                _ => r#"<span class="status-badge status-concern">Closed</span>"#,
            };

            format!(
                r#"<section class="doc-sheet" aria-labelledby="schedule-heading">
                    <div class="flex-between-center" style="margin-bottom:0.5rem;">
                        <h2 id="schedule-heading" style="margin:0;">Semester Evaluation Schedule</h2>
                        {status_badge}
                    </div>
                    <p style="color:var(--ink-secondary);">
                        Set the official opening and closing dates for student evaluations in Philippine Time. Students may submit evaluations at their convenience while the schedule is open.
                    </p>
                    <hr class="section-divider">
                    <div class="form-group" style="max-width:460px;">
                        <label class="form-label" for="win-status">Schedule Status</label>
                        <select id="win-status" class="form-select">
                            <option value="OPEN" {s_open}>Open — Accepting Student Evaluations</option>
                            <option value="SUSPENDED" {s_susp}>Paused — Temporarily Hold Submissions</option>
                            <option value="CLOSED" {s_closed}>Closed — Evaluation Period Ended</option>
                        </select>
                    </div>
                    <div class="two-col-grid">
                        <div class="form-group">
                            <label class="form-label" for="win-starts">Opening Date &amp; Time (Philippine Time)</label>
                            <input id="win-starts" class="form-input tabular-nums" type="datetime-local" value="{starts_local}">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="win-ends">Closing Date &amp; Time (Philippine Time)</label>
                            <input id="win-ends" class="form-input tabular-nums" type="datetime-local" value="{ends_local}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="win-notice">Student Portal Notice (Optional)</label>
                        <textarea id="win-notice" class="form-textarea" style="min-height:88px;" placeholder="Optional announcement displayed regarding the semester evaluation schedule...">{notice_text}</textarea>
                    </div>
                    <div class="btn-row">
                        <button type="button" class="btn btn-primary btn-lg" data-action="admin-update-window">Save Schedule</button>
                        <button type="button" class="btn btn-secondary btn-lg" data-action="admin-extend-7days">Extend Closing Date (+7 Days)</button>
                    </div>
                </section>"#,
                s_open = if win_status == "OPEN" { "selected" } else { "" },
                s_susp = if win_status == "SUSPENDED" {
                    "selected"
                } else {
                    ""
                },
                s_closed = if win_status == "CLOSED" {
                    "selected"
                } else {
                    ""
                },
                starts_local = esc(&sql_to_datetime_local(starts_at)),
                ends_local = esc(&sql_to_datetime_local(ends_at)),
                notice_text = esc(notice_text),
            )
        }
    };

    format!(
        r#"<main id="main-workspace" class="workspace-container">
            {banner}
            <section class="doc-sheet">
                <p class="institution-line">1st Semester, AY 2026–2027 &middot; School AAAAA13</p>
                <h1 class="sheet-title" style="margin-bottom:0.25rem;">Office of the University Registrar — Evaluation Administration</h1>
                <p style="color:var(--ink-secondary);margin-bottom:0;">
                    Manage semester evaluation dates, post-grade report release, class enrollment rosters, and institutional privacy safeguards.
                </p>
            </section>
            <div class="portal-tabs no-print" role="tablist" aria-label="Registrar Administration Sections">
                <button type="button" role="tab" aria-selected="{s1}" class="portal-tab-btn {t1}" data-action="set-admin-tab" data-tab="schedule">Evaluation Schedule</button>
                <button type="button" role="tab" aria-selected="{s2}" class="portal-tab-btn {t2}" data-action="set-admin-tab" data-tab="grades">Grade Release</button>
                <button type="button" role="tab" aria-selected="{s3}" class="portal-tab-btn {t3}" data-action="set-admin-tab" data-tab="roster">Class Roster Import</button>
                <button type="button" role="tab" aria-selected="{s4}" class="portal-tab-btn {t4}" data-action="set-admin-tab" data-tab="privacy">Privacy &amp; Security</button>
            </div>
            {tab_body}
        </main>"#,
        banner = render_banner(s),
        s1 = s.admin_tab == "schedule",
        t1 = if s.admin_tab == "schedule" {
            "active"
        } else {
            ""
        },
        s2 = s.admin_tab == "grades",
        t2 = if s.admin_tab == "grades" {
            "active"
        } else {
            ""
        },
        s3 = s.admin_tab == "roster",
        t3 = if s.admin_tab == "roster" {
            "active"
        } else {
            ""
        },
        s4 = s.admin_tab == "privacy",
        t4 = if s.admin_tab == "privacy" {
            "active"
        } else {
            ""
        },
    )
}

fn render() {
    let Some(d) = doc() else {
        return;
    };
    let Some(root) = d.get_element_by_id("app") else {
        return;
    };

    let html = STATE.with(|c| {
        let s = c.borrow();
        let nav = render_navbar(&s);
        let modals = render_global_modals(&s);
        let base = s.route.split('?').next().unwrap_or("/");
        let view = match base {
            "/" => render_landing(&s),
            "/verify" => format!(
                r#"<main id="main-workspace" class="workspace-container">
                    {}
                    <div class="no-print"><button type="button" class="btn btn-secondary" data-action="nav" data-path="/">&larr; Back to Home</button></div>
                    {}
                </main>"#,
                render_banner(&s),
                render_receipt_checker_box(&s)
            ),
            "/login" => render_login(&s),
            "/account/password" => render_password_change(&s),
            "/queue" => render_student_queue(&s),
            p if p.starts_with("/evaluate") => render_evaluation_form(&s),
            "/faculty" => render_faculty_portal(&s),
            "/chair" | "/dean" => render_dean_or_chair(&s),
            "/admin" => render_admin_console(&s),
            "/access-denied" => r#"<main id="main-workspace" class="workspace-container">
                <section class="doc-sheet" style="max-width:560px;margin:0 auto;">
                    <p class="institution-line">School AAAAA13 &middot; Access Control</p>
                    <h1 class="sheet-title" style="margin-bottom:0.4rem;">Page Not Available for Your Role</h1>
                    <p style="color:var(--ink-secondary);">Your current university account does not have permission to view this page.</p>
                    <button type="button" class="btn btn-primary" data-action="nav" data-path="/">Return to Home</button>
                </section>
            </main>"#
                .to_string(),
            _ => r#"<main id="main-workspace" class="workspace-container">
                <section class="doc-sheet" style="max-width:560px;margin:0 auto;">
                    <h1 class="sheet-title" style="margin-bottom:0.4rem;">Page Not Found</h1>
                    <p style="color:var(--ink-secondary);">The page you requested could not be found in the Faculty Evaluation Portal.</p>
                    <button type="button" class="btn btn-primary" data-action="nav" data-path="/">Return to Home</button>
                </section>
            </main>"#
                .to_string(),
        };
        format!("{nav}{view}{modals}")
    });

    root.set_inner_html(&html);
}

fn sync_step2_fields_to_draft() {
    let comment = input_val("eval-comment");
    let q1 = input_val("eval-q1");
    let q2 = input_val("eval-q2");
    let q3 = input_val("eval-q3");
    let q4 = input_val("eval-q4");
    STATE.with(|c| {
        let mut s = c.borrow_mut();
        s.draft.comment_text = comment;
        s.draft.three_words = q1;
        s.draft.improvements = q2;
        s.draft.strengths = q3;
        s.draft.recommendations = q4;
        save_draft(s.eval_section_id, &s.draft);
    });
}

fn submit_evaluation_flow(confirm_uniform: bool) {
    sync_step2_fields_to_draft();
    let payload_opt = STATE.with(|c| {
        let mut s = c.borrow_mut();
        let Some(stats) = compute_likert_stats(&s.draft.ratings) else {
            s.banner_msg = Some((
                "Please rate all 25 statements between 1 and 5 before submitting.".to_string(),
                true,
            ));
            return None;
        };
        if stats.is_straight_lined && !confirm_uniform {
            s.show_straight_line_modal = true;
            return None;
        }
        s.show_straight_line_modal = false;
        s.submitting = true;
        let elapsed_s = ((js_sys::Date::now() - s.eval_started_ms).max(0.0) / 1000.0) as i64;
        let words: Vec<String> = s
            .draft
            .three_words
            .split([',', ';', ' '])
            .map(str::trim)
            .filter(|w| !w.is_empty())
            .take(3)
            .map(String::from)
            .collect();
        let topics: Vec<String> = s
            .draft
            .improvements
            .split(';')
            .map(str::trim)
            .filter(|t| !t.is_empty())
            .map(String::from)
            .collect();
        Some((
            s.eval_section_id,
            json!({
                "course_section_id": s.eval_section_id,
                "submission_key": s.eval_submission_key,
                "ratings": s.draft.ratings,
                "dwell_time_seconds": elapsed_s,
                "confirm_uniform": confirm_uniform,
                "start_token": s.eval_start_token,
                "comment_text": s.draft.comment_text,
                "professor_questions": {
                    "three_words": words,
                    "room_for_improvements": topics,
                    "teaching_strengths": s.draft.strengths,
                    "recommendations": s.draft.recommendations,
                }
            }),
        ))
    });

    render();
    let Some((sec_id, req_json)) = payload_opt else {
        return;
    };

    spawn_local(async move {
        let res = api_request("POST", "/api/evaluation/submit", Some(req_json)).await;
        STATE.with(|c| {
            let mut s = c.borrow_mut();
            s.submitting = false;
            match res {
                Ok((200 | 201, body)) => {
                    remove_draft(sec_id);
                    let tok = body
                        .get("clearance_token")
                        .and_then(Value::as_str)
                        .unwrap_or("COMPLETED");
                    s.eval_section_id = 0;
                    s.banner_msg = Some((
                        format!(
                            "Thank you! Your evaluation has been submitted. Completion Receipt Code: {tok}"
                        ),
                        false,
                    ));
                    s.route = "/queue".to_string();
                }
                Ok((_, body)) => {
                    if body.get("code").and_then(Value::as_str)
                        == Some("ZERO_VARIANCE_CONFIRMATION_REQUIRED")
                    {
                        s.show_straight_line_modal = true;
                    } else {
                        let msg = humanize_api_error(
                            &body,
                            "Unable to submit evaluation. Please review your answers and try again.",
                        );
                        s.banner_msg = Some((msg, true));
                    }
                }
                Err(e) => {
                    s.banner_msg = Some((e, true));
                }
            }
        });
        let current_route = STATE.with(|c| c.borrow().route.clone());
        if current_route == "/queue"
            && let Some(w) = win()
            && let Ok(h) = w.history()
        {
            let _ = h.push_state_with_url(&JsValue::NULL, "", Some("/queue"));
        }
        update_page_title(&current_route);
        render();
        if current_route == "/queue" {
            scroll_top();
            load_route_data("/queue");
        }
    });
}

fn do_login_submit() {
    let identifier = input_val("login-identifier");
    let password = input_val("login-password");
    spawn_local(async move {
        match api_request(
            "POST",
            "/api/auth/login",
            Some(json!({ "identifier": identifier, "password": password })),
        )
        .await
        {
            Ok((200, body)) => {
                let tok = body
                    .get("token")
                    .and_then(Value::as_str)
                    .unwrap_or("")
                    .to_string();
                if let Some(u_val) = body.get("user")
                    && let Ok(user) = serde_json::from_value::<UserSession>(u_val.clone())
                {
                    save_session(&tok, &user);
                    let dest = if user.must_change_password {
                        "/account/password".to_string()
                    } else {
                        default_route_for_role(&user.role).to_string()
                    };
                    STATE.with(|c| {
                        let mut s = c.borrow_mut();
                        s.token = Some(tok);
                        s.user = Some(user);
                        s.show_password = false;
                    });
                    push_route(&dest);
                }
            }
            Ok((_, body)) => {
                let msg = humanize_api_error(&body, "Sign-in failed. Please try again.");
                STATE.with(|c| c.borrow_mut().banner_msg = Some((msg, true)));
                render();
            }
            Err(e) => {
                STATE.with(|c| c.borrow_mut().banner_msg = Some((e, true)));
                render();
            }
        }
    });
}

fn do_password_submit() {
    let cur = input_val("pw-current");
    let next = input_val("pw-next");
    spawn_local(async move {
        match api_request(
            "POST",
            "/api/auth/password",
            Some(json!({ "current_password": cur, "new_password": next })),
        )
        .await
        {
            Ok((200, _)) => {
                let dest = STATE.with(|c| {
                    let mut s = c.borrow_mut();
                    let tok_clone = s.token.clone();
                    let mut role = "student".to_string();
                    if let Some(ref mut u) = s.user {
                        u.must_change_password = false;
                        role = u.role.clone();
                        if let Some(ref t) = tok_clone {
                            save_session(t, u);
                        }
                    }
                    s.show_password = false;
                    default_route_for_role(&role).to_string()
                });
                push_route(&dest);
            }
            Ok((_, body)) => {
                let msg = humanize_api_error(&body, "Could not update password.");
                STATE.with(|c| c.borrow_mut().banner_msg = Some((msg, true)));
                render();
            }
            Err(e) => {
                STATE.with(|c| c.borrow_mut().banner_msg = Some((e, true)));
                render();
            }
        }
    });
}

fn do_verify_token() {
    let tok = input_val("verify-token-input");
    spawn_local(async move {
        if let Ok((_, body)) = api_request(
            "POST",
            "/api/clearance/verify",
            Some(json!({ "clearance_token": tok })),
        )
        .await
        {
            STATE.with(|c| c.borrow_mut().verify_result = Some(body));
            render();
        }
    });
}

fn do_search_insights() {
    let q_txt = input_val("insights-q-input");
    let v_flt = input_val("insights-val-select");
    let is_chair = STATE.with(|c| {
        let mut s = c.borrow_mut();
        s.insights_query = q_txt.clone();
        s.insights_valence = v_flt.clone();
        s.route.starts_with("/chair")
    });
    let prefix = if is_chair { "/api/chair" } else { "/api/dean" };
    let url = format!(
        "{prefix}/insights-search?q={}&valence={}",
        percent_encode_uri(&q_txt),
        percent_encode_uri(&v_flt)
    );
    spawn_local(async move {
        if let Ok((200, data)) = api_request("GET", &url, None).await {
            STATE.with(|c| c.borrow_mut().dean_insights = Some(data));
            render();
        }
    });
}

fn build_fallback_governance_csv(s: &AppState) -> String {
    let mut out =
        String::from("Instructor,Responses,AverageRating,DescriptiveRating,ConcernsPct\n");
    if let Some(arr) = s
        .dean_overview
        .as_ref()
        .and_then(|o| o.get("instructor_summaries"))
        .and_then(Value::as_array)
    {
        for inst in arr {
            let name = sanitize_csv_cell(
                inst.get("faculty_name")
                    .and_then(Value::as_str)
                    .unwrap_or(""),
            )
            .replace('"', "\"\"");
            let cnt = inst
                .get("submission_count")
                .and_then(Value::as_u64)
                .unwrap_or(0);
            let mean = inst
                .get("mean_score")
                .and_then(Value::as_f64)
                .unwrap_or(0.0);
            let neg = inst
                .get("negative_pct")
                .and_then(Value::as_f64)
                .unwrap_or(0.0);
            out.push_str(&format!(
                "\"{name}\",{cnt},{mean:.2},{},{neg:.1}\n",
                perf_adjective(mean)
            ));
        }
    }
    out
}

fn find_action_el(mut cur: Option<Element>) -> Option<(Element, String)> {
    while let Some(el) = cur {
        if let Some(act) = el.get_attribute("data-action") {
            return Some((el, act));
        }
        cur = el.parent_element();
    }
    None
}

fn find_likert_row(mut cur: Option<Element>) -> Option<usize> {
    while let Some(el) = cur {
        if let Some(idx_s) = el.get_attribute("data-likert-row")
            && let Ok(idx) = idx_s.parse::<usize>()
        {
            return Some(idx);
        }
        cur = el.parent_element();
    }
    None
}

fn handle_click(ev: Event) {
    let target = ev.target().and_then(|t| t.dyn_into::<Element>().ok());
    let Some((el, action)) = find_action_el(target) else {
        return;
    };

    if el.tag_name().eq_ignore_ascii_case("A") {
        ev.prevent_default();
    }

    match action.as_str() {
        "nav" => {
            if let Some(path) = el.get_attribute("data-path") {
                push_route(&path);
            }
        }
        "goto-verify" => {
            let cur_route = STATE.with(|c| {
                let mut s = c.borrow_mut();
                s.mobile_menu_open = false;
                s.route.clone()
            });
            if cur_route != "/" {
                push_route("/");
            } else {
                render();
            }
            if let Some(d) = doc() {
                if let Some(sec) = d.get_element_by_id("receipt-check-section") {
                    let opts = ScrollIntoViewOptions::new();
                    opts.set_behavior(ScrollBehavior::Smooth);
                    sec.scroll_into_view_with_scroll_into_view_options(&opts);
                }
                if let Some(inp) = d
                    .get_element_by_id("verify-token-input")
                    .and_then(|e| e.dyn_into::<HtmlElement>().ok())
                {
                    let _ = inp.focus();
                }
            }
        }
        "nav-dean-tab" => {
            let path = el
                .get_attribute("data-path")
                .unwrap_or_else(|| "/dean".to_string());
            let tab = el
                .get_attribute("data-tab")
                .unwrap_or_else(|| "summary".to_string());
            let already_there = STATE.with(|c| {
                let mut s = c.borrow_mut();
                s.dean_tab = tab;
                s.mobile_menu_open = false;
                s.route.starts_with(&path)
            });
            if already_there {
                render();
            } else {
                push_route(&path);
            }
        }
        "nav-admin-tab" => {
            let tab = el
                .get_attribute("data-tab")
                .unwrap_or_else(|| "schedule".to_string());
            let already_there = STATE.with(|c| {
                let mut s = c.borrow_mut();
                s.admin_tab = tab;
                s.mobile_menu_open = false;
                s.route.starts_with("/admin")
            });
            if already_there {
                render();
            } else {
                push_route("/admin");
            }
        }
        "toggle-theme" => {
            let next_theme = STATE.with(|c| {
                if c.borrow().theme == "dark" {
                    "light"
                } else {
                    "dark"
                }
            });
            apply_theme(next_theme);
            render();
        }
        "toggle-font-scale" => {
            let next_scale = STATE.with(|c| {
                if c.borrow().font_scale == "large" {
                    "normal"
                } else {
                    "large"
                }
            });
            apply_font_scale(next_scale);
            render();
        }
        "open-rubric" => {
            STATE.with(|c| {
                let mut s = c.borrow_mut();
                s.mobile_menu_open = false;
                s.show_rubric_modal = true;
            });
            render();
        }
        "close-modals" => {
            STATE.with(|c| {
                let mut s = c.borrow_mut();
                s.show_rubric_modal = false;
                s.show_discard_modal = false;
                s.receipt_modal_course = None;
            });
            render();
        }
        "toggle-mobile-menu" => {
            STATE.with(|c| {
                let mut s = c.borrow_mut();
                s.mobile_menu_open = !s.mobile_menu_open;
            });
            render();
        }
        "toggle-password" => {
            let id_v = input_val("login-identifier");
            let pw_v = input_val("login-password");
            let cur_v = input_val("pw-current");
            let nxt_v = input_val("pw-next");
            STATE.with(|c| {
                let mut s = c.borrow_mut();
                s.show_password = !s.show_password;
            });
            render();
            if let Some(d) = doc() {
                for (id, val) in [
                    ("login-identifier", id_v),
                    ("login-password", pw_v),
                    ("pw-current", cur_v),
                    ("pw-next", nxt_v),
                ] {
                    if let Some(inp) = d
                        .get_element_by_id(id)
                        .and_then(|e| e.dyn_into::<HtmlInputElement>().ok())
                    {
                        inp.set_value(&val);
                    }
                }
            }
        }
        "verify-token" => do_verify_token(),
        "login-submit" => do_login_submit(),
        "password-submit" => do_password_submit(),
        "logout" => {
            spawn_local(async {
                let _ = api_request("POST", "/api/auth/logout", Some(json!({}))).await;
                clear_session();
                STATE.with(|c| {
                    let mut s = c.borrow_mut();
                    s.token = None;
                    s.user = None;
                });
                push_route("/login");
            });
        }
        "set-queue-filter" => {
            if let Some(f) = el.get_attribute("data-filter") {
                STATE.with(|c| c.borrow_mut().queue_filter = f);
                render();
            }
        }
        "view-receipt" => {
            if let Some(idx) = el
                .get_attribute("data-course-idx")
                .and_then(|s| s.parse::<usize>().ok())
            {
                STATE.with(|c| {
                    let mut s = c.borrow_mut();
                    let found = s
                        .queue_data
                        .as_ref()
                        .and_then(|q| q.get("courses"))
                        .and_then(Value::as_array)
                        .and_then(|arr| arr.get(idx))
                        .cloned();
                    s.receipt_modal_course = found;
                });
                render();
            }
        }
        "export-queue-csv" => {
            let csv = STATE.with(|c| {
                let s = c.borrow();
                let items = s
                    .queue_data
                    .as_ref()
                    .and_then(|q| q.get("courses"))
                    .and_then(Value::as_array)
                    .cloned()
                    .unwrap_or_default();
                let mut out =
                    String::from("SubjectCode,CourseTitle,Section,Instructor,Status,ReceiptCode\n");
                for it in items {
                    let code = sanitize_csv_cell(
                        it.get("course_code").and_then(Value::as_str).unwrap_or(""),
                    );
                    let title = sanitize_csv_cell(
                        it.get("course_title").and_then(Value::as_str).unwrap_or(""),
                    )
                    .replace('"', "\"\"");
                    let sec = sanitize_csv_cell(
                        it.get("section_code")
                            .and_then(Value::as_str)
                            .unwrap_or("SEC-A"),
                    );
                    let prof = sanitize_csv_cell(
                        it.get("faculty_name").and_then(Value::as_str).unwrap_or(""),
                    )
                    .replace('"', "\"\"");
                    let done = it.get("status").and_then(Value::as_str) == Some("CLEARED");
                    let st = if done { "Completed" } else { "Pending" };
                    let tok = sanitize_csv_cell(
                        it.get("clearance_token")
                            .and_then(Value::as_str)
                            .unwrap_or("Pending"),
                    );
                    out.push_str(&format!("{code},\"{title}\",{sec},\"{prof}\",{st},{tok}\n"));
                }
                out
            });
            trigger_csv_download("My_Enrolled_Subjects_AY2026_2027.csv", &csv);
        }
        "start-eval" => {
            if let Some(sec_str) = el.get_attribute("data-section-id") {
                push_route(&format!("/evaluate/{sec_str}"));
            }
        }
        "jump-cluster" => {
            if let Some(c_idx) = el.get_attribute("data-cluster")
                && let Some(d) = doc()
                && let Some(card) = d.get_element_by_id(&format!("cluster-card-{c_idx}"))
            {
                let opts = ScrollIntoViewOptions::new();
                opts.set_behavior(ScrollBehavior::Smooth);
                card.scroll_into_view_with_scroll_into_view_options(&opts);
            }
        }
        "ask-discard-draft" => {
            STATE.with(|c| c.borrow_mut().show_discard_modal = true);
            render();
        }
        "confirm-discard-draft" => {
            STATE.with(|c| {
                let mut s = c.borrow_mut();
                remove_draft(s.eval_section_id);
                s.draft = DraftPayload::default();
                s.eval_step = 1;
                s.show_discard_modal = false;
                s.banner_msg = Some(("Saved answers cleared for this subject.".to_string(), false));
            });
            render();
        }
        "set-rating" => {
            let idx = el
                .get_attribute("data-idx")
                .and_then(|s| s.parse::<usize>().ok())
                .unwrap_or(0);
            let val = el
                .get_attribute("data-val")
                .and_then(|s| s.parse::<i32>().ok())
                .unwrap_or(0);
            if idx < 25 && (1..=5).contains(&val) {
                STATE.with(|c| {
                    let mut s = c.borrow_mut();
                    s.draft.ratings[idx] = val;
                    s.incomplete_indices.retain(|&x| x != idx);
                    save_draft(s.eval_section_id, &s.draft);
                });
                render();
            }
        }
        "step-next" => {
            let first_missing = STATE.with(|c| {
                let mut s = c.borrow_mut();
                let missing: Vec<usize> = s
                    .draft
                    .ratings
                    .iter()
                    .enumerate()
                    .filter_map(|(i, &r)| if (1..=5).contains(&r) { None } else { Some(i) })
                    .collect();
                if missing.is_empty() {
                    s.incomplete_indices.clear();
                    s.eval_step = 2;
                    s.banner_msg = None;
                    None
                } else {
                    let first = missing[0];
                    let count = missing.len();
                    s.incomplete_indices = missing;
                    s.banner_msg = Some((
                        format!(
                            "Please complete all 25 statements before continuing ({count} unanswered statement{} highlighted below).",
                            if count == 1 { "" } else { "s" }
                        ),
                        true,
                    ));
                    Some(first)
                }
            });
            render();
            if first_missing.is_none() {
                scroll_top();
            }
            if let Some(idx) = first_missing
                && let Some(d) = doc()
                && let Some(row) = d.get_element_by_id(&format!("likert-item-{idx}"))
            {
                let opts = ScrollIntoViewOptions::new();
                opts.set_behavior(ScrollBehavior::Smooth);
                row.scroll_into_view_with_scroll_into_view_options(&opts);
            }
        }
        "step-back" => {
            sync_step2_fields_to_draft();
            STATE.with(|c| c.borrow_mut().eval_step = 1);
            render();
            scroll_top();
        }
        "eval-submit" => submit_evaluation_flow(false),
        "cancel-straight-line" => {
            STATE.with(|c| {
                let mut s = c.borrow_mut();
                s.show_straight_line_modal = false;
                s.eval_step = 1;
            });
            render();
        }
        "confirm-straight-line" => submit_evaluation_flow(true),
        "print-cert" => {
            if let Some(w) = win() {
                let _ = w.print();
            }
        }
        "faculty-submit-grades" => {
            let sec_id = el
                .get_attribute("data-section-id")
                .and_then(|s| s.parse::<i64>().ok())
                .unwrap_or(0);
            spawn_local(async move {
                let url = format!("/api/faculty/sections/{sec_id}/submit-grades");
                if let Ok((200, _)) = api_request("POST", &url, Some(json!({}))).await {
                    STATE.with(|c| {
                        let mut s = c.borrow_mut();
                        s.selected_faculty_sec_id = sec_id;
                        s.banner_msg = Some((
                            "Final semester grades have been marked as submitted.".to_string(),
                            false,
                        ));
                    });
                }
                load_route_data("/faculty");
            });
        }
        "faculty-view-report" => {
            let sec_id = el
                .get_attribute("data-section-id")
                .and_then(|s| s.parse::<i64>().ok())
                .unwrap_or(0);
            spawn_local(async move {
                let url = format!("/api/faculty/sections/{sec_id}/report");
                match api_request("GET", &url, None).await {
                    Ok((200, rep)) => {
                        STATE.with(|c| {
                            let mut s = c.borrow_mut();
                            s.selected_faculty_sec_id = sec_id;
                            s.selected_faculty_report = Some(rep);
                            s.banner_msg = None;
                        });
                    }
                    Ok((_, err_b)) => {
                        let msg = humanize_api_error(&err_b, "Report is currently unavailable.");
                        STATE.with(|c| c.borrow_mut().banner_msg = Some((msg, true)));
                    }
                    Err(e) => {
                        STATE.with(|c| c.borrow_mut().banner_msg = Some((e, true)));
                    }
                }
                render();
            });
        }
        "export-faculty-csv" => {
            let csv_opt = STATE.with(|c| {
                let s = c.borrow();
                let rep = s.selected_faculty_report.as_ref()?;
                let code = sanitize_csv_cell(
                    rep.get("course_code")
                        .and_then(Value::as_str)
                        .unwrap_or("SEC"),
                );
                let scode = sanitize_csv_cell(
                    rep.get("section_code")
                        .and_then(Value::as_str)
                        .unwrap_or("SEC-A"),
                );
                let mean = rep.get("mean_score").and_then(Value::as_f64).unwrap_or(0.0);
                let cnt = rep
                    .get("submission_count")
                    .and_then(Value::as_u64)
                    .unwrap_or(0);
                let mut out = format!(
                    "SubjectCode,Section,Responses,OverallRating,DescriptiveRating\n{code},{scode},{cnt},{mean:.2},{}\n\nEvaluationArea,AverageRating\n",
                    perf_adjective(mean)
                );
                for tag in PEDAGOGICAL_TAGS {
                    let cm = rep["cluster_means"]
                        .get(tag)
                        .and_then(Value::as_f64)
                        .unwrap_or(mean);
                    out.push_str(&format!("\"{}\",{cm:.2}\n", friendly_area_name(tag)));
                }
                Some(out)
            });
            if let Some(csv) = csv_opt {
                trigger_csv_download("Class_Evaluation_Summary.csv", &csv);
            }
        }
        "set-dean-tab" => {
            if let Some(t) = el.get_attribute("data-tab") {
                STATE.with(|c| c.borrow_mut().dean_tab = t);
                render();
            }
        }
        "signoff-plan" => {
            let fid = el
                .get_attribute("data-fac-id")
                .and_then(|s| s.parse::<i64>().ok())
                .unwrap_or(0);
            let plan_id = el
                .get_attribute("data-plan-id")
                .unwrap_or_else(|| "AAAAA13-ACT".to_string());
            let next_st = el
                .get_attribute("data-status")
                .unwrap_or_else(|| "APPROVED".to_string());
            spawn_local(async move {
                let _ = api_request(
                    "POST",
                    "/api/faculty/action-plan",
                    Some(json!({ "faculty_id": fid, "plan_id": plan_id, "status": next_st })),
                )
                .await;
                let human_status = if next_st == "RESOLVED" {
                    "Mentoring support plan marked as completed."
                } else {
                    "Mentoring support plan approved."
                };
                STATE.with(|c| {
                    c.borrow_mut().banner_msg = Some((human_status.to_string(), false));
                });
                render();
            });
        }
        "search-insights" => do_search_insights(),
        "export-governance-csv" => {
            let (endpoint, filename, fallback_csv) = STATE.with(|c| {
                let s = c.borrow();
                let is_chair = s.route.starts_with("/chair");
                let ep = if is_chair {
                    "/api/chair/export-csv"
                } else {
                    "/api/dean/export-csv"
                };
                let fname = if is_chair {
                    "Department_Evaluation_Summary.csv"
                } else {
                    "College_Evaluation_Summary.csv"
                };
                (ep, fname, build_fallback_governance_csv(&s))
            });
            spawn_local(async move {
                match api_request("POST", endpoint, None).await {
                    Ok((200, body)) => {
                        let raw = body
                            .get("raw")
                            .and_then(Value::as_str)
                            .filter(|r| !r.is_empty())
                            .unwrap_or(&fallback_csv);
                        trigger_csv_download(filename, raw);
                    }
                    _ => {
                        trigger_csv_download(filename, &fallback_csv);
                    }
                }
            });
        }
        "export-pending-students-csv" => {
            let csv = STATE.with(|c| {
                let s = c.borrow();
                let mut out = String::from(
                    "StudentID,StudentName,UniversityEmail,CompletedSubjects,EnrolledSubjects,PendingSubjects\n",
                );
                if let Some(arr) = s
                    .dean_participation
                    .as_ref()
                    .and_then(|p| p.get("pending_students"))
                    .and_then(Value::as_array)
                {
                    for st in arr {
                        let iid = sanitize_csv_cell(
                            st.get("institutional_id")
                                .and_then(Value::as_str)
                                .unwrap_or(""),
                        );
                        let sname = sanitize_csv_cell(
                            st.get("student_name").and_then(Value::as_str).unwrap_or(""),
                        )
                        .replace('"', "\"\"");
                        let em = sanitize_csv_cell(
                            st.get("email").and_then(Value::as_str).unwrap_or(""),
                        );
                        let comp = st
                            .get("completed_count")
                            .and_then(Value::as_u64)
                            .unwrap_or(0);
                        let enr = st
                            .get("enrolled_count")
                            .and_then(Value::as_u64)
                            .unwrap_or(0);
                        let codes = st
                            .get("pending_courses")
                            .and_then(Value::as_array)
                            .map(|a| {
                                a.iter()
                                    .filter_map(Value::as_str)
                                    .collect::<Vec<_>>()
                                    .join("; ")
                            })
                            .unwrap_or_default();
                        let safe_codes = sanitize_csv_cell(&codes).replace('"', "\"\"");
                        out.push_str(&format!(
                            "{iid},\"{sname}\",{em},{comp},{enr},\"{safe_codes}\"\n"
                        ));
                    }
                }
                out
            });
            trigger_csv_download("Pending_Evaluation_FollowUp_List.csv", &csv);
        }
        "compile-dossier" => {
            let is_chair = STATE.with(|c| c.borrow().route.starts_with("/chair"));
            let endpoint = if is_chair {
                "/api/chair/accreditation/compile"
            } else {
                "/api/dean/accreditation/compile"
            };
            spawn_local(async move {
                if let Ok((200, d)) = api_request("POST", endpoint, Some(json!({}))).await {
                    STATE.with(|c| c.borrow_mut().compiled_dossier = Some(d));
                    render();
                }
            });
        }
        "set-admin-tab" => {
            if let Some(t) = el.get_attribute("data-tab") {
                STATE.with(|c| c.borrow_mut().admin_tab = t);
                render();
            }
        }
        "admin-toggle-lock" => {
            let sec_id = el
                .get_attribute("data-section-id")
                .and_then(|s| s.parse::<i64>().ok())
                .unwrap_or(0);
            spawn_local(async move {
                let url = format!("/api/admin/sections/{sec_id}/registrar-lock");
                let _ = api_request("POST", &url, Some(json!({}))).await;
                load_route_data("/admin");
            });
        }
        "admin-bulk-lock" => {
            let lock_flag = el.get_attribute("data-lock").as_deref() == Some("1");
            spawn_local(async move {
                let _ = api_request(
                    "POST",
                    "/api/admin/sections/bulk-lock",
                    Some(json!({ "registrar_lock": lock_flag })),
                )
                .await;
                let msg = if lock_flag {
                    "Released evaluation reports for all verified class sections."
                } else {
                    "Placed all class evaluation reports on hold."
                };
                STATE.with(|c| c.borrow_mut().banner_msg = Some((msg.to_string(), false)));
                load_route_data("/admin");
            });
        }
        "admin-update-window" => {
            let st = input_val("win-status");
            let starts = datetime_local_to_sql(&input_val("win-starts"), "00");
            let ends = datetime_local_to_sql(&input_val("win-ends"), "59");
            let notice = input_val("win-notice");
            spawn_local(async move {
                match api_request(
                    "POST",
                    "/api/admin/evaluation-window",
                    Some(json!({
                        "status": st,
                        "starts_at": starts,
                        "ends_at": ends,
                        "announcement_message": notice,
                    })),
                )
                .await
                {
                    Ok((200, w)) => {
                        STATE.with(|c| {
                            let mut s = c.borrow_mut();
                            s.admin_window = Some(w);
                            s.admin_notice_override = Some(notice);
                            s.banner_msg = Some((
                                "Evaluation schedule saved (Philippine Time).".to_string(),
                                false,
                            ));
                        });
                        render();
                    }
                    Ok((_, b)) => {
                        let msg = humanize_api_error(&b, "Unable to update evaluation schedule.");
                        STATE.with(|c| c.borrow_mut().banner_msg = Some((msg, true)));
                        render();
                    }
                    Err(e) => {
                        STATE.with(|c| c.borrow_mut().banner_msg = Some((e, true)));
                        render();
                    }
                }
            });
        }
        "admin-extend-7days" => {
            let starts = datetime_local_to_sql(&input_val("win-starts"), "00");
            let cur_ends = input_val("win-ends");
            let notice = input_val("win-notice");
            let base_ms = js_sys::Date::parse(&cur_ends);
            let next_ms = if base_ms.is_nan() {
                js_sys::Date::now() + 7.0 * 86_400_000.0
            } else {
                base_ms + 7.0 * 86_400_000.0
            };
            let d = js_sys::Date::new(&JsValue::from_f64(next_ms));
            let extended_sql = format!(
                "{:04}-{:02}-{:02} {:02}:{:02}:59",
                d.get_full_year(),
                d.get_month() + 1,
                d.get_date(),
                d.get_hours(),
                d.get_minutes()
            );
            spawn_local(async move {
                if let Ok((200, w)) = api_request(
                    "POST",
                    "/api/admin/evaluation-window",
                    Some(json!({
                        "status": "OPEN",
                        "starts_at": starts,
                        "ends_at": extended_sql,
                        "announcement_message": notice,
                    })),
                )
                .await
                {
                    STATE.with(|c| {
                        let mut s = c.borrow_mut();
                        s.admin_window = Some(w);
                        s.admin_notice_override = Some(notice);
                        s.banner_msg = Some((
                            "Closing date extended by 7 days and schedule set to Open.".to_string(),
                            false,
                        ));
                    });
                    render();
                }
            });
        }
        "admin-import-roster" => {
            let csv_raw = input_val("admin-roster-csv");
            spawn_local(async move {
                match api_request(
                    "POST",
                    "/api/admin/rosters/import",
                    Some(json!({ "csv": csv_raw })),
                )
                .await
                {
                    Ok((200, body)) => {
                        let creds = body.get("issued_credentials").cloned();
                        let count = body
                            .get("imported_rows")
                            .and_then(Value::as_u64)
                            .unwrap_or(0);
                        STATE.with(|c| {
                            let mut s = c.borrow_mut();
                            s.admin_issued_creds = creds;
                            s.banner_msg = Some((
                                format!("Successfully imported {count} class enrollment row(s)."),
                                false,
                            ));
                        });
                        load_route_data("/admin");
                        render();
                    }
                    Ok((_, b)) => {
                        let msg =
                            humanize_api_error(&b, "Class roster import could not be completed.");
                        STATE.with(|c| c.borrow_mut().banner_msg = Some((msg, true)));
                        render();
                    }
                    Err(e) => {
                        STATE.with(|c| c.borrow_mut().banner_msg = Some((e, true)));
                        render();
                    }
                }
            });
        }
        "export-issued-creds-csv" => {
            let csv_opt = STATE.with(|c| {
                let s = c.borrow();
                let arr = s.admin_issued_creds.as_ref()?.as_array()?;
                let mut out =
                    String::from("StudentID,UniversityEmail,TemporaryPassword,SignInRequirement\n");
                for item in arr {
                    let iid = sanitize_csv_cell(
                        item.get("institutional_id")
                            .and_then(Value::as_str)
                            .unwrap_or(""),
                    );
                    let em =
                        sanitize_csv_cell(item.get("email").and_then(Value::as_str).unwrap_or(""));
                    let pw = sanitize_csv_cell(
                        item.get("temporary_password")
                            .and_then(Value::as_str)
                            .unwrap_or(""),
                    );
                    out.push_str(&format!("{iid},{em},{pw},PasswordChangeRequired\n"));
                }
                Some(out)
            });
            if let Some(csv) = csv_opt {
                trigger_csv_download("Issued_Temporary_Passwords.csv", &csv);
            }
        }
        _ => {}
    }
}

fn handle_input(ev: Event) {
    let Some(el) = ev.target().and_then(|t| t.dyn_into::<Element>().ok()) else {
        return;
    };
    let act = el.get_attribute("data-action");
    match act.as_deref() {
        Some("draft-text-input") => {
            sync_step2_fields_to_draft();
        }
        Some("queue-search") => {
            if let Ok(inp) = el.dyn_into::<HtmlInputElement>() {
                let val = inp.value();
                let pos = inp
                    .selection_start()
                    .ok()
                    .flatten()
                    .unwrap_or(val.len() as u32);
                STATE.with(|c| c.borrow_mut().queue_search = val);
                render();
                if let Some(d) = doc()
                    && let Some(re_inp) = d
                        .get_element_by_id("queue-search-input")
                        .and_then(|e| e.dyn_into::<HtmlInputElement>().ok())
                {
                    let _ = re_inp.focus();
                    let _ = re_inp.set_selection_range(pos, pos);
                }
            }
        }
        _ => {}
    }
}

fn handle_change(ev: Event) {
    let Some(el) = ev.target().and_then(|t| t.dyn_into::<Element>().ok()) else {
        return;
    };
    let act = el.get_attribute("data-action");
    match act.as_deref() {
        Some("faculty-select-section") => {
            if let Ok(sel) = el.dyn_into::<HtmlSelectElement>() {
                let sec_id = sel.value().parse::<i64>().unwrap_or(0);
                let should_load_report = STATE.with(|c| {
                    let mut s = c.borrow_mut();
                    s.selected_faculty_sec_id = sec_id;
                    s.selected_faculty_report = None;
                    s.faculty_sections
                        .as_ref()
                        .and_then(|v| v.get("sections"))
                        .and_then(Value::as_array)
                        .is_some_and(|arr| {
                            arr.iter().any(|it| {
                                it.get("id").and_then(Value::as_i64) == Some(sec_id)
                                    && it
                                        .get("term_grades_submitted")
                                        .and_then(Value::as_bool)
                                        .unwrap_or(false)
                                    && it
                                        .get("registrar_lock")
                                        .and_then(Value::as_bool)
                                        .unwrap_or(false)
                            })
                        })
                });
                render();
                if should_load_report {
                    fetch_faculty_report_async(sec_id);
                }
            }
        }
        Some("insights-valence-change") => {
            do_search_insights();
        }
        _ => {}
    }
}

fn handle_keydown(ev: Event) {
    let Ok(kev) = ev.dyn_into::<KeyboardEvent>() else {
        return;
    };
    let key = kev.key();

    if key == "Escape" {
        let closed = STATE.with(|c| {
            let mut s = c.borrow_mut();
            let active = s.show_rubric_modal
                || s.show_straight_line_modal
                || s.show_discard_modal
                || s.receipt_modal_course.is_some()
                || s.mobile_menu_open;
            s.show_rubric_modal = false;
            s.show_straight_line_modal = false;
            s.show_discard_modal = false;
            s.receipt_modal_course = None;
            s.mobile_menu_open = false;
            active
        });
        if closed {
            render();
        }
        return;
    }

    if key == "Enter"
        && let Some(el) = kev.target().and_then(|t| t.dyn_into::<Element>().ok())
    {
        match el.id().as_str() {
            "login-identifier" | "login-password" => {
                kev.prevent_default();
                do_login_submit();
                return;
            }
            "pw-current" | "pw-next" => {
                kev.prevent_default();
                do_password_submit();
                return;
            }
            "verify-token-input" => {
                kev.prevent_default();
                do_verify_token();
                return;
            }
            "insights-q-input" => {
                kev.prevent_default();
                do_search_insights();
                return;
            }
            _ => {}
        }
    }

    // Keyboard 1..5 quick-rating when focused inside a `.statement-row`
    if matches!(key.as_str(), "1" | "2" | "3" | "4" | "5") {
        let target = kev.target().and_then(|t| t.dyn_into::<Element>().ok());
        let is_typing_field = target.as_ref().is_some_and(|el| {
            matches!(
                el.tag_name().to_ascii_uppercase().as_str(),
                "INPUT" | "TEXTAREA" | "SELECT"
            )
        });
        if !is_typing_field
            && let Some(row_idx) = find_likert_row(target)
            && let Ok(val) = key.parse::<i32>()
        {
            kev.prevent_default();
            STATE.with(|c| {
                let mut s = c.borrow_mut();
                if row_idx < 25 {
                    s.draft.ratings[row_idx] = val;
                    s.incomplete_indices.retain(|&x| x != row_idx);
                    save_draft(s.eval_section_id, &s.draft);
                }
            });
            render();
            if let Some(d) = doc()
                && let Some(next_el) = d
                    .get_element_by_id(&format!("likert-item-{}", (row_idx + 1).min(24)))
                    .and_then(|e| e.dyn_into::<HtmlElement>().ok())
            {
                let _ = next_el.focus();
            }
        }
    }
}

#[wasm_bindgen(start)]
pub fn start_app() {
    restore_session_from_storage();
    let initial_path = win()
        .and_then(|w| w.location().pathname().ok())
        .unwrap_or_else(|| "/".to_string());
    let guarded = enforce_route_guards(&sanitize_redirect_path(&initial_path, "/"));
    STATE.with(|c| c.borrow_mut().route = guarded.clone());
    update_page_title(&guarded);

    if let Some(d) = doc() {
        let on_click = Closure::<dyn FnMut(Event)>::new(handle_click);
        let _ = d.add_event_listener_with_callback("click", on_click.as_ref().unchecked_ref());
        on_click.forget();

        let on_input = Closure::<dyn FnMut(Event)>::new(handle_input);
        let _ = d.add_event_listener_with_callback("input", on_input.as_ref().unchecked_ref());
        on_input.forget();

        let on_change = Closure::<dyn FnMut(Event)>::new(handle_change);
        let _ = d.add_event_listener_with_callback("change", on_change.as_ref().unchecked_ref());
        on_change.forget();

        let on_key = Closure::<dyn FnMut(Event)>::new(handle_keydown);
        let _ = d.add_event_listener_with_callback("keydown", on_key.as_ref().unchecked_ref());
        on_key.forget();
    }

    if let Some(w) = win() {
        let on_pop = Closure::<dyn FnMut(Event)>::new(|_: Event| {
            let p = win()
                .and_then(|w| w.location().pathname().ok())
                .unwrap_or_else(|| "/".to_string());
            let g = enforce_route_guards(&sanitize_redirect_path(&p, "/"));
            STATE.with(|c| c.borrow_mut().route = g.clone());
            update_page_title(&g);
            render();
            load_route_data(&g);
        });
        let _ = w.add_event_listener_with_callback("popstate", on_pop.as_ref().unchecked_ref());
        on_pop.forget();
    }

    render();
    load_route_data(&guarded);
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn open_redirect_sanitizer_blocks_external_schemes_and_protocol_relative_urls() {
        assert_eq!(sanitize_redirect_path("/queue", "/"), "/queue");
        assert_eq!(sanitize_redirect_path("//evil.example/phish", "/"), "/");
        assert_eq!(sanitize_redirect_path("https://evil.example", "/"), "/");
        assert_eq!(sanitize_redirect_path("javascript:alert(1)", "/"), "/");
        assert_eq!(sanitize_redirect_path("/\\evil", "/"), "/");
    }

    #[test]
    fn html_escaping_and_uri_encoding_are_safe() {
        assert_eq!(
            esc("<script>alert(\"x\")</script>"),
            "&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;"
        );
        assert_eq!(percent_encode_uri("CS-401,SEC-A\n"), "CS-401%2CSEC-A%0A");
    }

    #[test]
    fn institutional_views_exclude_developer_jargon_and_eyebrow_pills() {
        let banned_terms = [
            "WebAssembly",
            "WASM",
            "SQLite",
            "UUIDv4",
            "sessionStorage",
            "evaluation_clearances",
            "evaluation_vault",
            "term_grades_submitted",
            "registrar_lock",
            "must_change_password",
            "0s+",
            "0S+",
            "&sigma;",
            "σ²",
            "Σ²",
            "TF-IDF",
            "VADER",
            "calamanCy",
            "Quivr",
            "Second Brain",
            "Ctrl+K",
            "Security Maxxxxxing",
            "Unsafe Rust",
            "Analyze in Rust",
            "mono-pill",
        ];

        let mut s = AppState::new();
        s.show_rubric_modal = true;
        s.show_straight_line_modal = true;
        s.show_discard_modal = true;
        s.queue_data = Some(json!({
            "completed_count": 1,
            "total_count": 2,
            "completion_percentage": 50.0,
            "courses": [
                {
                    "course_section_id": 1,
                    "course_code": "CS-401",
                    "course_title": "Design & Analysis of Algorithms",
                    "section_code": "SEC-A",
                    "faculty_name": "Prof. A. Dela Cruz",
                    "status": "CLEARED",
                    "clearance_token": "CLR-101"
                },
                {
                    "course_section_id": 2,
                    "course_code": "MATH-201",
                    "course_title": "Discrete Mathematics",
                    "section_code": "SEC-B",
                    "faculty_name": "Dr. R. Garcia",
                    "status": "PENDING"
                }
            ]
        }));
        s.receipt_modal_course = s.queue_data.as_ref().unwrap()["courses"][0].clone().into();
        s.faculty_sections = Some(json!({
            "sections": [
                {
                    "id": 1,
                    "course_code": "CS-401",
                    "course_title": "Design & Analysis of Algorithms",
                    "section_code": "SEC-A",
                    "term_grades_submitted": false,
                    "registrar_lock": false
                }
            ]
        }));
        s.dean_overview = Some(json!({
            "response_rate_pct": 88.5,
            "total_vault_submissions": 42,
            "overall_mean_score": 4.45,
            "sentiment": { "positive_pct": 80.0, "neutral_pct": 15.0, "negative_pct": 5.0 },
            "instructor_summaries": [
                {
                    "faculty_id": 10,
                    "faculty_name": "Prof. A. Dela Cruz",
                    "submission_count": 21,
                    "mean_score": 4.60,
                    "negative_pct": 4.0,
                    "watchlist_flagged": false
                }
            ]
        }));
        s.dean_insights = Some(json!({
            "results": [
                {
                    "course_code": "CS-401",
                    "pedagogical_tag": "Pedagogical Delivery",
                    "valence": "Positive",
                    "polarity": 0.888,
                    "comment_text": "Clear explanations and helpful examples."
                }
            ]
        }));
        s.admin_sections = s.faculty_sections.clone();
        s.admin_security = Some(json!({
            "enforced_controls": 63,
            "vault_anonymity": { "decoupled": true }
        }));

        let mut rendered = vec![
            render_navbar(&s),
            render_global_modals(&s),
            render_landing(&s),
            render_login(&s),
            render_password_change(&s),
            render_student_queue(&s),
        ];
        s.eval_section_id = 2;
        s.eval_step = 1;
        rendered.push(render_evaluation_form(&s));
        s.eval_step = 2;
        rendered.push(render_evaluation_form(&s));
        rendered.push(render_faculty_portal(&s));

        for role in ["student", "faculty", "chair", "dean", "admin"] {
            s.user = Some(UserSession {
                id: 1,
                institutional_id: "2026-001".to_string(),
                name: "Maria Clara".to_string(),
                email: "mc@aaaaa13.edu".to_string(),
                role: role.to_string(),
                role_label: role.to_string(),
                department_id: Some(1),
                department_name: Some("Department of Computer Science".to_string()),
                college_name: Some("College of Computing".to_string()),
                must_change_password: false,
            });
            rendered.push(render_navbar(&s));
        }

        for r in ["/chair", "/dean"] {
            s.route = r.to_string();
            for tab in ["summary", "support", "participation", "comments"] {
                s.dean_tab = tab.to_string();
                rendered.push(render_dean_or_chair(&s));
            }
        }

        s.route = "/admin".to_string();
        for tab in ["schedule", "grades", "roster", "privacy"] {
            s.admin_tab = tab.to_string();
            rendered.push(render_admin_console(&s));
        }

        let all_html = rendered.join("\n");
        for banned in banned_terms {
            assert!(
                !all_html.contains(banned),
                "Found banned term '{banned}' in rendered HTML"
            );
        }
        assert!(!all_html.contains("Rust"));
        assert!(!all_html.contains("+0.888"));
        assert!(all_html.contains("data-label=\"Subject Code\""));
        assert!(all_html.contains("A+ Large Text"));
        assert_eq!(
            sql_to_datetime_local("2026-08-15 08:30:00"),
            "2026-08-15T08:30"
        );
        assert_eq!(
            datetime_local_to_sql("2027-12-20T23:59", "59"),
            "2027-12-20 23:59:59"
        );
        // Verify pending course MATH-201 renders before completed course CS-401 in student queue
        let queue_html = render_student_queue(&s);
        let pending_pos = queue_html.find("MATH-201").expect("MATH-201 in queue");
        let completed_pos = queue_html.find("CS-401").expect("CS-401 in queue");
        assert!(
            pending_pos < completed_pos,
            "Pending subjects must be sorted before completed subjects"
        );
    }
}
