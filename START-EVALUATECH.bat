@echo off
setlocal
title School AAAAA13 EvaluaTech — Rust Portal + Python calamanCy ML
cd /d "%~dp0"

if "%HOST%"=="" set HOST=127.0.0.1
if "%PORT%"=="" set PORT=8080
if "%APP_ENV%"=="" set APP_ENV=local
if exist ".venv\Scripts\python.exe" set PYTHON_BIN=%~dp0.venv\Scripts\python.exe

echo ======================================================================
echo  School AAAAA13 EvaluaTech — Faculty Evaluation Portal
echo  Systems & Web UI : Safe Rust + WebAssembly
echo  NLP & Sentiment  : Python (calamanCy + VADER + scikit-learn)
echo  Portal URL       : http://%HOST%:%PORT%
echo ======================================================================

if exist "target\release\evaluatech-server.exe" (
    "target\release\evaluatech-server.exe"
) else (
    cargo run --release -p evaluatech-server
)
