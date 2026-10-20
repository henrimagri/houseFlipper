$ErrorActionPreference = 'Stop'
$auditDir = $PSScriptRoot
$phpExe = 'C:\Users\thaym\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mariadb'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '13308'
$env:DB_DATABASE = 'houseflipper_audit'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = (Get-Content -LiteralPath "$auditDir\db-password.private" -Raw).Trim()
$env:CACHE_STORE = 'array'

& $phpExe "$auditDir\setup-task-stage.php"
if ($LASTEXITCODE -ne 0) { throw 'Task/stage setup failed' }

foreach ($phase in @('task-derived', 'task-conflict', 'stage-conflict')) {
    & $phpExe "$auditDir\plan-task-stage.php" $phase
    if ($LASTEXITCODE -ne 0) { throw "Plan failed for $phase" }
    $workers = @()
    foreach ($index in 0, 1) {
        $prefix = "$auditDir\$phase-$index"
        foreach ($suffix in '.ready', '.go', '.result', '.stdout', '.stderr') {
            Remove-Item -LiteralPath "$prefix$suffix" -Force -ErrorAction SilentlyContinue
        }
        $workers += Start-Process -FilePath $phpExe -ArgumentList @("`"$auditDir\worker.php`"", "`"$prefix.job`"", "`"$prefix`"") -WindowStyle Hidden -PassThru -RedirectStandardOutput "$prefix.stdout" -RedirectStandardError "$prefix.stderr"
    }
    $deadline = (Get-Date).AddSeconds(15)
    while (!(Test-Path "$auditDir\$phase-0.ready") -or !(Test-Path "$auditDir\$phase-1.ready")) {
        if ((Get-Date) -gt $deadline) { throw "Workers did not reach barrier for $phase" }
        Start-Sleep -Milliseconds 25
    }
    foreach ($index in 0, 1) { Set-Content "$auditDir\$phase-$index.go" 'go' }
    foreach ($worker in $workers) {
        if (!$worker.WaitForExit(20000)) { throw "Worker exceeded observation timeout for $phase" }
    }
}

& $phpExe "$auditDir\verify-task-stage.php"
if ($LASTEXITCODE -ne 0) { throw 'Task/stage concurrency invariant failed' }
