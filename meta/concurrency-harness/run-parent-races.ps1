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

$phases = @('project-create','expense-create','document-create','task-create','event-create','move-source-delete','move-destination-delete','project-update-delete')
$evidence = @()
foreach ($phase in $phases) {
    foreach ($variant in @('natural', 'mutation-first')) {
        & $phpExe "$auditDir\setup-parent-races.php" $phase
        if ($LASTEXITCODE -ne 0) { throw "Setup failed for $phase/$variant" }
        & $phpExe "$auditDir\plan-parent-races.php" $variant
        if ($LASTEXITCODE -ne 0) { throw "Plan failed for $phase/$variant" }
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
            if ((Get-Date) -gt $deadline) { throw "Workers did not reach barrier for $phase/$variant" }
            Start-Sleep -Milliseconds 25
        }
        foreach ($index in 0, 1) { Set-Content "$auditDir\$phase-$index.go" 'go' }
        foreach ($worker in $workers) {
            if (!$worker.WaitForExit(20000)) { throw "Worker exceeded observation timeout for $phase/$variant" }
        }
        $verification = & $phpExe "$auditDir\verify-parent-races.php" $variant
        if ($LASTEXITCODE -ne 0) { throw "Invariant failed for $phase/$variant`: $verification" }
        $evidence += $verification | ConvertFrom-Json
    }
}

[pscustomobject]@{
    database = 'MariaDB isolated'
    scenarios = $evidence
    passed = ($evidence.Count -eq ($phases.Count * 2) -and @($evidence | Where-Object { -not $_.passed }).Count -eq 0)
} | ConvertTo-Json -Depth 8
