# EvaluaTech Production Deployment Guide

How to deploy **School AAAAA13 EvaluaTech** (`evaluatech-server` + `evaluatech-web` WASM + `nlp-python` ML engine) for a live institutional evaluation cycle.

## 1. Runtime Architecture in Production

| Component | Artifact / Path | Responsibility |
| :--- | :--- | :--- |
| **HTTP & Vault Server** | `target/release/evaluatech-server` (`.exe` on Windows) | Memory-safe Axum HTTP server, SQLite WAL persistence, `EVT.*` auth, 63 OWASP security controls, and embedded static asset serving. |
| **WebAssembly UI** | `web-dist/` (`pkg/evaluatech_web_bg.wasm` + `styles.css`) | Prebuilt TOPCIT-styled SPA: plain single-column flow for Students, Institutional Left Sidebar for Faculty, Chairs, Deans, and Registrar. |
| **Database** | `DATABASE_URL` (e.g. `/var/lib/evaluatech/evaluatech_aaaaa13.sqlite`) | Bundled SQLite 3 in WAL mode (`evaluation_clearances` physically decoupled from `evaluation_vault`). Include in encrypted daily backups. |
| **Python NLP & ML Worker** | `.venv/` + `nlp-python/` (`models/*.joblib`) | Spawned automatically as a warm UTF-8 JSON-RPC worker by `evaluatech-server` (`PYTHON_BIN`), running `tl_calamancy_md-0.2.0`, `en_core_web_md`, `vaderSentiment`, and calibrated `scikit-learn` models. |

---

## 2. Provision Python ML Environment & Build Release Binaries

```powershell
# 1. Create Python 3.12 virtual environment and install NLP/ML dependencies
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r nlp-python\requirements.txt

# 2. Pre-cache Tagalog calamanCy (tl_calamancy_md-0.2.0) & English spaCy (en_core_web_md) models
.\.venv\Scripts\python.exe -m spacy download en_core_web_md
.\.venv\Scripts\python.exe -c "import calamancy; calamancy.load('tl_calamancy_md-0.2.0')"

# 3. Verify trained scikit-learn artifacts & Python NLP suite
$env:PYTHONPATH = "$PWD\nlp-python"
.\.venv\Scripts\python.exe -m pytest nlp-python\tests -v

# 4. Build WebAssembly UI & release Rust server binary
cargo build -p evaluatech-web --target wasm32-unknown-unknown --release
wasm-bindgen "target/wasm32-unknown-unknown/release/evaluatech_web.wasm" --out-dir "web-dist/pkg" --target web --no-typescript
cargo build -p evaluatech-server --release
```

---

## 3. Configure Production Environment Variables

Never run a live cycle with `APP_ENV=local` (which seeds sample accounts on an empty database). Configure the following environment variables in your service manager (`systemd` or Windows Service):

| Variable | Required | Purpose |
| :--- | :---: | :--- |
| `APP_ENV=production` | **Yes** | Disables development demo seeding and enforces strict secret checks. |
| `HOST=127.0.0.1` | **Yes** | Bind behind a TLS-terminating reverse proxy (Nginx / Caddy / IIS HTTPS `443`). |
| `PORT=8080` | **Yes** | Internal loopback listener port forwarded by the HTTPS reverse proxy. |
| `DATABASE_URL` | **Yes** | Path to persistent SQLite database file on encrypted storage. |
| `EVALUATECH_SIGNING_SECRET` | **Yes** | Minimum 32-byte CSPRNG hex secret signing `EVT.*` Bearer tokens and dossier seals. |
| `EVALUATECH_ADMIN_PASSWORD` | First boot only | Strong initial password (`>= 10` chars, letters + digits) to bootstrap `ADM-00001` on an empty database; remove immediately after first start. |
| `PYTHON_BIN` | Recommended | Absolute path to `.venv/bin/python` (Linux) or `.venv\Scripts\python.exe` (Windows). |

Generate a signing secret securely:
```powershell
.\.venv\Scripts\python.exe -c "import secrets; print(secrets.token_hex(32))"
```

---

## 4. Load Rosters & Distribute One-Time Passwords

1. Sign in as the bootstrapped Registrar Administrator (`admin@aaaaa13.edu` / `ADM-00001`) at `/login`.
2. In the **Registrar Console (`/admin`)**, review the active **Academic Term** and **Evaluation Window** (`starts_at`, `ends_at`, `Asia/Manila`).
3. Import the semester class roster via **Class Roster Import** (CSV or JSON with `student_id`, `course_code`, `instructor_name`, and optional `section_code`, `department_code`, `faculty_email`). Imports are atomic: any malformed row rolls back the entire batch with a clear row-level error.
4. Provision **Department Chairs** and **College Deans** under **Academic Setup -> Users**, assigning each to their respective `department_id` / college scope.
5. Every newly created user receives a CSPRNG one-time password (`TMP-*`) stored strictly as a `bcrypt` hash with `must_change_password = 1`. Export the one-time password slip once, distribute securely, and delete the exported CSV afterward. On first sign-in, users are pinned to `/account/password` until they set a personal password.

---

## 5. Double-Blind Grade Release Lifecycle

Faculty members receive `HTTP 403 DOUBLE_BLIND_LOCKED` (rendered as the step-by-step **Pending Grade Release** screen on `/faculty`) until both conditions hold for a course section:
1. The instructor confirms final grade submission (`term_grades_submitted = 1`).
2. The University Registrar seals the section grade roster (`registrar_lock = 1`) in `/admin`.

Once unlocked, faculty reports expose aggregate Likert means, score histograms, Python `calamanCy` + `scikit-learn` sentiment summaries, and PII-scrubbed anonymous comments only.

---

## 6. Pre-Launch Verification Checklist

- [ ] `APP_ENV=production` and `EVALUATECH_SIGNING_SECRET` configured in secret store; `EVALUATECH_ADMIN_PASSWORD` removed after initial bootstrap.
- [ ] HTTPS (`TLS 1.2+`) enforced at the reverse proxy; plain HTTP redirected to HTTPS.
- [ ] `pytest nlp-python/tests -v` and `cargo test --workspace --all-features` pass 100% on the release build.
- [ ] `GET /api/nlp/provenance` reports `language_runtime: "Python"`, `calamancy_loaded: true`, and `ml_model_loaded: true`.
- [ ] Term window (`Asia/Manila`), department scopes, and SQLite backup rotation verified.
