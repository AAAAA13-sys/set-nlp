//! Persistent Warm Bridge to the Python `calamanCy` + `vaderSentiment` + `scikit-learn` Engine (`nlp-python/`).

use std::io::{BufRead, BufReader, Write};
use std::path::PathBuf;
use std::process::{Child, ChildStdin, ChildStdout, Command, Stdio};
use std::sync::{Mutex, OnceLock};

use evaluatech_nlp::{PEDAGOGICAL_TAGS, scrub_pii};
use serde_json::{Value, json};

struct WarmPythonProcess {
    child: Child,
    stdin: ChildStdin,
    stdout: BufReader<ChildStdout>,
}

impl Drop for WarmPythonProcess {
    fn drop(&mut self) {
        let _ = self.child.kill();
        let _ = self.child.wait();
    }
}

pub struct PythonNlpBridge {
    worker: Mutex<Option<WarmPythonProcess>>,
    nlp_dir: PathBuf,
    python_bin: PathBuf,
}

fn resolve_nlp_python_dir() -> PathBuf {
    let manifest_rel = PathBuf::from(env!("CARGO_MANIFEST_DIR"))
        .parent()
        .map(|p| p.join("nlp-python"))
        .unwrap_or_else(|| PathBuf::from("nlp-python"));
    if manifest_rel.exists() {
        return manifest_rel;
    }
    PathBuf::from("nlp-python")
}

fn resolve_python_bin(nlp_dir: &std::path::Path) -> PathBuf {
    if let Ok(explicit) = std::env::var("PYTHON_BIN")
        && !explicit.trim().is_empty()
    {
        return PathBuf::from(explicit);
    }
    if let Some(repo_root) = nlp_dir.parent() {
        let win_venv = repo_root.join(".venv").join("Scripts").join("python.exe");
        if win_venv.exists() {
            return win_venv;
        }
        let unix_venv = repo_root.join(".venv").join("bin").join("python");
        if unix_venv.exists() {
            return unix_venv;
        }
    }
    PathBuf::from("python")
}

impl PythonNlpBridge {
    fn new() -> Self {
        let nlp_dir = resolve_nlp_python_dir();
        let python_bin = resolve_python_bin(&nlp_dir);
        Self {
            worker: Mutex::new(None),
            nlp_dir,
            python_bin,
        }
    }

    fn spawn_worker(&self) -> Result<WarmPythonProcess, String> {
        let mut child = Command::new(&self.python_bin)
            .args(["-u", "-m", "evaluatech_nlp_py.worker"])
            .env("PYTHONPATH", &self.nlp_dir)
            .env("PYTHONIOENCODING", "utf-8")
            .stdin(Stdio::piped())
            .stdout(Stdio::piped())
            .stderr(Stdio::null())
            .spawn()
            .map_err(|e| {
                format!(
                    "Failed to launch Python NLP worker ({:?}): {e}",
                    self.python_bin
                )
            })?;

        let stdin = child
            .stdin
            .take()
            .ok_or_else(|| "Python worker stdin unavailable".to_string())?;
        let stdout = child
            .stdout
            .take()
            .ok_or_else(|| "Python worker stdout unavailable".to_string())?;

        Ok(WarmPythonProcess {
            child,
            stdin,
            stdout: BufReader::new(stdout),
        })
    }

    pub fn call(&self, payload: &Value) -> Result<Value, String> {
        let mut guard = self.worker.lock().expect("python nlp lock");
        for attempt in 0..2 {
            if guard.is_none() {
                *guard = Some(self.spawn_worker()?);
            }
            let proc = guard.as_mut().expect("worker present");
            let mut line_out = payload.to_string();
            line_out.push('\n');

            let io_res: Result<String, String> = (|| {
                proc.stdin
                    .write_all(line_out.as_bytes())
                    .map_err(|e| e.to_string())?;
                proc.stdin.flush().map_err(|e| e.to_string())?;
                let mut resp_line = String::new();
                let n = proc
                    .stdout
                    .read_line(&mut resp_line)
                    .map_err(|e| e.to_string())?;
                if n == 0 {
                    return Err("Python NLP worker closed stdout".to_string());
                }
                Ok(resp_line)
            })();

            match io_res {
                Ok(raw) => {
                    let parsed: Value = serde_json::from_str(&raw)
                        .map_err(|e| format!("Invalid worker JSON: {e}"))?;
                    if parsed.get("ok").and_then(Value::as_bool) == Some(true) {
                        return Ok(parsed.get("data").cloned().unwrap_or(Value::Null));
                    }
                    let msg = parsed
                        .get("message")
                        .and_then(Value::as_str)
                        .unwrap_or("Python NLP worker error");
                    return Err(msg.to_string());
                }
                Err(err) => {
                    *guard = None;
                    if attempt == 1 {
                        return Err(err);
                    }
                }
            }
        }
        Err("Python NLP worker unreachable".to_string())
    }
}

pub fn python_nlp() -> &'static PythonNlpBridge {
    static BRIDGE: OnceLock<PythonNlpBridge> = OnceLock::new();
    BRIDGE.get_or_init(PythonNlpBridge::new)
}

pub fn get_engine_provenance() -> Value {
    python_nlp()
        .call(&json!({ "op": "provenance" }))
        .unwrap_or_else(|e| {
            json!({
                "nlp_engine": "Python calamanCy + VADER + scikit-learn",
                "language_runtime": "Python",
                "status": "degraded",
                "error": e,
            })
        })
}

pub fn analyze_comment_str(raw: &str) -> Value {
    let scrubbed = scrub_pii(raw);
    python_nlp()
        .call(&json!({ "op": "analyze", "text": scrubbed }))
        .unwrap_or_else(|_| {
            json!({
                "polarity": 0.0,
                "valence": "Neutral",
                "pedagogical_tag": "Pedagogical Delivery",
                "scrubbed_preview": scrubbed,
                "nlp_engine": "Python calamanCy + VADER + scikit-learn (degraded)",
            })
        })
}

pub fn analyze_batch(comments: &[Value]) -> Result<Value, String> {
    python_nlp().call(&json!({ "op": "batch", "comments": comments }))
}

pub fn summarize_comments(items: &[Value]) -> Value {
    python_nlp()
        .call(&json!({ "op": "summarize", "items": items }))
        .unwrap_or_else(|_| {
            let mut tb = serde_json::Map::new();
            for t in PEDAGOGICAL_TAGS {
                tb.insert(t.to_string(), json!(0));
            }
            json!({
                "total": items.len(),
                "positive_count": 0,
                "neutral_count": items.len(),
                "negative_count": 0,
                "positive_pct": 0.0,
                "neutral_pct": if items.is_empty() { 0.0 } else { 100.0 },
                "negative_pct": 0.0,
                "mean_polarity": 0.0,
                "primary_tag": "Pedagogical Delivery",
                "primary_negative_tag": "Pedagogical Delivery",
                "unclassified_count": 0,
                "tag_breakdown": tb,
                "watchlist_flagged": false,
            })
        })
}

pub fn generate_pedagogical_action_plan(
    faculty_name: &str,
    mean_score: f64,
    negative_sentiment_pct: f64,
    primary_tag: Option<&str>,
) -> Value {
    python_nlp()
        .call(&json!({
            "op": "action_plan",
            "faculty_name": faculty_name,
            "mean_score": mean_score,
            "negative_sentiment_pct": negative_sentiment_pct,
            "primary_tag": primary_tag,
        }))
        .unwrap_or_else(|_| {
            json!({
                "plan_id": "AAAAA13-ACT-FALLBACK",
                "faculty_name": faculty_name,
                "mean_score": mean_score,
                "negative_sentiment_pct": negative_sentiment_pct,
                "primary_cluster": primary_tag.unwrap_or("Pedagogical Delivery"),
                "urgency_level": "MODERATE",
                "recommended_interventions": [
                    "Schedule collegial syllabus, pacing, and rubric review consultation."
                ],
                "nlp_engine": "Python calamanCy + VADER + scikit-learn",
            })
        })
}
