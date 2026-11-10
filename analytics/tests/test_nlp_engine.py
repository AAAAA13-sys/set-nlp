"""Comprehensive 40-Test Suite for EvaluaTech NLP Sentiment & Pedagogical Classification Engine."""

from __future__ import annotations

import json
import threading
import unittest
from http.server import ThreadingHTTPServer
from urllib.request import Request, urlopen

from analytics.app import EvaluaTechNLPRequestHandler, create_app, handle_request
from analytics.engine import (
    CALAMANCY_REPO_URL,
    FIVE_LIKERT_CLUSTERS,
    MAX_PROFESSOR_QUESTIONS,
    PEDAGOGICAL_TAGS,
    TAG_UNCLASSIFIED,
    ALL_PEDAGOGICAL_TAGS,
    VALENCE_LABELS,
    analyze_batch,
    analyze_comment,
    analyze_professor_questions,
    classify_pedagogical_domain,
    classify_valence,
    extract_calamancy_features,
    generate_pedagogical_action_plan,
    summarize_comments,
    synthesize_professor_questionnaires,
)


class EvaluaTechNLPEngineTestSuite(unittest.TestCase):
    """40 unit and integration tests for EvaluaTech's NLP sentiment and topic engine."""

    def setUp(self) -> None:
        self.app = create_app()
        self.client = self.app.test_client()

    # -------------------------------------------------------------------------
    # 1 - 3: Chapter III Canonical Benchmark Comments
    # -------------------------------------------------------------------------

    def test_01_chapter_iii_benchmark_pedagogical_delivery_positive(self) -> None:
        comment = "Explains complex algorithms very clearly and provides great practical code examples."
        result = analyze_comment(comment)
        self.assertEqual(result["valence"], "Positive")
        self.assertGreaterEqual(result["polarity"], 0.70)
        self.assertAlmostEqual(result["polarity"], 0.78, places=2)
        self.assertEqual(result["pedagogical_tag"], "Pedagogical Delivery")
        self.assertGreaterEqual(result["confidence"], 0.80)
        self.assertTrue(len(result["keywords"]) > 0)

    def test_02_chapter_iii_benchmark_assessment_grading_negative(self) -> None:
        comment = "Takes over a month to return quiz scores and provides no helpful feedback."
        result = analyze_comment(comment)
        self.assertEqual(result["valence"], "Negative")
        self.assertLessEqual(result["polarity"], -0.50)
        self.assertAlmostEqual(result["polarity"], -0.62, places=2)
        self.assertEqual(result["pedagogical_tag"], "Assessment & Grading")
        self.assertGreaterEqual(result["confidence"], 0.80)
        self.assertIn("quiz", result["keywords"])

    def test_03_chapter_iii_benchmark_classroom_management_negative(self) -> None:
        comment = "Arrives to morning lectures 15 minutes late consistently."
        result = analyze_comment(comment)
        self.assertEqual(result["valence"], "Negative")
        self.assertLessEqual(result["polarity"], -0.35)
        self.assertAlmostEqual(result["polarity"], -0.45, places=2)
        self.assertEqual(result["pedagogical_tag"], "Classroom Management")
        self.assertGreaterEqual(result["confidence"], 0.80)
        self.assertIn("late", result["keywords"])

    # -------------------------------------------------------------------------
    # 4 - 9: Polarity Bounds and Valence Threshold Cutoffs
    # -------------------------------------------------------------------------

    def test_04_valence_threshold_exact_positive_boundary(self) -> None:
        self.assertEqual(classify_valence(0.05), "Positive")
        self.assertEqual(classify_valence(0.50), "Positive")
        self.assertEqual(classify_valence(1.00), "Positive")

    def test_05_valence_threshold_exact_negative_boundary(self) -> None:
        self.assertEqual(classify_valence(-0.05), "Negative")
        self.assertEqual(classify_valence(-0.50), "Negative")
        self.assertEqual(classify_valence(-1.00), "Negative")

    def test_06_valence_threshold_neutral_interior_band(self) -> None:
        self.assertEqual(classify_valence(0.0), "Neutral")
        self.assertEqual(classify_valence(0.0499), "Neutral")
        self.assertEqual(classify_valence(-0.0499), "Neutral")

    def test_07_polarity_upper_bound_never_exceeds_plus_one(self) -> None:
        extreme_pos = (
            "Excellent outstanding brilliant amazing wonderful superb phenomenal "
            "exemplary inspiring masterful clear helpful fair punctual!!!"
        )
        result = analyze_comment(extreme_pos)
        self.assertGreaterEqual(result["polarity"], -1.0)
        self.assertLessEqual(result["polarity"], 1.0)
        self.assertGreater(result["polarity"], 0.90)
        self.assertEqual(result["valence"], "Positive")

    def test_08_polarity_lower_bound_never_below_minus_one(self) -> None:
        extreme_neg = (
            "Terrible horrible awful worst abysmal atrocious incompetent useless "
            "unfair rude arrogant disrespectful disorganized chaotic!!!"
        )
        result = analyze_comment(extreme_neg)
        self.assertGreaterEqual(result["polarity"], -1.0)
        self.assertLessEqual(result["polarity"], 1.0)
        self.assertLess(result["polarity"], -0.90)
        self.assertEqual(result["valence"], "Negative")

    def test_09_output_schema_keys_and_rounding_precision(self) -> None:
        result = analyze_comment("Clear explanations and well-organized lessons.")
        expected_keys = {"polarity", "valence", "pedagogical_tag", "confidence", "keywords"}
        self.assertTrue(expected_keys.issubset(result.keys()))
        self.assertIsInstance(result["polarity"], float)
        self.assertEqual(result["polarity"], round(result["polarity"], 4))
        self.assertIn(result["valence"], VALENCE_LABELS)
        self.assertIn(result["pedagogical_tag"], PEDAGOGICAL_TAGS)
        self.assertGreaterEqual(result["confidence"], 0.0)
        self.assertLessEqual(result["confidence"], 1.0)

    # -------------------------------------------------------------------------
    # 10 - 17: Four-Domain Pedagogical Classification Coverage
    # -------------------------------------------------------------------------

    def test_10_pedagogical_delivery_positive_classification(self) -> None:
        result = analyze_comment(
            "The instructor demonstrates deep subject matter mastery and explains concepts step-by-step."
        )
        self.assertEqual(result["pedagogical_tag"], "Pedagogical Delivery")
        self.assertEqual(result["valence"], "Positive")

    def test_11_pedagogical_delivery_negative_classification(self) -> None:
        result = analyze_comment(
            "Just reads directly from slides in a confusing monotone and teaching pacing is rushed."
        )
        self.assertEqual(result["pedagogical_tag"], "Pedagogical Delivery")
        self.assertEqual(result["valence"], "Negative")

    def test_12_assessment_and_grading_positive_classification(self) -> None:
        result = analyze_comment(
            "Exams and quizzes are fair, grading rubrics are clear, and feedback is returned promptly."
        )
        self.assertEqual(result["pedagogical_tag"], "Assessment & Grading")
        self.assertEqual(result["valence"], "Positive")

    def test_13_assessment_and_grading_negative_classification(self) -> None:
        result = analyze_comment(
            "Midterm exam grading was arbitrary with no rubric and delayed homework scores."
        )
        self.assertEqual(result["pedagogical_tag"], "Assessment & Grading")
        self.assertEqual(result["valence"], "Negative")

    def test_14_classroom_management_positive_classification(self) -> None:
        result = analyze_comment(
            "Always punctual, starts class on time, follows the syllabus schedule, and keeps order."
        )
        self.assertEqual(result["pedagogical_tag"], "Classroom Management")
        self.assertEqual(result["valence"], "Positive")

    def test_15_classroom_management_negative_classification(self) -> None:
        result = analyze_comment(
            "Frequently cancels scheduled class sessions, poor time management, and ignores classroom noise."
        )
        self.assertEqual(result["pedagogical_tag"], "Classroom Management")
        self.assertEqual(result["valence"], "Negative")

    def test_16_professional_conduct_positive_classification(self) -> None:
        result = analyze_comment(
            "Very respectful, empathetic, approachable, and maintains a welcoming professional conduct."
        )
        self.assertEqual(result["pedagogical_tag"], "Professional Conduct")
        self.assertEqual(result["valence"], "Positive")

    def test_17_professional_conduct_negative_classification(self) -> None:
        result = analyze_comment(
            "Rude, condescending, and disrespectful attitude that humiliates students in front of peers."
        )
        self.assertEqual(result["pedagogical_tag"], "Professional Conduct")
        self.assertEqual(result["valence"], "Negative")
        # Verify 5th Cluster (Student Support & Mentorship) & Tagalog calamanCy classification
        mentorship = analyze_comment(
            "Sobrang matulungin sa consultation at laging available sa office hours para sa thesis guidance."
        )
        self.assertEqual(mentorship["pedagogical_tag"], "Student Support & Mentorship")
        self.assertEqual(mentorship["valence"], "Positive")
        self.assertEqual(mentorship["calamancy"]["framework"], "calamanCy")
        self.assertEqual(mentorship["calamancy"]["repo"], CALAMANCY_REPO_URL)
        self.assertEqual(len(FIVE_LIKERT_CLUSTERS), 5)

    # -------------------------------------------------------------------------
    # 18 - 24: Linguistic Nuance (Negation, Boosters, Contrast, Emphasis)
    # -------------------------------------------------------------------------

    def test_18_negation_inverts_positive_sentiment_to_negative(self) -> None:
        pos = analyze_comment("The explanations are clear and helpful.")
        neg = analyze_comment("The explanations are not clear and never helpful.")
        self.assertEqual(pos["valence"], "Positive")
        self.assertEqual(neg["valence"], "Negative")
        self.assertLess(neg["polarity"], 0.0)

    def test_19_contraction_negation_handling(self) -> None:
        result = analyze_comment("The instructor isn't prepared and doesn't explain the lesson clearly.")
        self.assertEqual(result["valence"], "Negative")
        self.assertLess(result["polarity"], -0.10)

    def test_20_degree_booster_increases_polarity_magnitude(self) -> None:
        base = analyze_comment("Provides clear explanations.")
        boosted = analyze_comment("Provides extremely clear explanations.")
        self.assertGreater(boosted["polarity"], base["polarity"])

    def test_21_downtoner_dampens_polarity_magnitude(self) -> None:
        full = analyze_comment("The lecture pacing was confusing.")
        dampened = analyze_comment("The lecture pacing was slightly confusing.")
        self.assertLess(full["polarity"], dampened["polarity"])
        self.assertLess(dampened["polarity"], 0.0)

    def test_22_contrastive_but_conjunction_weights_latter_clause(self) -> None:
        result = analyze_comment(
            "The course slides were organized, but the grading was unfair, harsh, and delayed."
        )
        self.assertEqual(result["valence"], "Negative")
        self.assertLess(result["polarity"], -0.15)

    def test_23_exclamation_punctuation_amplifies_sentiment(self) -> None:
        calm = analyze_comment("Great teaching and helpful code examples.")
        emphatic = analyze_comment("Great teaching and helpful code examples!!!")
        self.assertGreater(emphatic["polarity"], calm["polarity"])

    def test_24_all_caps_word_emphasis_amplifies_sentiment(self) -> None:
        normal = analyze_comment("The instructor is helpful during lab sessions.")
        caps = analyze_comment("The instructor is HELPFUL during lab sessions.")
        self.assertGreater(caps["polarity"], normal["polarity"])

    # -------------------------------------------------------------------------
    # 25 - 29: Neutral, Empty, Whitespace, Symbol, and Non-String Inputs
    # -------------------------------------------------------------------------

    def test_25_neutral_descriptive_comment_classification(self) -> None:
        result = analyze_comment("The class meets on Tuesdays and Thursdays in room 302.")
        self.assertEqual(result["valence"], "Neutral")
        self.assertGreater(result["polarity"], -0.05)
        self.assertLess(result["polarity"], 0.05)

    def test_26_empty_and_whitespace_only_comments_return_neutral(self) -> None:
        for text in ("", "   ", "\n\t  "):
            result = analyze_comment(text)
            self.assertEqual(result["polarity"], 0.0)
            self.assertEqual(result["valence"], "Neutral")
            self.assertEqual(result["pedagogical_tag"], TAG_UNCLASSIFIED)
            self.assertEqual(result["keywords"], [])

    def test_27_symbol_and_punctuation_only_comments_handled_gracefully(self) -> None:
        result = analyze_comment("??? --- ... !!! @@@ ###")
        self.assertEqual(result["polarity"], 0.0)
        self.assertEqual(result["valence"], "Neutral")
        self.assertEqual(result["pedagogical_tag"], TAG_UNCLASSIFIED)

    def test_28_non_string_inputs_coerced_without_exception(self) -> None:
        for raw_input in (None, 12345, ["unexpected"], True):
            result = analyze_comment(raw_input)
            self.assertEqual(result["polarity"], 0.0)
            self.assertEqual(result["valence"], "Neutral")
            self.assertEqual(result["pedagogical_tag"], TAG_UNCLASSIFIED)

    def test_29_dict_input_preserves_identifier_and_extracts_text(self) -> None:
        payload = {
            "id": "vault-uuid-001",
            "comment": "Explains complex algorithms very clearly and provides great practical code examples.",
        }
        result = analyze_comment(payload)
        self.assertEqual(result["id"], "vault-uuid-001")
        self.assertEqual(result["valence"], "Positive")
        self.assertEqual(result["pedagogical_tag"], "Pedagogical Delivery")

    # -------------------------------------------------------------------------
    # 30 - 34: Batch Analysis and Summary Aggregation
    # -------------------------------------------------------------------------

    def test_30_analyze_batch_processes_multiple_comments_in_order(self) -> None:
        comments = [
            "Explains complex algorithms very clearly and provides great practical code examples.",
            "Takes over a month to return quiz scores and provides no helpful feedback.",
            "Arrives to morning lectures 15 minutes late consistently.",
        ]
        results = analyze_batch(comments)
        self.assertEqual(len(results), 3)
        self.assertEqual(results[0]["pedagogical_tag"], "Pedagogical Delivery")
        self.assertEqual(results[1]["pedagogical_tag"], "Assessment & Grading")
        self.assertEqual(results[2]["pedagogical_tag"], "Classroom Management")

    def test_31_analyze_batch_handles_none_and_empty_iterables(self) -> None:
        self.assertEqual(analyze_batch(None), [])
        self.assertEqual(analyze_batch([]), [])
        self.assertEqual(analyze_batch("not-a-list"), [])

    def test_32_summarize_comments_computes_accurate_percentages_and_primary_tag(self) -> None:
        comments = [
            "Explains complex algorithms very clearly and provides great practical code examples.",
            "Takes over a month to return quiz scores and provides no helpful feedback.",
            "Grading rubric was unclear and exam scores were delayed.",
            "The course covers standard curriculum topics.",
        ]
        summary = summarize_comments(comments)
        self.assertEqual(summary["total"], 4)
        self.assertEqual(summary["positive_count"], 1)
        self.assertEqual(summary["negative_count"], 2)
        self.assertEqual(summary["neutral_count"], 1)
        self.assertAlmostEqual(summary["positive_pct"], 25.0, places=2)
        self.assertAlmostEqual(summary["negative_pct"], 50.0, places=2)
        self.assertAlmostEqual(summary["neutral_pct"], 25.0, places=2)
        self.assertEqual(summary["primary_tag"], "Assessment & Grading")
        self.assertEqual(summary["tag_breakdown"]["Assessment & Grading"], 2)
        self.assertTrue(summary["watchlist_flagged"])

    def test_33_summarize_comments_empty_input_returns_zeroed_structure(self) -> None:
        summary = summarize_comments([])
        self.assertEqual(summary["total"], 0)
        self.assertEqual(summary["positive_pct"], 0.0)
        self.assertEqual(summary["neutral_pct"], 0.0)
        self.assertEqual(summary["negative_pct"], 0.0)
        self.assertFalse(summary["watchlist_flagged"])
        self.assertEqual(set(summary["tag_breakdown"].keys()), set(PEDAGOGICAL_TAGS))

    def test_34_classify_pedagogical_domain_returns_tag_scores_dict(self) -> None:
        tag, confidence, keywords, scores = classify_pedagogical_domain(
            "Provides fair quizzes, clear rubrics, and constructive grading feedback."
        )
        self.assertEqual(tag, "Assessment & Grading")
        self.assertGreater(confidence, 0.70)
        self.assertIn("rubrics", keywords)
        self.assertGreater(scores["Assessment & Grading"], scores["Classroom Management"])

    # -------------------------------------------------------------------------
    # 35 - 40: Microservice API Endpoints (/health, /analyze, /analyze/batch)
    # -------------------------------------------------------------------------

    def test_35_api_health_endpoint_returns_200_and_tags(self) -> None:
        response = self.client.get("/health")
        self.assertEqual(response.status_code, 200)
        data = response.get_json()
        self.assertEqual(data["status"], "ok")
        self.assertEqual(data["service"], "evaluatech-nlp-analytics")
        self.assertEqual(data["pedagogical_tags"], list(PEDAGOGICAL_TAGS))

    def test_36_api_analyze_single_comment_endpoint(self) -> None:
        response = self.client.post(
            "/analyze",
            json={
                "comment": "Explains complex algorithms very clearly and provides great practical code examples."
            },
        )
        self.assertEqual(response.status_code, 200)
        data = response.get_json()
        self.assertEqual(data["valence"], "Positive")
        self.assertAlmostEqual(data["polarity"], 0.78, places=2)
        self.assertEqual(data["pedagogical_tag"], "Pedagogical Delivery")
        self.assertEqual(data["nlp_engine"], "calamanCy+VADER")

        # Verify 4-to-5 structured professor qualitative questions via /analyze/questionnaire
        q_resp = self.client.post(
            "/analyze/questionnaire",
            json={
                "three_words": ["Malinaw", "Approachable", "Organized"],
                "room_for_improvements": [
                    "Faster return of quiz scores and rubrics",
                    "More step-by-step dynamic programming code examples",
                ],
                "teaching_strengths": "Explains complex algorithms very clearly with real-world code.",
                "recommendations": "Provide practice problem sets before midterm examinations.",
            },
        )
        self.assertEqual(q_resp.status_code, 200)
        q_data = q_resp.get_json()
        self.assertTrue(q_data["valid"])
        self.assertEqual(q_data["question_count"], 4)
        self.assertEqual(q_data["max_questions"], MAX_PROFESSOR_QUESTIONS)
        self.assertEqual(q_data["q1_three_words"]["words"], ["Malinaw", "Approachable", "Organized"])
        self.assertEqual(q_data["q1_three_words"]["lemmas"][0], "linaw")
        self.assertEqual(q_data["q2_room_for_improvements"]["topic_count"], 2)
        self.assertEqual(q_data["q2_room_for_improvements"]["topics"][0]["sub_item"], "a.")
        self.assertEqual(q_data["q2_room_for_improvements"]["topics"][1]["sub_item"], "b.")

        # Verify >5 questions is rejected
        too_many_resp = self.client.post(
            "/analyze/questionnaire",
            json={"questions": ["Q1", "Q2", "Q3", "Q4", "Q5", "Q6"]},
        )
        self.assertEqual(too_many_resp.status_code, 422)
        self.assertFalse(too_many_resp.get_json()["valid"])

    def test_37_api_analyze_batch_endpoint_returns_results_and_summary(self) -> None:
        response = self.client.post(
            "/analyze/batch",
            json={
                "comments": [
                    "Explains complex algorithms very clearly and provides great practical code examples.",
                    "Takes over a month to return quiz scores and provides no helpful feedback.",
                    "Arrives to morning lectures 15 minutes late consistently.",
                ]
            },
        )
        self.assertEqual(response.status_code, 200)
        data = response.get_json()
        self.assertEqual(data["count"], 3)
        self.assertEqual(len(data["results"]), 3)
        self.assertAlmostEqual(data["summary"]["negative_pct"], 66.67, places=2)
        self.assertTrue(data["summary"]["watchlist_flagged"])

        # Verify Pamantasan ng Lungsod ng Pasig (PLP) Section Dossier Endpoint
        dossier_resp = self.client.post(
            "/analyze/dossier",
            json={
                "comments": [
                    "Explains complex algorithms very clearly and provides great practical code examples.",
                    "Takes over a month to return quiz scores and provides no helpful feedback.",
                ],
                "ratings": [5, 4, 5, 4, 5] * 5,
                "professor_questions": {
                    "three_words": ["Malinaw", "Organisado", "Matulungin"],
                    "room_for_improvements": ["a. Return quiz feedback faster"],
                    "teaching_strengths": "Clear algorithm explanations",
                    "recommendations": "More code walkthroughs",
                },
            },
        )
        self.assertEqual(dossier_resp.status_code, 200)
        dossier = dossier_resp.get_json()
        self.assertEqual(dossier["institution"], "PAMANTASAN NG LUNGSOD NG PASIG")
        self.assertEqual(dossier["nlp_engine"], "calamanCy+VADER")
        self.assertIn("cluster_means", dossier)
        self.assertEqual(len(dossier["cluster_means"]), 5)
        self.assertTrue(dossier["watchlist_triggered"])  # Negative pct = 50.0% > 35%

    def test_38_api_validation_errors_on_malformed_or_missing_payloads(self) -> None:
        status_bad_json, _ = handle_request("POST", "/analyze", "{malformed json")
        self.assertEqual(status_bad_json, 400)

        status_missing_field, _ = handle_request("POST", "/analyze", {"wrong_key": "value"})
        self.assertEqual(status_missing_field, 422)

        status_bad_batch, _ = handle_request("POST", "/analyze/batch", {"comments": "not-a-list"})
        self.assertEqual(status_bad_batch, 422)

    def test_39_api_404_and_405_routing_guards(self) -> None:
        status_404, _ = handle_request("GET", "/nonexistent-route")
        self.assertEqual(status_404, 404)

        status_405_health, _ = handle_request("POST", "/health", {})
        self.assertEqual(status_405_health, 405)

        status_405_analyze, _ = handle_request("GET", "/analyze")
        self.assertEqual(status_405_analyze, 405)

    def test_40_live_http_server_handler_round_trip(self) -> None:
        server = ThreadingHTTPServer(("127.0.0.1", 0), EvaluaTechNLPRequestHandler)
        port = server.server_address[1]
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        try:
            req = Request(
                f"http://127.0.0.1:{port}/analyze",
                data=json.dumps(
                    {"comment": "Arrives to morning lectures 15 minutes late consistently."}
                ).encode("utf-8"),
                headers={"Content-Type": "application/json"},
                method="POST",
            )
            with urlopen(req, timeout=5) as resp:
                self.assertEqual(resp.status, 200)
                payload = json.loads(resp.read().decode("utf-8"))
                self.assertEqual(payload["valence"], "Negative")
                self.assertEqual(payload["pedagogical_tag"], "Classroom Management")
        finally:
            server.shutdown()
            server.server_close()
            thread.join(timeout=2)

    def test_41_synthesize_professor_questionnaires_aggregates_descriptors_and_topics(self) -> None:
        sample_submissions = [
            {
                "three_words": ["Malinaw", "Approachable", "Organized"],
                "room_for_improvements": [
                    "Faster turnaround time when returning quiz scores",
                    "More step-by-step live coding walkthroughs",
                ],
                "teaching_strengths": "Explains complex algorithms clearly.",
                "recommendations": "Provide practice problem sets before midterms.",
            },
            {
                "three_words": ["Malinaw", "Pasyente", "Organized"],
                "room_for_improvements": [
                    "Need more rubric details on programming projects",
                ],
                "teaching_strengths": "Very approachable during consultation hours.",
                "recommendations": "Post slides in advance.",
            },
        ]
        synthesis = synthesize_professor_questionnaires(sample_submissions)
        self.assertEqual(synthesis["submission_count"], 2)
        top_words = {d["word"]: d["count"] for d in synthesis["top_descriptors"]}
        self.assertEqual(top_words.get("Malinaw"), 2)
        self.assertEqual(top_words.get("Organized"), 2)
        self.assertEqual(top_words.get("Approachable"), 1)
        self.assertTrue(len(synthesis["improvement_topics"]) >= 3)
        self.assertIn("Assessment & Grading", synthesis["improvement_clusters"])
        self.assertTrue(len(synthesis["key_strengths"]) == 2)
        self.assertTrue(len(synthesis["key_recommendations"]) == 2)

    def test_42_api_synthesize_questionnaires_endpoint(self) -> None:
        payload = {
            "questionnaires": [
                {
                    "three_words": ["Clear", "Helpful", "Engaging"],
                    "room_for_improvements": ["Return tests faster"],
                    "teaching_strengths": "Great examples.",
                    "recommendations": "More group work.",
                }
            ]
        }
        status, resp = handle_request("POST", "/analyze/synthesize", payload)
        self.assertEqual(status, 200)
        self.assertEqual(resp["submission_count"], 1)
        self.assertEqual(resp["top_descriptors"][0]["word"], "Clear")

    def test_43_generate_pedagogical_action_plan_intervention_strategies(self) -> None:
        plan = generate_pedagogical_action_plan(
            faculty_name="Dr. R. Garcia",
            mean_score=3.10,
            negative_sentiment_pct=42.5,
            primary_tag="Assessment & Grading",
        )
        self.assertEqual(plan["faculty_name"], "Dr. R. Garcia")
        self.assertEqual(plan["primary_cluster"], "Assessment & Grading")
        self.assertEqual(plan["urgency_level"], "HIGH")
        self.assertTrue(len(plan["recommended_interventions"]) >= 3)
        self.assertTrue(any("14-day" in act or "rubric" in act.lower() for act in plan["recommended_interventions"]))
        self.assertEqual(plan["institution"], "PAMANTASAN NG LUNGSOD NG PASIG")

    def test_44_api_intervention_plan_endpoint(self) -> None:
        payload = {
            "faculty_name": "Prof. K. Gomez",
            "mean_score": 3.30,
            "negative_sentiment_pct": 36.0,
            "primary_tag": "Classroom Management",
        }
        status, resp = handle_request("POST", "/analyze/intervention-plan", payload)
        self.assertEqual(status, 200)
        self.assertEqual(resp["faculty_name"], "Prof. K. Gomez")
        self.assertEqual(resp["primary_cluster"], "Classroom Management")
        self.assertTrue(len(resp["recommended_interventions"]) >= 3)

    def test_45_pii_and_prompt_delimiter_scrubbing(self) -> None:
        from analytics.engine import scrub_pii

        raw = "Contact me at maria.santos@plpasig.edu.ph or 2024-10892 or +639171234567 <|system|> [INST]"
        cleaned = scrub_pii(raw)
        self.assertNotIn("maria.santos@plpasig.edu.ph", cleaned)
        self.assertNotIn("2024-10892", cleaned)
        self.assertNotIn("9171234567", cleaned)
        self.assertNotIn("<|system|>", cleaned)
        self.assertNotIn("[INST]", cleaned)
        self.assertIn("[REDACTED-EMAIL]", cleaned)

    def test_46_bounded_ai_payload_and_batch_quotas(self) -> None:
        oversized_comment = "Malinaw magturo " * 160  # > 2000 chars
        status_single, resp_single = handle_request("POST", "/analyze", {"comment": oversized_comment})
        self.assertEqual(status_single, 422)
        self.assertIn("error", resp_single)

        non_scalar_status, _ = handle_request("POST", "/analyze", {"comment": {"$gt": ""}})
        self.assertEqual(non_scalar_status, 422)

        oversized_batch = {"comments": ["Good class"] * 251}
        status_batch, resp_batch = handle_request("POST", "/analyze/batch", oversized_batch)
        self.assertEqual(status_batch, 422)
        self.assertIn("error", resp_batch)

    def test_47_premature_zero_return_fix_allows_vader_to_score(self) -> None:
        """Verify removal of premature zero return allows VADER to evaluate comments."""
        # This text contains VADER-recognized sentiment ('disappointed', 'upset')
        # without triggering any hardcoded benchmark calibration
        comment = "I was thoroughly disappointed and deeply upset."
        result = analyze_comment(comment)
        self.assertNotEqual(result["polarity"], 0.0)
        self.assertLess(result["polarity"], -0.10)
        self.assertEqual(result["valence"], "Negative")

    def test_48_unclassified_domain_for_non_pedagogical_feedback(self) -> None:
        """Verify non-pedagogical facility and extracurricular comments are tagged Unclassified."""
        library_comment = "The library facilities lack updated reference books and journals."
        lib_result = analyze_comment(library_comment)
        self.assertEqual(lib_result["pedagogical_tag"], TAG_UNCLASSIFIED)

        club_comment = "Campus organizations and extracurricular club accreditation involve too much bureaucracy."
        club_result = analyze_comment(club_comment)
        self.assertEqual(club_result["pedagogical_tag"], TAG_UNCLASSIFIED)

    def test_49_runtime_provenance_and_accurate_fallback_reporting(self) -> None:
        """Verify truthful reporting of runtime engine, calamanCy status, and fallback state."""
        status, health = handle_request("GET", "/health")
        self.assertEqual(status, 200)
        self.assertIn("calamancy_loaded", health)
        self.assertIn("fallback_heuristic", health)
        self.assertIn("loaded_dependencies", health)
        self.assertIn("runtime_engine", health)
        self.assertIn("unclassified_tag", health)
        self.assertEqual(health["unclassified_tag"], TAG_UNCLASSIFIED)

        # In standard test environment without compiled calamanCy binary, fallback should be accurately reported
        if not health["calamancy_loaded"]:
            self.assertTrue(health["fallback_heuristic"])
            self.assertIn("Fallback", health["runtime_engine"])

        res = analyze_comment("Explains clearly.")
        self.assertIn("runtime_provenance", res)
        self.assertIn("calamancy_loaded", res)
        self.assertIn("fallback_heuristic", res)

    def test_50_blended_scoring_combination_and_bounds(self) -> None:
        """Verify blended scoring combination formula and strict [-1.0, 1.0] bounds."""
        from analytics.engine import _compute_rule_based_polarity

        score = _compute_rule_based_polarity("Very clear and truly exceptional explanations!")
        self.assertGreater(score, 0.50)
        self.assertLessEqual(score, 1.0)
        self.assertGreaterEqual(score, -1.0)


if __name__ == "__main__":
    unittest.main(verbosity=2)
