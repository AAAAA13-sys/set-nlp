"""EvaluaTech Python NLP Microservice HTTP / Flask Application (calamanCy + VADER).

Exposes:
  - GET  /health
  - POST /analyze
  - POST /analyze/batch
  - POST /analyze/questionnaire

Works seamlessly with Flask when installed and provides a zero-dependency
standard-library HTTP/WSGI handler (`handle_request`, `EvaluaTechNLPRequestHandler`,
and a Flask-compatible `create_app().test_client()`) when Flask is not installed.
"""

from __future__ import annotations

import json
import os
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any
from urllib.parse import urlsplit

from .engine import (
    CALAMANCY_DEFAULT_MODEL,
    CALAMANCY_REPO_URL,
    FIVE_LIKERT_CLUSTERS,
    MAX_BATCH_SIZE,
    MAX_COMMENT_LENGTH,
    MAX_PROFESSOR_QUESTIONS,
    PEDAGOGICAL_TAGS,
    TAG_UNCLASSIFIED,
    ALL_PEDAGOGICAL_TAGS,
    get_engine_provenance,
    analyze_batch,
    analyze_comment,
    analyze_plp_section_dossier,
    analyze_professor_questions,
    generate_pedagogical_action_plan,
    scrub_pii,
    summarize_comments,
    synthesize_professor_questionnaires,
)

MAX_HTTP_BODY_BYTES: int = 2_097_152

try:
    from flask import Flask, jsonify, request  # type: ignore[import-not-found,import-untyped]
except ImportError:  # pragma: no cover - fallback active when Flask is not installed
    Flask = None
    jsonify = None
    request = None


def _parse_body(body: Any) -> tuple[bool, Any]:
    """Parse raw request body into a Python object."""
    if body is None:
        return True, None
    if isinstance(body, (dict, list)):
        return True, body
    if isinstance(body, bytes):
        try:
            decoded = body.decode("utf-8")
        except UnicodeDecodeError:
            return False, None
        body = decoded
    if isinstance(body, str):
        stripped = body.strip()
        if not stripped:
            return True, None
        try:
            return True, json.loads(stripped)
        except json.JSONDecodeError:
            return False, None
    return False, None


def handle_request(method: str, path: str, body: Any = None) -> tuple[int, dict[str, Any]]:
    """Core stateless router for EvaluaTech NLP microservice endpoints."""
    http_method = (method or "GET").upper().strip()
    clean_path = urlsplit(path or "/").path.rstrip("/") or "/"

    if clean_path == "/health":
        if http_method != "GET":
            return 405, {"error": "Method not allowed."}
        prov = get_engine_provenance()
        return 200, {
            "status": "ok",
            "service": "evaluatech-nlp-analytics",
            "version": "1.2.0",
            "nlp_engine": prov["nlp_engine"],
            "runtime_engine": prov["runtime_engine"],
            "calamancy_loaded": prov["calamancy_loaded"],
            "fallback_heuristic": prov["fallback_heuristic"],
            "vader_loaded": prov["vader_loaded"],
            "loaded_dependencies": prov["dependencies"],
            "calamancy_model": prov["calamancy_model"],
            "calamancy_repo": prov["calamancy_repo"],
            "pedagogical_tags": list(PEDAGOGICAL_TAGS),
            "unclassified_tag": TAG_UNCLASSIFIED,
            "all_pedagogical_tags": list(ALL_PEDAGOGICAL_TAGS),
            "likert_clusters": list(FIVE_LIKERT_CLUSTERS),
            "max_professor_questions": MAX_PROFESSOR_QUESTIONS,
        }

    if clean_path == "/analyze":
        if http_method != "POST":
            return 405, {"error": "Method not allowed."}
        if isinstance(body, (bytes, str)) and len(body) > MAX_HTTP_BODY_BYTES:
            return 413, {"error": "Payload exceeds maximum allowed size.", "code": "PAYLOAD_TOO_LARGE"}
        ok, payload = _parse_body(body)
        if not ok or not isinstance(payload, dict):
            return 400, {"error": "Invalid JSON object payload."}
        if "comment" not in payload and "text" not in payload:
            return 422, {"error": "Field 'comment' or 'text' is required."}
        raw_text = payload.get("comment") if "comment" in payload else payload.get("text")
        if isinstance(raw_text, (dict, list)):
            return 422, {"error": "Field 'comment' must be a scalar string.", "code": "INVALID_COMMENT_TYPE"}
        if isinstance(raw_text, str) and len(raw_text) > MAX_COMMENT_LENGTH:
            return 422, {
                "error": f"Comment exceeds maximum length of {MAX_COMMENT_LENGTH} characters.",
                "code": "COMMENT_TOO_LONG",
            }
        result = analyze_comment(raw_text)
        result["scrubbed_preview"] = scrub_pii(raw_text) if isinstance(raw_text, str) else ""
        if "professor_questions" in payload and isinstance(payload["professor_questions"], dict):
            result["professor_questions"] = analyze_professor_questions(payload["professor_questions"])
        return 200, result

    if clean_path in ("/analyze/questionnaire", "/analyze/questionnaires"):
        if http_method != "POST":
            return 405, {"error": "Method not allowed."}
        ok, payload = _parse_body(body)
        if not ok or payload is None:
            return 400, {"error": "Invalid JSON object payload."}
        if isinstance(payload, list):
            synthesis = synthesize_professor_questionnaires(payload)
            return 200, synthesis
        if not isinstance(payload, dict):
            return 400, {"error": "Invalid JSON object payload."}
        if "questionnaires" in payload and isinstance(payload["questionnaires"], list):
            synthesis = synthesize_professor_questionnaires(payload["questionnaires"])
            return 200, synthesis
        analysis = analyze_professor_questions(payload)
        status = 200 if analysis.get("valid", False) or "error_code" not in analysis else 422
        return status, analysis

    if clean_path in ("/analyze/synthesize", "/analyze/questionnaires/synthesize", "/analyze/questionnaire/synthesize"):
        if http_method != "POST":
            return 405, {"error": "Method not allowed."}
        ok, payload = _parse_body(body)
        if not ok or payload is None:
            return 400, {"error": "Invalid JSON payload."}
        if isinstance(payload, list):
            items = payload
        elif isinstance(payload, dict):
            items = payload.get("questionnaires", payload.get("items", [payload]))
        else:
            items = []
        synthesis = synthesize_professor_questionnaires(items)
        return 200, synthesis

    if clean_path == "/analyze/batch":
        if http_method != "POST":
            return 405, {"error": "Method not allowed."}
        ok, payload = _parse_body(body)
        if not ok or payload is None:
            return 400, {"error": "Invalid JSON payload."}

        if isinstance(payload, list):
            comments = payload
        elif isinstance(payload, dict):
            comments = payload.get("comments") if "comments" in payload else payload.get("texts")
        else:
            comments = None

        if not isinstance(comments, list):
            return 422, {"error": "Field 'comments' must be a list."}
        if len(comments) > MAX_BATCH_SIZE:
            return 422, {
                "error": f"Batch size exceeds maximum of {MAX_BATCH_SIZE} comments.",
                "code": "BATCH_LIMIT_EXCEEDED",
            }

        results = analyze_batch(comments)
        summary = summarize_comments(results)
        return 200, {
            "count": len(results),
            "results": results,
            "summary": summary,
        }

    if clean_path in ("/analyze/dossier", "/analyze-dossier"):
        if http_method != "POST":
            return 405, {"error": "Method not allowed."}
        ok, payload = _parse_body(body)
        if not ok or not isinstance(payload, dict):
            return 400, {"error": "Invalid JSON object payload."}
        comments = payload.get("comments", payload.get("comment", []))
        cluster_ratings = payload.get("cluster_ratings", payload.get("ratings"))
        professor_questions = payload.get("professor_questions", payload.get("qualitative_questions"))
        dossier = analyze_plp_section_dossier(
            comments=comments,
            cluster_ratings=cluster_ratings,
            professor_questions=professor_questions,
        )
        return 200, dossier

    if clean_path in ("/analyze/intervention-plan", "/analyze/action-plan", "/analyze/intervention"):
        if http_method != "POST":
            return 405, {"error": "Method not allowed."}
        ok, payload = _parse_body(body)
        if not ok or not isinstance(payload, dict):
            return 400, {"error": "Invalid JSON object payload."}
        faculty_name = payload.get("faculty_name", "Faculty Member")
        mean_score = payload.get("mean_score", 3.10)
        negative_sentiment_pct = payload.get("negative_sentiment_pct", 38.0)
        primary_tag = payload.get("primary_tag", "Pedagogical Delivery")
        plan = generate_pedagogical_action_plan(
            faculty_name=faculty_name,
            mean_score=mean_score,
            negative_sentiment_pct=negative_sentiment_pct,
            primary_tag=primary_tag,
        )
        return 200, plan

    return 404, {"error": "Endpoint not found."}


class EvaluaTechNLPRequestHandler(BaseHTTPRequestHandler):
    """Standard-library HTTP request handler for EvaluaTech NLP microservice."""

    def _dispatch(self, method: str) -> None:
        content_length = int(self.headers.get("Content-Length", "0") or "0")
        raw_body = self.rfile.read(content_length) if content_length > 0 else None
        status_code, response_payload = handle_request(method, self.path, raw_body)
        encoded = json.dumps(response_payload).encode("utf-8")

        self.send_response(status_code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(encoded)))
        self.end_headers()
        self.wfile.write(encoded)

    def do_GET(self) -> None:  # noqa: N802
        self._dispatch("GET")

    def do_POST(self) -> None:  # noqa: N802
        self._dispatch("POST")

    def log_message(self, format: str, *args: Any) -> None:  # noqa: A002
        return


class _FallbackResponse:
    """Flask-compatible test response wrapper when Flask is not installed."""

    def __init__(self, status_code: int, payload: dict[str, Any]) -> None:
        self.status_code = status_code
        self._payload = payload
        self.data = json.dumps(payload).encode("utf-8")

    @property
    def json(self) -> dict[str, Any]:
        return self._payload

    def get_json(self) -> dict[str, Any]:
        return self._payload


class _FallbackTestClient:
    """Flask-compatible test client delegating directly to `handle_request`."""

    def get(self, path: str, **kwargs: Any) -> _FallbackResponse:
        status_code, payload = handle_request("GET", path, kwargs.get("json") or kwargs.get("data"))
        return _FallbackResponse(status_code, payload)

    def post(self, path: str, **kwargs: Any) -> _FallbackResponse:
        body = kwargs.get("json") if "json" in kwargs else kwargs.get("data")
        status_code, payload = handle_request("POST", path, body)
        return _FallbackResponse(status_code, payload)

    def put(self, path: str, **kwargs: Any) -> _FallbackResponse:
        body = kwargs.get("json") if "json" in kwargs else kwargs.get("data")
        status_code, payload = handle_request("PUT", path, body)
        return _FallbackResponse(status_code, payload)

    def delete(self, path: str, **kwargs: Any) -> _FallbackResponse:
        status_code, payload = handle_request("DELETE", path)
        return _FallbackResponse(status_code, payload)


class _FallbackApp:
    """Minimal Flask-compatible application wrapper for zero-dependency environments."""

    def __init__(self) -> None:
        self.config: dict[str, Any] = {"TESTING": False}

    def test_client(self) -> _FallbackTestClient:
        return _FallbackTestClient()


def create_app() -> Any:
    """Create the Flask application (or zero-dependency fallback app)."""
    if Flask is None:
        return _FallbackApp()

    flask_app = Flask(__name__)

    @flask_app.route("/health", methods=["GET", "POST", "PUT", "DELETE"])
    def health_endpoint() -> Any:
        status_code, payload = handle_request(request.method, "/health")
        return jsonify(payload), status_code

    @flask_app.route("/analyze", methods=["GET", "POST", "PUT", "DELETE"])
    def analyze_endpoint() -> Any:
        raw_body = request.get_data(as_text=True)
        status_code, payload = handle_request(request.method, "/analyze", raw_body)
        return jsonify(payload), status_code

    @flask_app.route("/analyze/questionnaire", methods=["GET", "POST", "PUT", "DELETE"])
    def analyze_questionnaire_endpoint() -> Any:
        raw_body = request.get_data(as_text=True)
        status_code, payload = handle_request(request.method, "/analyze/questionnaire", raw_body)
        return jsonify(payload), status_code

    @flask_app.route("/analyze/batch", methods=["GET", "POST", "PUT", "DELETE"])
    def analyze_batch_endpoint() -> Any:
        raw_body = request.get_data(as_text=True)
        status_code, payload = handle_request(request.method, "/analyze/batch", raw_body)
        return jsonify(payload), status_code

    @flask_app.route("/analyze/dossier", methods=["GET", "POST", "PUT", "DELETE"])
    @flask_app.route("/analyze-dossier", methods=["GET", "POST", "PUT", "DELETE"])
    def analyze_dossier_endpoint() -> Any:
        raw_body = request.get_data(as_text=True)
        status_code, payload = handle_request(request.method, "/analyze/dossier", raw_body)
        return jsonify(payload), status_code

    return flask_app


app = create_app()


def run_server(host: str = "127.0.0.1", port: int = 5001) -> None:  # pragma: no cover
    """Start the EvaluaTech NLP HTTP service."""
    if Flask is not None and hasattr(app, "run"):
        app.run(host=host, port=port, debug=False)
        return
    server = ThreadingHTTPServer((host, port), EvaluaTechNLPRequestHandler)
    try:
        server.serve_forever()
    finally:
        server.server_close()


if __name__ == "__main__":  # pragma: no cover
    listen_host = os.environ.get("NLP_HOST", "127.0.0.1")
    listen_port = int(os.environ.get("NLP_PORT", "5001"))
    run_server(listen_host, listen_port)
