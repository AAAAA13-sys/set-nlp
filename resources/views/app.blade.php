<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pamantasan ng Lungsod ng Pasig (PLP) — EvaluaTech Automated Student Evaluation of Teachers (SET) Platform with Dual-Token Cryptographic Decoupling and calamanCy + VADER NLP Sentiment Analytics">
    <title>PAMANTASAN NG LUNGSOD NG PASIG — EvaluaTech Institutional SET Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,800&family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/resources/css/app.css">
    @if (function_exists('vite'))
        @vite(['resources/js/app.js'])
    @else
        <script type="module" src="/resources/js/plp-live-portal.js"></script>
    @endif
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <div id="app" data-platform="EvaluaTech" data-institution="PAMANTASAN NG LUNGSOD NG PASIG" data-reference="https://plpasig.edu.ph/" data-active-term="Fall">
        <header class="navbar-header" role="banner" aria-label="Pamantasan ng Lungsod ng Pasig Institutional Portal Header">
            <div class="plp-top-ribbon">
                <div class="plp-top-ribbon-inner">
                    <span>Republic of the Philippines • City Government of Pasig • Alkalde Joseco St., Kapasigan, Pasig City</span>
                    <span class="plp-ribbon-motto">Official Reference: plpasig.edu.ph • Academic Excellence &amp; Pasigueño Integrity</span>
                </div>
            </div>
            <div class="navbar-container">
                <div class="navbar-brand-row">
                    <div class="brand-identity">
                        <div class="plp-crest-seal" aria-hidden="true">
                            <svg width="34" height="34" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="32" cy="32" r="29" stroke="#FACC15" stroke-width="2.5" stroke-dasharray="3 2" />
                                <circle cx="32" cy="32" r="23" fill="#0B4F26" stroke="#EAB308" stroke-width="1.8" />
                                <path d="M32 14L44 20V31C44 40 38.8 46.8 32 50C25.2 46.8 20 40 20 31V20L32 14Z" fill="#14532D" stroke="#FACC15" stroke-width="1.8" />
                                <path d="M32 20L35.5 27.5L43 28.5L37.5 33.8L39 41.5L32 37.8L25 41.5L26.5 33.8L21 28.5L28.5 27.5L32 20Z" fill="#FACC15" />
                            </svg>
                        </div>
                        <div class="brand-text-stack">
                            <span class="plp-university-title">PAMANTASAN NG LUNGSOD NG PASIG</span>
                            <div class="brand-sub-row">
                                <span class="brand-badge">[PLP • EduMetrics / EvaluaTech]</span>
                                <h1 class="brand-title">Institutional Evaluation Portal</h1>
                            </div>
                        </div>
                    </div>
                    <div class="term-pill">
                        <span>Active Term:</span>
                        <strong>Fall / 1st Semester</strong>
                    </div>
                </div>
            </div>
        </header>
    </div>
</body>
</html>
