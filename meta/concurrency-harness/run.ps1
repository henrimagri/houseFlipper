$ErrorActionPreference = 'Stop'
$auditDir = $PSScriptRoot
$phpExe = 'C:\Users\thaym\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
$env:APP_ENV='testing'
$env:DB_CONNECTION='mariadb'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='13308'
$env:DB_DATABASE='houseflipper_audit'
$env:DB_USERNAME='root'
$env:DB_PASSWORD=(Get-Content -LiteralPath "$auditDir\db-password.private" -Raw).Trim()
$env:CACHE_STORE='array'
foreach ($phase in @('analyses','selection','costs')) {
    & $phpExe "$auditDir\plan.php" $phase
    if ($LASTEXITCODE -ne 0) { throw 'Plan failed' }
    $workers = @()
    foreach ($idx in 0,1) {
        $prefix="$auditDir\$phase-$idx"
        $workers += Start-Process -FilePath $phpExe -ArgumentList @("`"$auditDir\worker.php`"", "`"$prefix.job`"", "`"$prefix`"") -WindowStyle Hidden -PassThru -RedirectStandardOutput "$prefix.stdout" -RedirectStandardError "$prefix.stderr"
    }
    $deadline=(Get-Date).AddSeconds(12)
    while (!(Test-Path "$auditDir\$phase-0.ready") -or !(Test-Path "$auditDir\$phase-1.ready")) {
        if ((Get-Date) -gt $deadline) { throw 'Workers did not reach barrier' }
        Start-Sleep -Milliseconds 25
    }
    foreach ($idx in 0,1) { Set-Content "$auditDir\$phase-$idx.go" 'go' }
    foreach ($worker in $workers) {
        if (!$worker.WaitForExit(15000)) { throw 'Live worker exceeded observation timeout; inspect before retrying' }
    }
    foreach ($idx in 0,1) {
        $result=Get-Content "$auditDir\$phase-$idx.result" -Raw | ConvertFrom-Json
        Write-Output "$phase worker $idx HTTP $($result.status)"
        if ($result.status -notin 200,201) { throw 'Concurrent request failed' }
    }
}
& $phpExe "$auditDir\verify.php"
if ($LASTEXITCODE -ne 0) { throw 'Concurrency invariant failed' }
