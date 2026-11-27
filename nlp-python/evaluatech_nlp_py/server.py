"""FastAPI HTTP Microservice for School AAAAA13 EvaluaTech Python NLP & Sentiment ML (`server.py`)."""

from __future__ import annotations

from typing import Any

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from .engine import (
    MAX_BATCH_SIZE,
    analyze_comment,
    analyze_comment_item,
    generate_pedagogical_action_plan,
    get_engine_provenance,
    summarize_comments,
)

app = FastAPI(
    title="School AAAAA13 EvaluaTech — Python calamanCy + VADER + scikit-learn NLP Service",
    version="1.0.0",
)


class AnalyzeRequest(BaseModel):
    comment: str | None = None
    text: str | None = None
    comments: list[Any] | None = None


class ActionPlanRequest(BaseModel):
    faculty_name: str = Field(default="Faculty Member")
    mean_score: float = Field(default=3.5)
    negative_sentiment_pct: float = Field(default=0.0)
    primary_tag: str | None = None


@app.get("/health")
def health() -> dict[str, Any]:
    return {
        "status": "healthy",
        "service": "School AAAAA13 EvaluaTech Python NLP & ML Service",
        "provenance": get_engine_provenance(),
    }


@app.post("/analyze")
def analyze_endpoint(req: AnalyzeRequest) -> dict[str, Any]:
    if req.comments is not None:
        if len(req.comments) > MAX_BATCH_SIZE:
            raise HTTPException(
                status_code=422,
                detail={
                    "code": "BATCH_LIMIT_EXCEEDED",
                    "message": f"Batch exceeds maximum of {MAX_BATCH_SIZE} comments.",
                },
            )
        results = [analyze_comment_item(it) for it in req.comments]
        summary = summarize_comments(results)
        return {"results": results, "summary": summary}

    raw = req.comment if req.comment is not None else (req.text or "")
    return analyze_comment(raw)


@app.post("/action-plan")
def action_plan_endpoint(req: ActionPlanRequest) -> dict[str, Any]:
    return generate_pedagogical_action_plan(
        req.faculty_name,
        req.mean_score,
        req.negative_sentiment_pct,
        req.primary_tag,
    )
