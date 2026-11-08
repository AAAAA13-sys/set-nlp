# Graph Report - NLP SET  (2026-11-09)

## Corpus Check
- 109 files · ~116,358 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 7 file(s) not represented in the graph (top: (none) 2, .example 1, .bat 1)

## Summary
- 1409 nodes · 2641 edges · 93 communities (59 shown, 34 thin omitted)
- Extraction: 90% EXTRACTED · 10% INFERRED · 0% AMBIGUOUS · INFERRED: 269 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Decoupled Evaluation Architecture
- Decoupled Evaluation Architecture
- Vue Workspace & Navigation Shell
- calamanCy + VADER NLP Engine
- Vue Workspace & Navigation Shell
- Decoupled Evaluation Architecture
- calamanCy + VADER NLP Engine
- Multi-Tier Automated Test Suites
- Academic Roster Ingestion
- Multi-Tier Automated Test Suites
- Decoupled Evaluation Architecture
- Institutional Analytics & Governance
- Academic Roster Ingestion
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Component Group (security-ci.yml)
- Academic Roster Ingestion
- Vue Workspace & Navigation Shell
- calamanCy + VADER NLP Engine
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Component Group (ArrayAccess)
- Vue Workspace & Navigation Shell
- calamanCy + VADER NLP Engine
- calamanCy + VADER NLP Engine
- Database Schema & Seeders
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Decoupled Evaluation Architecture
- calamanCy + VADER NLP Engine
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- calamanCy + VADER NLP Engine
- Vue Workspace & Navigation Shell
- Student Queue & Permit Verification
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Decoupled Evaluation Architecture
- Academic Roster Ingestion
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- Vue Workspace & Navigation Shell
- calamanCy + VADER NLP Engine
- Component Group (1. Ruthless UI Anti-Slop Audit)
- Component Group (Blueprint)
- Student Queue & Permit Verification
- Component Group (Architectural boundaries)
- Vue Workspace & Navigation Shell
- Institutional Analytics & Governance
- Vue Workspace & Navigation Shell
- calamanCy + VADER NLP Engine
- Decoupled Evaluation Architecture
- Vue Workspace & Navigation Shell
- Decoupled Evaluation Architecture
- Component Group (EvaluaTechNLPRequestHandler)
- Vue Workspace & Navigation Shell
- Component Group (1. Cross-Tier Contract Synchro...)
- Component Group (1. Multi-Tier Verification Mat...)
- Institutional Analytics & Governance
- Institutional Analytics & Governance
- calamanCy + VADER NLP Engine
- Vue Workspace & Navigation Shell
- Component Group (evaluateWatchlistFlag())
- Multi-Tier Automated Test Suites
- Component Group (FormRequest)
- Vue Workspace & Navigation Shell
- Database Schema & Seeders
- Component Group (Illuminate\Support\Facades\Rou...)
- Multi-Tier Automated Test Suites
- Institutional Analytics & Governance
- Component Group (EvaluaTech Backend Standards)
- Vue Workspace & Navigation Shell
- Component Group (EvaluaTech Security & Anonymit...)
- Component Group (EvaluaTech Verification & Qual...)
- Database Schema & Seeders

## God Nodes (most connected - your core abstractions)
1. `DecoupledEvaluationService` - 80 edges
2. `EvaluaTechNLPEngineTestSuite` - 50 edges
3. `DeanAnalyticsController` - 45 edges
4. `analyze_comment()` - 44 edges
5. `EvaluaTechBackendSuiteTest` - 43 edges
6. `AnalyticsClientService` - 33 edges
7. `IntegrityValidatorService` - 31 edges
8. `TestCase` - 31 edges
9. `useAuthStore` - 29 edges
10. `handle_request()` - 28 edges

## Surprising Connections (you probably didn't know these)
- `GET /{any?}()` --calls--> `base_path()`  [INFERRED]
  routes/web.php → bootstrap/app.php
- `verify_4_calamancy_vader_and_professor_questions()` --calls--> `extract_calamancy_features()`  [EXTRACTED]
  tests/load/verify_plp_benchmarks.py → analytics/engine.py
- `verify_4_calamancy_vader_and_professor_questions()` --calls--> `analyze_comment()`  [EXTRACTED]
  tests/load/verify_plp_benchmarks.py → analytics/engine.py
- `verify_4_calamancy_vader_and_professor_questions()` --calls--> `analyze_professor_questions()`  [EXTRACTED]
  tests/load/verify_plp_benchmarks.py → analytics/engine.py
- `{closure#26}()` --calls--> `DeanAnalyticsController`  [EXTRACTED]
  public/index.php → app/Http/Controllers/Api/DeanAnalyticsController.php

## Import Cycles
- None detected.

## Communities (93 total, 34 thin omitted)

### Community 1 - "Decoupled Evaluation Architecture"
Cohesion: 0.09
Nodes (32): Schema, {closure#1}(), {closure#2}(), down(), up(), {closure#1}(), {closure#2}(), {closure#3}() (+24 more)

### Community 2 - "Vue Workspace & Navigation Shell"
Cohesion: 0.06
Nodes (31): emit, handleKeyDown(), props, allSectionsSelected, authStore, bulkActionBusy, bulkStatusMessage, confirmDangerousWindowStatus() (+23 more)

### Community 3 - "calamanCy + VADER NLP Engine"
Cohesion: 0.09
Nodes (3): analyze_comment(), classify_valence(), EvaluaTechNLPEngineTestSuite

### Community 4 - "Vue Workspace & Navigation Shell"
Cohesion: 0.06
Nodes (33): clearSurveyDraft(), loadSurveyDraft(), saveSurveyDraft(), activeProfQuestionCount, activeSectionId, additionalObservation, allRatingsAnswered, answeredCount (+25 more)

### Community 5 - "Decoupled Evaluation Architecture"
Cohesion: 0.05
Nodes (36): 1. Authenticity Filtering Performance, 1. Dual-Token Cryptographic Decoupling Workflow, 1. Test Environment and Execution Stack, 2. Double-Blind Faculty Access Control, 2. High-Concurrency Stress Testing (Operational Efficiency), 2. Narrative Sentiment and Pedagogical Classification, 3. Data Integrity & Speed-Run Verification, 3. ISO/IEC 25010 Software Quality Assessment (+28 more)

### Community 6 - "calamanCy + VADER NLP Engine"
Cohesion: 0.10
Nodes (12): analyze_plp_section_dossier(), analyze_professor_questions(), classify_pedagogical_domain(), _compute_five_cluster_means(), _compute_rule_based_polarity(), extract_calamancy_features(), _get_calamancy_pipeline(), _infer_ud_pos() (+4 more)

### Community 7 - "Multi-Tier Automated Test Suites"
Cohesion: 0.06
Nodes (35): autoload, autoload-dev, psr-4, psr-4, config, optimize-autoloader, preferred-install, sort-packages (+27 more)

### Community 8 - "Academic Roster Ingestion"
Cohesion: 0.15
Nodes (27): AdminRosterController, DeanAnalyticsController, {closure#15}(), {closure#16}(), {closure#17}(), {closure#18}(), {closure#19}(), {closure#20}() (+19 more)

### Community 11 - "Institutional Analytics & Governance"
Cohesion: 0.11
Nodes (10): create_app(), analyze_batch_endpoint(), analyze_dossier_endpoint(), analyze_endpoint(), analyze_questionnaire_endpoint(), health_endpoint(), _FallbackApp, _FallbackResponse (+2 more)

### Community 12 - "Academic Roster Ingestion"
Cohesion: 0.08
Nodes (10): {closure#1}(), UserRole, ADMIN, CHAIR, DEAN, FACULTY, STUDENT, ImportRosterRequest (+2 more)

### Community 13 - "Vue Workspace & Navigation Shell"
Cohesion: 0.07
Nodes (29): calculateSusScore(), activeTab, authStore, averageEvalTimeDisplay, clustersList, complianceAuditData, departmentsList, evaluationStore (+21 more)

### Community 15 - "Vue Workspace & Navigation Shell"
Cohesion: 0.08
Nodes (21): activeFacet, allVisibleSelected, applyDebouncedSearch, authStore, clearSelection(), currentPage, debouncedQuery, density (+13 more)

### Community 17 - "Component Group (security-ci.yml)"
Cohesion: 0.08
Nodes (25): jobs, verify, name, on, pull_request, push, permissions, contents (+17 more)

### Community 18 - "Academic Roster Ingestion"
Cohesion: 0.14
Nodes (6): FacultyPortalController, {closure#12}(), {closure#13}(), {closure#14}(), {closure#41}(), {closure#9}()

### Community 19 - "Vue Workspace & Navigation Shell"
Cohesion: 0.11
Nodes (18): dependencies, pinia@^2.3.0, vue@^3.5.13, vue-router@^4.5.0, pinia, vue, vue-router, bootstrapEvaluaTechApp() (+10 more)

### Community 20 - "calamanCy + VADER NLP Engine"
Cohesion: 0.11
Nodes (16): calculateClusterMeans(), calculateMean(), DEMO_DEAN_METRICS, DEMO_STUDENT_QUEUE, LIKERT_SCALE_OPTIONS, MIN_PROFESSOR_QUESTIONS, PEDAGOGICAL_DIMENSIONS, PROFESSOR_QUALITATIVE_QUESTIONS (+8 more)

### Community 21 - "Vue Workspace & Navigation Shell"
Cohesion: 0.18
Nodes (18): canAccessRoute(), createEvaluaTechRouter(), routes, clearStoredSession(), getDefaultRouteForRole(), persistStoredSession(), readStoredSession(), useAuthStore (+10 more)

### Community 24 - "Vue Workspace & Navigation Shell"
Cohesion: 0.09
Nodes (19): activeSection, anonymizedComments, assignedSections, authStore, dimensionScores, evaluationStore, gradeStatusBanner, loadingReport (+11 more)

### Community 25 - "calamanCy + VADER NLP Engine"
Cohesion: 0.12
Nodes (4): _parse_body(), run_server(), generate_pedagogical_action_plan(), scrub_pii()

### Community 26 - "calamanCy + VADER NLP Engine"
Cohesion: 0.20
Nodes (4): AnalyticsClientService, {closure#30}(), {closure#32}(), {closure#33}()

### Community 27 - "Database Schema & Seeders"
Cohesion: 0.14
Nodes (5): check_live_server(), main(), run_benchmark(), run_in_memory_concurrency_simulation(), submit_via_http()

### Community 28 - "Vue Workspace & Navigation Shell"
Cohesion: 0.13
Nodes (18): axios@^1.7.9, description, devDependencies, laravel-vite-plugin@^1.2.0, vite@^6.0.7, @vitejs/plugin-vue@^5.2.1, name, private (+10 more)

### Community 29 - "Vue Workspace & Navigation Shell"
Cohesion: 0.12
Nodes (18): activeLemmas, clusterConfidenceList, customNarrative, emit, handleKeyDown(), nlpAnalysis, professorPayload, props (+10 more)

### Community 30 - "Vue Workspace & Navigation Shell"
Cohesion: 0.10
Nodes (17): authStore, chairHeatmapData, chairIdentifier, chairNotes, departmentLongitudinal, departmentMeanScore, departmentName, dossierData (+9 more)

### Community 31 - "Decoupled Evaluation Architecture"
Cohesion: 0.16
Nodes (8): AcademicTerm, Course, CourseSection, Department, Enrollment, EvaluationClearance, EvaluationVault, QualityEvaluation

### Community 33 - "Vue Workspace & Navigation Shell"
Cohesion: 0.13
Nodes (7): base_path(), basePath(), config(), Controller, database_path(), Queueable, ShouldQueue

### Community 34 - "Vue Workspace & Navigation Shell"
Cohesion: 0.11
Nodes (17): Delivery order and exclusions, EvaluaTech implementation plan, Focused multi-tier verification matrix, Formulas and classification rules, Four-tier architecture and boundaries, Phase 0: decisions before implementation, Phase 1: Laravel 12 foundation, five-role RBAC and academic roster ingestion, Phase 2: Dynamic student evaluation queue and dual-token cryptographic decoupling (+9 more)

### Community 35 - "Vue Workspace & Navigation Shell"
Cohesion: 0.11
Nodes (17): Delivery order and exclusions, EvaluaTech implementation plan, Focused multi-tier verification matrix, Formulas and classification rules, Four-tier architecture and boundaries, Phase 0: decisions before implementation, Phase 1: Laravel 12 foundation, five-role RBAC and academic roster ingestion, Phase 2: Dynamic student evaluation queue and dual-token cryptographic decoupling (+9 more)

### Community 36 - "calamanCy + VADER NLP Engine"
Cohesion: 0.21
Nodes (8): compute_mean_and_population_variance(), evaluate_submission_authenticity(), main(), verify_1_speed_run_guard(), verify_2_straight_lining_guard(), verify_3_genuine_varied_evaluations(), verify_4_calamancy_vader_and_professor_questions(), verify_5_iso25010_and_sus_benchmarks()

### Community 38 - "Student Queue & Permit Verification"
Cohesion: 0.15
Nodes (5): StudentEvaluationController, {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}()

### Community 39 - "Vue Workspace & Navigation Shell"
Cohesion: 0.16
Nodes (5): EnsureDoubleBlindUnlocked, EnsureRole, SecurityHeadersMiddleware, {closure#11}(), {closure#2}()

### Community 40 - "Vue Workspace & Navigation Shell"
Cohesion: 0.14
Nodes (16): applyPrompt(), authStore, brainResponse, debouncedSearch, emit, endpointBase, errorMsg, executeSearch() (+8 more)

### Community 41 - "Vue Workspace & Navigation Shell"
Cohesion: 0.12
Nodes (15): authStore, errorMessage, evaluationStore, handleLogin, idValidationHint, institutionalId, password, passwordValidationHint (+7 more)

### Community 42 - "Vue Workspace & Navigation Shell"
Cohesion: 0.14
Nodes (14): authStore, canUseQuivrBrain, currentRole, emit, evaluationStore, firstPendingSectionId, handleSignOut(), primaryLinks (+6 more)

### Community 45 - "Vue Workspace & Navigation Shell"
Cohesion: 0.15
Nodes (10): authStore, debouncedSearch, evaluationStore, filteredCourses, onSearchInput(), progress, router, searchInput (+2 more)

### Community 46 - "Vue Workspace & Navigation Shell"
Cohesion: 0.23
Nodes (11): activeIndex, authStore, baseCommands, emit, executeCommand(), filteredCommands, onKeyDown(), props (+3 more)

### Community 47 - "Vue Workspace & Navigation Shell"
Cohesion: 0.17
Nodes (10): authorizedNavLinks, authStore, clearanceStatus, evaluationStore, firstPendingSectionId, isCleared, roleNavLinks, route (+2 more)

### Community 49 - "Vue Workspace & Navigation Shell"
Cohesion: 0.20
Nodes (8): authStore, isVerifying, masterPermitToken, progress, props, verificationError, verificationResult, calculateQueueProgress()

### Community 50 - "Vue Workspace & Navigation Shell"
Cohesion: 0.18
Nodes (8): attemptedPath, authorizedRoute, authStore, currentRole, evaluationStore, requiredRoles, route, router

### Community 51 - "calamanCy + VADER NLP Engine"
Cohesion: 0.20
Nodes (9): 1. Executive Summary, 2. Pamantasan ng Lungsod ng Pasig (PLP) Visual Identity & Color Token Alignment, 3.1 Five Pedagogical Clusters (25 Likert Items, Scale 1..5), 3.2 Structured Professor Qualitative Questions (4 Default, Max 5 Cap), 3. 5-Cluster × 5-Point Likert Scale (25 Items) & 4-to-5 Professor Qualitative Questions Contract Trace, 4. `calamanCy` + VADER Sentiment Polarity Alignment, 5. Non-Negotiable Security & Integrity Invariants Verification, 6. Review Taxonomy Findings (Blockers, Suggestions, Nits) (+1 more)

### Community 52 - "Component Group (1. Ruthless UI Anti-Slop Audit)"
Cohesion: 0.22
Nodes (8): 1. Ruthless UI Anti-Slop Audit, 2. Functional & Distinctive Interaction Ergonomics, 3. Responsive & Print Rigor, 📋 Anti-Slop Verification Checklist, 🎯 Core Missions in EvaluaTech, 🧠 Identity & Aesthetic Philosophy, 🚫 The AI UI Slop Hall of Shame (Banned Patterns), UI Anti-Slop Specialist Agent (EvaluaTech & Institutional WebApps)

### Community 53 - "Component Group (Blueprint)"
Cohesion: 0.22
Nodes (4): Blueprint, __call(), group(), Route

### Community 54 - "Student Queue & Permit Verification"
Cohesion: 0.36
Nodes (8): generateSubmissionKey(), useEvaluationStore, calculatePopulationVariance(), canFacultyViewEvaluation(), isStraightLined(), currentVariance, straightLined, isUnlocked

### Community 55 - "Component Group (Architectural boundaries)"
Cohesion: 0.25
Nodes (7): Architectural boundaries, Before editing, Done means, EvaluaTech repository agent guide, Non-negotiable invariants that must survive every change, Specialized agency workers (`.agents/skills/`), Verification standards

### Community 56 - "Vue Workspace & Navigation Shell"
Cohesion: 0.29
Nodes (7): formattedTime, isSatisfied, progressPercent, props, remainingSeconds, isDwellTimeValid(), dwellValid

### Community 57 - "Institutional Analytics & Governance"
Cohesion: 0.29
Nodes (8): fetchComplianceAudit(), fetchEvidenceDossier(), fetchHeatmapData(), fetchLongitudinalTrends(), fetchPerformanceQuartiles(), loadAllDeanData(), onDepartmentChange(), setPlanStatus()

### Community 58 - "Vue Workspace & Navigation Shell"
Cohesion: 0.25
Nodes (6): authStore, campusPortalShareUrl, collegeDrives, copiedTarget, evaluationStore, latestClearanceToken

### Community 59 - "calamanCy + VADER NLP Engine"
Cohesion: 0.29
Nodes (6): 1. Sentiment Polarity & Valence Calibration, 2. Four-Domain Pedagogical Classification, 3. Production Resilience & Circuit-Breaker Fallback, AI & NLP Engineer Agent (EvaluaTech Edition), 🎯 Core Missions in EvaluaTech, 🧠 Identity & Persona

### Community 60 - "Decoupled Evaluation Architecture"
Cohesion: 0.29
Nodes (6): 1. Dual-Token Cryptographic Decoupling & ACID Integrity, 2. Service Layer Boundaries, 3. Double-Blind Faculty Visibility & Watchlist Rules, Backend Architect Agent (EvaluaTech Edition), 🎯 Core Missions in EvaluaTech, 🧠 Identity & Persona

### Community 61 - "Vue Workspace & Navigation Shell"
Cohesion: 0.29
Nodes (6): 1. Vue.js 3 SPA Architecture (`resources/js/`), 2. Interactive Authenticity Guards & Queue UX, 3. Accessibility & Role-Scoped Portals, 🎯 Core Missions in EvaluaTech, Frontend Developer Agent (EvaluaTech Edition), 🧠 Identity & Persona

### Community 62 - "Decoupled Evaluation Architecture"
Cohesion: 0.29
Nodes (6): 1. Zero-Knowledge Cryptographic Decoupling, 2. Double-Blind Retaliation Mitigation & Five-Role RBAC, 3. Anti-Tamper & Input Sanitization, 🎯 Core Missions in EvaluaTech, 🧠 Identity & Persona, Security Guardian Agent (EvaluaTech Edition)

### Community 65 - "Component Group (1. Cross-Tier Contract Synchro...)"
Cohesion: 0.33
Nodes (5): 1. Cross-Tier Contract Synchronization, 2. Review Taxonomy, Code Reviewer & Minimal Change Agent (EvaluaTech Edition), 🎯 Core Missions in EvaluaTech, 🧠 Identity & Persona

### Community 66 - "Component Group (1. Multi-Tier Verification Mat...)"
Cohesion: 0.33
Nodes (5): 1. Multi-Tier Verification Matrix, 2. Authenticity & Anonymity Audit Checks, 🎯 Core Missions in EvaluaTech, 🧠 Identity & Persona, QA Reality Checker Agent (EvaluaTech Edition)

### Community 67 - "Institutional Analytics & Governance"
Cohesion: 0.33
Nodes (5): 1. Architectural Overview, 2. Quick Start & Verification, Launch the Live PLP Portal (`http://127.0.0.1:8000`), PAMANTASAN NG LUNGSOD NG PASIG (PLP) — EvaluaTech, Run the Full 85-Unit Multi-Tier Verification Suite

### Community 68 - "Institutional Analytics & Governance"
Cohesion: 0.33
Nodes (6): sanitizeCsvCell(), downloadQualitativeCsv(), downloadQualitativeJson(), exportAccreditationDossier(), generateAccreditationCsv(), triggerDownload()

### Community 70 - "Vue Workspace & Navigation Shell"
Cohesion: 0.50
Nodes (4): emit, handleRetry, props, throttle()

### Community 71 - "Component Group (evaluateWatchlistFlag())"
Cohesion: 0.40
Nodes (5): evaluateWatchlistFlag(), departmentFaculty, departmentWatchlist, flaggedWatchlist, proInstructorRows

### Community 74 - "Vue Workspace & Navigation Shell"
Cohesion: 0.67
Nodes (3): emit, handleKeyDown(), props

### Community 79 - "Institutional Analytics & Governance"
Cohesion: 0.67
Nodes (3): getDeanNotes(), getPlanStatus(), toggleInterventionPlan()

## Knowledge Gaps
- **142 isolated node(s):** `ADMIN`, `DEAN`, `CHAIR`, `FACULTY`, `STUDENT` (+137 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 655 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **34 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `DecoupledEvaluationService` connect `Decoupled Evaluation Architecture` to `calamanCy + VADER NLP Engine`, `Decoupled Evaluation Architecture`, `Vue Workspace & Navigation Shell`, `Student Queue & Permit Verification`, `Vue Workspace & Navigation Shell`, `Academic Roster Ingestion`, `calamanCy + VADER NLP Engine`, `Decoupled Evaluation Architecture`, `Academic Roster Ingestion`, `Multi-Tier Automated Test Suites`, `Vue Workspace & Navigation Shell`, `Institutional Analytics & Governance`, `Academic Roster Ingestion`, `Vue Workspace & Navigation Shell`, `calamanCy + VADER NLP Engine`?**
  _High betweenness centrality (0.039) - this node is a cross-community bridge._
- **Are the 16 inferred relationships involving `DecoupledEvaluationService` (e.g. with `.__construct()` and `.test_14_double_blind_faculty_unlock_serves_anonymized_aggregates_only()`) actually correct?**
  _`DecoupledEvaluationService` has 16 INFERRED edges - model-reasoned connections that need verification._
- **What connects `ADMIN`, `DEAN`, `CHAIR` to the rest of the system?**
  _142 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Decoupled Evaluation Architecture` be split into smaller, more focused modules?**
  _Cohesion score 0.07149758454106281 - nodes in this community are weakly interconnected._
- **Why does `EvaluaTechBackendSuiteTest` connect `Decoupled Evaluation Architecture` to `calamanCy + VADER NLP Engine`, `Vue Workspace & Navigation Shell`, `Student Queue & Permit Verification`, `Multi-Tier Automated Test Suites`, `Decoupled Evaluation Architecture`, `Multi-Tier Automated Test Suites`, `Multi-Tier Automated Test Suites`, `Institutional Analytics & Governance`, `Vue Workspace & Navigation Shell`?**
  _High betweenness centrality (0.029) - this node is a cross-community bridge._
- **Are the 5 inferred relationships involving `DeanAnalyticsController` (e.g. with `.test_26_department_chair_diagnostic_workspace_endpoints_and_rbac_scoping()` and `.test_27_longitudinal_semester_trends_and_action_plan_resolution_rate()`) actually correct?**
  _`DeanAnalyticsController` has 5 INFERRED edges - model-reasoned connections that need verification._
- **Should `Decoupled Evaluation Architecture` be split into smaller, more focused modules?**
  _Cohesion score 0.08888888888888889 - nodes in this community are weakly interconnected._