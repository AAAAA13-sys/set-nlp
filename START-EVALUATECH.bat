@echo off
setlocal EnableDelayedExpansion
cd /d "%~dp0"

set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"

echo ========================================================================
echo  EvaluaTech - Automated Student Evaluation of Teachers (SET) Platform
echo  Dual-Token Cryptographic Decoupling ^& Python VADER NLP Analytics
echo ========================================================================

if /I "%~1"=="--verify" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\verify-all.ps1"
    exit /b !ERRORLEVEL!
)

if /I "%~1"=="--benchmark" (
    echo.
    echo [Benchmark 1/2] Running Empirical Chapter III Benchmarks...
    python "%~dp0tests\load\verify_plp_benchmarks.py"
    if !ERRORLEVEL! NEQ 0 exit /b !ERRORLEVEL!

    echo.
    echo [Benchmark 2/2] Running High-Concurrency Stress Benchmark [200+ Requests]...
    python "%~dp0tests\load\run_concurrent_benchmark.py"
    exit /b !ERRORLEVEL!
)

echo.
echo [Step 1/3] Running Multi-Tier Verification Suite (85+ Tests)...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\verify-all.ps1"
if !ERRORLEVEL! NEQ 0 (
    echo [ERROR] Verification suite reported failures. Aborting startup.
    exit /b !ERRORLEVEL!
)

echo.
echo [Step 2/3] Starting Python NLP Sentiment Microservice on http://127.0.0.1:5000 ...
start "EvaluaTech Python NLP Daemon" /min cmd /c "python -m analytics.app"

echo.
echo [Step 3/3] Starting EvaluaTech Laravel 12 + Vue 3 Portal on http://127.0.0.1:8000 ...
echo   - Student Queue (2024-10892): http://127.0.0.1:8000/queue
echo   - Faculty Double-Blind View : http://127.0.0.1:8000/faculty
echo   - Dean Analytical Overview  : http://127.0.0.1:8000/dean
echo   - Registrar Admin Console   : http://127.0.0.1:8000/admin
echo   - PLP calamanCy NLP Lab     : http://127.0.0.1:8000/nlp-lab
echo.
"%PHP_EXE%" -S 127.0.0.1:8000 -t public public\index.php
