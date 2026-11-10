# Goal: Institutional Evaluation Campaign Orchestrator, Action Plan Mentorship Lifecycle & AACCUP Level III Accreditation Intelligence (Phase 8)

Date: 10 November 2026
Status: Complete & Production Verified (181 Automated Units, 100% PASS)
Execution Architecture: Parallel Tri-Agent Team (Lead Engineer, Frontend UI/UX Developer, QA Reality Checker & Autonomous Playwright)

---

## 1. Objective

Elevate EvaluaTech from its verified single-page evaluation baseline into an enterprise-grade academic command system for Pamantasan ng Lungsod ng Pasig (PLP). This goal establishes:
1. **Campaign Pre-Flight & Live Operations War Room:** An automated 6-point pre-launch diagnostic audit and real-time $\ge 85\%$ turnout velocity leaderboard.
2. **Faculty Pedagogical Action Plan & Peer Mentorship Lifecycle:** A closed-loop growth workspace for watchlist-flagged instructors, department chair mentor assignments, milestone sign-offs, and longitudinal recovery tracking.
3. **AACCUP Level III Executive Accreditation Dossier:** One-click compilation of cryptographically sealed accreditation evidence bundles (`PLP-AACCUP3-DOSSIER-YYYYMMDD.zip`) separating campus facilities feedback (`TAG_UNCLASSIFIED`) from teaching pedagogy.
4. **Quivr Qualitative Feedback & Semantic Search Explorer:** Intuitive, multi-tenant qualitative inquiry tool with sentiment valence pills and topic filtering.

---

## 2. Parallel Tri-Agent Development Model

Work proceeds in parallel across three specialized agency roles bound by strict shared contracts:

1. **Lead Systems Engineer & Backend Master (`agency-backend-architect`):**
   - Owns domain services (`CampaignWarRoomService`, `MentorshipLifecycleService`, `AccreditationGovernanceService`), database migrations, and REST APIs.
   - Enforces zero-knowledge decoupling, IDOR departmental scoping, and 63 OWASP controls.
2. **Frontend UI/UX Developer (`agency-frontend-developer`, `ui-anti-slop`):**
   - Owns reactive Vue 3 components, Pinia stores, and ergonomic PLP Academic Tech design tokens (`#103c2e` emerald, `#f4cd42` gold, `#f9f9f8` canvas).
   - Zero sidebars, zero generic slop, high-contrast typography (`Public Sans`, `Newsreader`, `JetBrains Mono`), and WCAG 2.2 AA accessibility.
3. **QA Reality Checker & Autonomous Playwright Specialist (`agency-qa-reality-checker`, `playwright-cli`):**
   - **Playwright Does His Own Shit:** Self-managing E2E test runner that automatically handles local server lifecycle (`127.0.0.1:8000`), executes headless browser user journeys (expanded to 12 tests: `E2E-01` to `E2E-12`), captures live visual audit screenshots, and gates production deployment.

---

## 3. UI/UX Specifications & Anti-Slop System

- **Institutional Color Tokens:**
  - Primary: `--color-forest-dark: #0b3322;`, `--color-emerald-base: #103c2e;`
  - Accents: `--color-gold-accent: #f4cd42;`, `--color-gold-warm: #eab308;`
  - Surfaces: `--color-offwhite-canvas: #f9f9f8;`, `--color-card-bg: #ffffff;`
- **Typography Standards:**
  - Interface: `Public Sans` (16.5px–17.5px root, 1.62 line-height).
  - Accreditation: `Newsreader` (official decrees and certificates).
  - Monospace: `JetBrains Mono` (hashes, clearance tokens, audit counters).
  - Banned: `Inter`, `Roboto`, thick `>=2px` side-tab borders, or unprompted purple gradients.

---

## 4. Playwright Autonomous Mission: "Does His Own Shit"

The QA Reality Checker equips Playwright with complete autonomy:
- Auto-spawns or detects local PHP test server on `127.0.0.1:8000`.
- Executes full browser journeys across Desktop ($1366\times768$) and Mobile ($375\times812$).
- Captures automated full-page screenshot evidence (`live_war_room.png`, `live_mentorship_modal.png`, `live_accreditation_dossier.png`, etc.).
- Verifies zero console errors and zero layout overflow across all viewports.

---

## 5. Phased Milestones & Acceptance Criteria

- [x] **Phase 8.1: Campaign Pre-Flight & Operations War Room**
  - [x] 6-point pre-launch diagnostic audit service (`CampaignWarRoomService`) & UI in `/admin/setup` (`SetupWarRoomTab.vue`).
  - [x] Live velocity burn-up gauge and departmental completion leaderboard with 85% goal pacing.
  - [x] Non-coercive reminder batches for pending evaluators with zero response links.
  - [x] Autonomous Playwright E2E tests `E2E-07` and `E2E-08` passing with screenshot evidence.
- [x] **Phase 8.2: Faculty Pedagogical Action Plan & Mentorship Lifecycle**
  - [x] Faculty self-reflection & milestone submission workspace in `/faculty`.
  - [x] Chair mentor assignment dropdown & milestone approval modal in `/chair`.
  - [x] Action plan status state machine (`DRAFT`, `SUBMITTED`, `APPROVED`, `IN_PROGRESS`, `RESOLVED`).
  - [x] Autonomous Playwright E2E tests `E2E-09` and `E2E-10` passing with screenshot evidence.
- [x] **Phase 8.3: AACCUP Level III Executive Accreditation Dossier**
  - [x] One-click dossier compiler service in `AccreditationGovernanceService`.
  - [x] Sealed accreditation bundle download (`PLP-AACCUP3-DOSSIER-YYYYMMDD.zip`) and print styles.
  - [x] Facilities separation isolating `TAG_UNCLASSIFIED` from instructional pedagogy.
  - [x] Autonomous Playwright E2E test `E2E-11` passing with print layout audit.
- [x] **Phase 8.4: Quivr Second Brain Qualitative Feedback Explorer**
  - [x] Multi-tenant qualitative query endpoints with strict Chair/Dean scoping (`HTTP 403` cross-department guard).
  - [x] Fast sentiment pill filtering and keyword search input in Chair and Dean portals.
  - [x] Autonomous Playwright E2E test `E2E-12` passing with cross-tenant `403` checks.
- [x] **Phase 8.5: Consolidated Multi-Tier Certification**
  - [x] Multi-tier verification matrix passing 100% (181 automated units across all 5 verification layers).
  - [x] Clean OOP service architecture with `DecoupledEvaluationService` orchestrating dedicated domain services.
  - [x] Mobile ($375\times812$) and Desktop ($1366\times768$) visual audits verified with zero layout overflow.
