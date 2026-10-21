@echo off
setlocal
echo Esta acao executara uma unica tarefa revisada em docs\ai\NEXT_TASK.md.
echo Feche esta janela agora se voce nao aprovou o conteudo do arquivo.
choice /C SN /N /M "Executar a tarefa aprovada? [S/N]: "
if errorlevel 2 exit /b 2
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0..\..\docs\ai\bridge.ps1" -Action run -Approve
set "exit_code=%ERRORLEVEL%"
echo.
if not "%exit_code%"=="0" pause
exit /b %exit_code%
