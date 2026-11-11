# School AAAAA13 EvaluaTech — Interactive Video Showcase

**System:** School AAAAA13 EvaluaTech (Student Evaluation of Teachers Platform)  
**Video Artifact:** [`evalua_tech_showcase.webm`](evalua_tech_showcase.webm) (14.27 MB)  
**Format:** Native Chromium WebM Video Capture ($1366\times768\text{ px}$)  
**Generator:** `scripts/record_showcase_video.js` via Playwright  
**Date of Recording:** 11 November 2026  

---

## 🎬 Showcase Scene Breakdown

The recorded video walks through all seven fundamental operational stages of the platform with zero mock shortcuts:

| Scene | Timestamp / Stage | Description |
| :---: | :--- | :--- |
| **1** | **Public Institutional Landing Page (`/`)** | Introduces School AAAAA13 branding, serene Coolors pastel periwinkle design system (`#edf2fb` to `#abc4ff`), AACCUP Level III accreditation governance, and the Zero Permits Policy charter. |
| **2** | **Academic Tech Sign-in (`/login`)** | Shows the full-viewport split-screen login, floating card container, institutional kitemark, and RA 10173 data privacy notice. |
| **3** | **Student Workspace & Evaluation Form (`/queue`, `/evaluate/1`)** | Authentic student persona assesses course `CS-401`. Features in-queue rating rubric modal, 25 Likert scale questions scored across 5 pedagogical clusters, 4 structured qualitative professor questions, and instant submit under the Mandatory Timing Policy (0s+ dwell allowed) issuing a signed cryptographic clearance token (`EVT.CLR.*`). |
| **4** | **Student Evaluation Participation Tracker** | University Administrator inspects turnout velocity, section completion bars, and opens polite, non-coercive reminder notices for students with pending evaluations under the Zero Permits Policy. |
| **5** | **Faculty Section Performance Dashboard (`/faculty`)** | Shows double-blind unlock safeguards in action: unlocked sections display aggregated ratings with zero student PII, while unreleased sections display the double-blind lock (`Confirm Grade Submission Status`). Explores the Pedagogical Action Plan workspace. |
| **6** | **Department Chair & Dean Analytics Heatmaps (`/chair`, `/dean`)** | Chair manages faculty standing and performs semantic inquiry in the Quivr Second Brain qualitative feedback drawer. Dean reviews 91.4% college turnout, 4,821 submissions, section completion heatmap matrix, and compiles the AACCUP Level III Executive Accreditation Dossier. |
| **7** | **Campaign Operations War Room** | Final view of the 6-point pre-flight diagnostics engine, 100% readiness score bar, and Departmental Turnout Velocity Leaderboard. |

---

## 🚀 How to Re-Record the Showcase Video

To capture a fresh recording of the showcase video at any time:

```powershell
# Execute the automated Playwright recording engine
node scripts/record_showcase_video.js
```

The generated recording will be saved directly to `docs/showcase/evalua_tech_showcase.webm`.
