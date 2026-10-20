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

& $phpExe "$auditDir\setup-itbi.php"
if ($LASTEXITCODE -ne 0) { throw 'ITBI setup failed' }

$workers = @()
foreach ($index in 0, 1) {
    $prefix = "$auditDir\itbi-$index"
    foreach ($suffix in '.ready', '.go', '.result', '.stdout', '.stderr') {
        Remove-Item -LiteralPath "$prefix$suffix" -Force -ErrorAction SilentlyContinue
    }
    $workers += Start-Process -FilePath $phpExe -ArgumentList @("`"$auditDir\itbi-worker.php`"", "`"$prefix`"") -WindowStyle Hidden -PassThru -RedirectStandardOutput "$prefix.stdout" -RedirectStandardError "$prefix.stderr"
}

$deadline = (Get-Date).AddSeconds(15)
while (!(Test-Path "$auditDir\itbi-0.ready") -or !(Test-Path "$auditDir\itbi-1.ready")) {
    if ((Get-Date) -gt $deadline) { throw 'ITBI workers did not reach barrier' }
    Start-Sleep -Milliseconds 25
}
foreach ($index in 0, 1) { Set-Content "$auditDir\itbi-$index.go" 'go' }
foreach ($worker in $workers) {
    if (!$worker.WaitForExit(30000)) { throw 'ITBI worker exceeded observation timeout' }
}

& $phpExe "$auditDir\verify-itbi.php"
if ($LASTEXITCODE -ne 0) { throw 'ITBI concurrency invariant failed' }
