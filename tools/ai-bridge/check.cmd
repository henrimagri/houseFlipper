@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0..\..\docs\ai\bridge.ps1" -Action check
set "exit_code=%ERRORLEVEL%"
echo.
if not "%exit_code%"=="0" pause
exit /b %exit_code%
