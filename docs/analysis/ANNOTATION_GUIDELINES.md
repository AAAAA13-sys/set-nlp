# EvaluaTech Sentiment Annotation and Taxonomy Guidelines

**Document Version:** 1.0  
**Date:** November 10, 2026  
**Audience:** Academic Affairs, QA Reality Checkers, NLP Engineers  
**Dataset Scope:** Student Evaluation of Teachers (SET) — Multilingual Corpus (English, Tagalog, Taglish)

---

## 1. Principles of Student Evaluation Annotation

Student sentiment feedback in Philippine Higher Education Institutions (HEIs) presents unique linguistic and cultural nuances:
1. **Multilingual Taglish Code-Switching:** Students mix English pedagogical terminology (*"quizzes"*, *"syllabus"*, *"rubrics"*, *"consultation"*, *"pacing"*) with Tagalog verbs, particles, and affective markers (*"hindi"*, *"sobrang"*, *"nakakainip"*, *"matino"*, *"sulit"*, *"terror"*).
2. **Pedagogical Valence Independence:** Sentiment polarity reflects student perception of instructional delivery, assessment, conduct, and support—not the difficulty of the course itself. A rigorous course delivered fairly and clearly is **Positive**, while an easy course with absentee instruction or uncommunicated grades is **Negative**.
3. **Contrastive Shift Principle:** In compound sentences connected by contrastive conjunctions (*"pero"*, *"kaso"*, *"ngunit"*, *"subalit"*, *"however"*, *"but"*), the clause following the conjunction reflects the final evaluative judgment and carries higher cognitive weight.

---

## 2. Valence Classification Scheme

Every evaluation narrative is classified into one of three primary valence categories:

### 2.1 Positive (`Positive`)
- **Definition:** Expresses satisfaction, appreciation, clarity, fairness, effective delivery, student support, or constructive encouragement.
- **Linguistic Indicators:**
  - *English:* "clear", "helpful", "organized", "approachable", "dedicated", "great explanations", "fair grading".
  - *Tagalog/Taglish:* "malinaw", "mahusay", "mabait", "magaling", "matino", "makatarungan", "madaling lapitan", "sulit ang klase".
- **Polarity Range:** $P \ge +0.05$

### 2.2 Neutral (`Neutral`)
- **Definition:** Factual observations without strong affective sentiment, average/standard performance (*"sakto lang"*, *"okay naman"*, *"average"*), balanced feedback where positive and negative elements cancel out evenly without strong contrast, or descriptive administrative remarks.
- **Linguistic Indicators:**
  - *English:* "standard pacing", "follows the syllabus", "average lectures", "as expected".
  - *Tagalog/Taglish:* "sakto lang", "okay naman ang takbo", "katamtaman", "karaniwan", "sapat naman".
- **Polarity Range:** $-0.05 < P < +0.05$

### 2.3 Negative (`Negative`)
- **Definition:** Expresses dissatisfaction, unfairness, excessive delay in grading, poor communication, disrespect, disorganization, absenteeism, or instructional neglect.
- **Linguistic Indicators:**
  - *English:* "confusing", "disorganized", "unfair", "unapproachable", "late consistently", "no feedback", "harsh".
  - *Tagalog/Taglish:* "magulo", "hindi nagtuturo", "walang kwenta", "walang rubrics", "masungit", "terror", "nakaka-anxious", "paboritismo", "bagsak agad".
- **Polarity Range:** $P \le -0.05$

### 2.4 Handling Mixed Sentiment
- When a comment contains both positive and negative clauses:
  1. If linked with a contrastive conjunction (*"pero"*, *"kaso"*, *"however"*), the trailing clause dictates the dominant sentiment:
     - *"Mabait si ma'am pero hindi talaga maintindihan magturo."* $\rightarrow$ **Negative** (Final evaluation focuses on teaching incomprehensibility).
     - *"Medyo mabilis magturo ngunit napakalinaw ng mga slides at madaling lapitan."* $\rightarrow$ **Positive** (Final evaluation affirms clarity and support).
  2. If balanced without contrastive shift (*"Minsan mabilis, minsan malinaw"*), classify as **Neutral**.

---

## 3. Pedagogical Domain Taxonomy & Aspect Boundaries

Comments are mapped to the 5 institutional Likert clusters when addressing faculty performance, or marked as **Unclassified / Needs Review** when non-pedagogical:

| Cluster | Institutional Domain | Focus Scope | Key Indicators |
|---|---|---|---|
| **1** | **Pedagogical Delivery** | Teaching clarity, mastery, examples, pacing, slide structure | *nagpapaliwanag, malinaw, turo, lecture, slides, examples, coding, pacing* |
| **2** | **Assessment & Grading** | Exam fairness, rubric transparency, score turnaround, quizzes | *pagsusulit, grado, marka, exam, quiz, rubric, deduction, score, feedback* |
| **3** | **Classroom Management** | Punctuality, attendance, syllabus adherence, discipline | *late, oras, attendance, schedule, pumasok, syllabus, time management* |
| **4** | **Professional Conduct** | Respect, impartiality, decorum, temperament, ethics | *masungit, galit, bastos, respectful, professional, bias, paboritismo, sigaw* |
| **5** | **Student Support & Mentorship** | Consultations, responsiveness, patience, academic guidance | *consultation, lapitan, responsive, email, chat, gabay, tulong, support* |
| **N/A** | **Unclassified / Needs Review** | Facilities (library, lab hardware, Wi-Fi, AC), Extracurriculars, or Insufficient Evidence | *library, silid-aklatan, aircon, wifi, projector, club, varsity, campus, org* |

> **Institutional Rule:** Administrative participation reports and teacher dossier analytics must NEVER silently force facility or extracurricular complaints into teacher pedagogical scores. Such feedback is flagged as `Unclassified / Needs Review` for routing to Campus Facilities or Student Affairs.

---

## 4. Dataset Partitions and Integrity Safeguards

- **Total Corpus:** 800 labeled multilingual student evaluation comments.
- **Stratification:** Stratified across 9 strata ($3\text{ Languages} \times 3\text{ Sentiments}$).
- **Split Proportions:** 70% Development Partition (560 rows) / 30% Untouched Held-Out Test Partition (240 rows).
- **Duplicate Handling:** Exact duplicate rows (ID 202 and ID 636: *"Makatarungan ang ibinigay na marka batay sa ipinakitang husay sa pag-uulat at pagsusulit."*) are strictly bound to the Development partition to prevent test set data leakage.
- **Reference Independence:** Reference ratings and human sentiment labels are strictly withheld from prediction model inputs during inference and testing.
