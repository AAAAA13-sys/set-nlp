"""Persistent warm JSON-lines stdio worker bridged directly by `evaluatech-server` (Rust)."""

from __future__ import annotations

import json
import sys
from typing import Any

from .engine import (
    MAX_BATCH_SIZE,
    analyze_comment,
    analyze_comment_item,
    analyze_professor_questions,
    generate_pedagogical_action_plan,
    get_engine_provenance,
    summarize_comments,
)


def handle_rpc(req: dict[str, Any]) -> dict[str, Any]:
    op = req.get("op", "analyze")
    if op == "provenance":
        return {"ok": True, "data": get_engine_provenance()}
    if op == "analyze":
        text = str(req.get("text") or req.get("comment") or "")
        return {"ok": True, "data": analyze_comment(text)}
    if op == "batch":
        comments = req.get("comments") or []
        if len(comments) > MAX_BATCH_SIZE:
            return {
                "ok": False,
                "code": "BATCH_LIMIT_EXCEEDED",
                "message": f"Batch exceeds maximum of {MAX_BATCH_SIZE} comments.",
            }
        results = [analyze_comment_item(it) for it in comments]
        return {
            "ok": True,
            "data": {"results": results, "summary": summarize_comments(results)},
        }
    if op == "summarize":
        items = req.get("items") or []
        return {"ok": True, "data": summarize_comments(items)}
    if op == "action_plan":
        return {
            "ok": True,
            "data": generate_pedagogical_action_plan(
                str(req.get("faculty_name") or "Faculty Member"),
                float(req.get("mean_score") or 3.5),
                float(req.get("negative_sentiment_pct") or 0.0),
                req.get("primary_tag"),
            ),
        }
    if op == "professor_questions":
        return {"ok": True, "data": analyze_professor_questions(req.get("payload") or {})}
    return {"ok": False, "code": "UNKNOWN_OP", "message": f"Unsupported op: {op}"}


def main() -> None:
    # Ensure UTF-8 unbuffered line I/O on Windows & Linux
    sys.stdin.reconfigure(encoding="utf-8")  # type: ignore[union-attr]
    sys.stdout.reconfigure(encoding="utf-8", line_buffering=True)  # type: ignore[union-attr]

    # Warm up calamanCy + VADER + scikit-learn models once on startup
    _ = get_engine_provenance()

    for raw_line in sys.stdin:
        line = raw_line.strip()
        if not line:
            continue
        try:
            payload = json.loads(line)
            resp = handle_rpc(payload)
        except Exception as exc:  # pragma: no cover
            resp = {"ok": False, "code": "WORKER_EXCEPTION", "message": str(exc)}
        sys.stdout.write(json.dumps(resp, ensure_ascii=False) + "\n")
        sys.stdout.flush()


if __name__ == "__main__":
    main()
