@echo off
setlocal
title School AAAAA13 EvaluaTech — Pure-Rust + WebAssembly Portal
cd /d "%~dp0"

if "%HOST%"=="" set HOST=127.0.0.1
if "%PORT%"=="" set PORT=8080
if "%APP_ENV%"=="" set APP_ENV=local

echo ======================================================================
echo  School AAAAA13 EvaluaTech — Pure-Rust + WebAssembly Portal
echo  Portal URL: http://%HOST%:%PORT%
echo ======================================================================

if exist "target\release\evaluatech-server.exe" (
    "target\release\evaluatech-server.exe"
) else (
    cargo run --release -p evaluatech-server
)
