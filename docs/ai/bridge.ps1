[CmdletBinding()]
param(
    [ValidateSet('check', 'status', 'run')]
    [string] $Action = 'check',
    [string] $RepoPath = '',
    [string] $TaskPath = '',
    [switch] $Approve,
    [ValidateRange(5, 7200)]
    [int] $TimeoutSeconds = 900
)

$ErrorActionPreference = 'Stop'
$script:ExpectedBranch = 'Correção-visua/proteção'
$script:ExpectedRepository = 'henrimagri/houseFlipper'
$script:MaximumTaskBytes = 32768
$script:Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

try {
    [Console]::InputEncoding = $script:Utf8NoBom
    [Console]::OutputEncoding = $script:Utf8NoBom
    $OutputEncoding = $script:Utf8NoBom
} catch {
    # Hosts without a mutable console still work because file encodings are explicit.
}

function Get-FullPath {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $BasePath
    )
    if ([System.IO.Path]::IsPathRooted($Path)) {
        return [System.IO.Path]::GetFullPath($Path)
    }
    return [System.IO.Path]::GetFullPath((Join-Path $BasePath $Path))
}

function Find-RepositoryRoot {
    param([Parameter(Mandatory = $true)][string] $StartPath)
    $current = New-Object System.IO.DirectoryInfo((Get-FullPath -Path $StartPath -BasePath $PSScriptRoot))
    while ($null -ne $current) {
        $hasGit = Test-Path -LiteralPath (Join-Path $current.FullName '.git')
        $hasLaravel = (Test-Path -LiteralPath (Join-Path $current.FullName 'artisan')) -and
            (Test-Path -LiteralPath (Join-Path $current.FullName 'composer.json'))
        $hasFrontend = Test-Path -LiteralPath (Join-Path $current.FullName 'frontend/package.json')
        if ($hasGit -and $hasLaravel -and $hasFrontend) { return $current.FullName }
        $current = $current.Parent
    }
    throw "Não foi possível localizar a raiz do houseFlipper a partir de '$StartPath'. Esperados: .git, artisan, composer.json e frontend/package.json."
}

function Invoke-NativeText {
    param(
        [Parameter(Mandatory = $true)][string] $Executable,
        [Parameter(Mandatory = $true)][string[]] $Arguments,
        [switch] $AllowFailure
    )
    $previousErrorAction = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $lines = @(& $Executable @Arguments 2>&1 | ForEach-Object { $_.ToString() })
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    $text = ($lines -join [Environment]::NewLine).Trim()
    if (($exitCode -ne 0) -and (-not $AllowFailure)) {
        throw "Comando falhou (código $exitCode): $Executable $($Arguments -join ' ')`n$text"
    }
    return [pscustomobject]@{ ExitCode = $exitCode; Text = $text }
}

function Quote-NativeArgument {
    param([Parameter(Mandatory = $true)][string] $Value)
    if ($Value -notmatch '[\s"]') { return $Value }
    return '"' + ($Value -replace '(\\*)"', '$1$1\"' -replace '(\\+)$', '$1$1') + '"'
}

function Protect-LogText {
    param([AllowEmptyString()][string] $Text)
    if ([string]::IsNullOrEmpty($Text)) { return '' }
    $safe = $Text
    $safe = [regex]::Replace($safe, '(?i)(sk-(?:proj-|svcacct-)?[a-z0-9_-]{20,})', '[REDACTED_OPENAI_KEY]')
    $safe = [regex]::Replace($safe, '(?i)(gh[opusr]_[a-z0-9]{20,})', '[REDACTED_GITHUB_TOKEN]')
    $safe = [regex]::Replace($safe, '(?i)(bearer\s+)[a-z0-9._~+/-]{16,}', '$1[REDACTED]')
    $safe = [regex]::Replace($safe, '(?im)^([A-Z][A-Z0-9_]*(?:KEY|TOKEN|SECRET|PASSWORD)\s*[=:]\s*).+$', '$1[REDACTED]')
    return $safe
}

function Get-Sha256 {
    param([Parameter(Mandatory = $true)][string] $Path)
    $stream = [System.IO.File]::OpenRead($Path)
    $algorithm = [System.Security.Cryptography.SHA256]::Create()
    try {
        $bytes = $algorithm.ComputeHash($stream)
        return ([System.BitConverter]::ToString($bytes) -replace '-', '').ToLowerInvariant()
    } finally {
        $algorithm.Dispose()
        $stream.Dispose()
    }
}

function Get-TaskMetadata {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "Arquivo de tarefa não encontrado: $Path" }
    $item = Get-Item -LiteralPath $Path
    if (($item.Length -le 0) -or ($item.Length -gt $script:MaximumTaskBytes)) {
        throw "Tarefa rejeitada: tamanho deve estar entre 1 e $($script:MaximumTaskBytes) bytes; recebido $($item.Length)."
    }
    $content = [System.IO.File]::ReadAllText($item.FullName, [System.Text.Encoding]::UTF8)
    if ($content -notmatch '(?m)^# HOUSEFLIPPER_TASK_V1\s*$') { throw 'Tarefa rejeitada: cabeçalho exato "# HOUSEFLIPPER_TASK_V1" ausente.' }
    $idMatch = [regex]::Match($content, '(?m)^Task-ID:\s*([a-zA-Z0-9][a-zA-Z0-9._-]{2,63})\s*$')
    $sourceMatch = [regex]::Match($content, '(?m)^Origem:\s*(local-human)\s*$')
    $repositoryMatch = [regex]::Match($content, '(?m)^Repositório:\s*henrimagri/houseFlipper\s*$')
    $branchMatch = [regex]::Match($content, '(?m)^Branch:\s*Correção-visua/proteção\s*$')
    if (-not $idMatch.Success) { throw 'Tarefa rejeitada: Task-ID ausente ou inválido.' }
    if (-not $sourceMatch.Success) { throw 'Tarefa rejeitada: Origem deve ser "local-human".' }
    if (-not $repositoryMatch.Success) { throw "Tarefa rejeitada: Repositório deve ser '$($script:ExpectedRepository)'." }
    if (-not $branchMatch.Success) { throw "Tarefa rejeitada: Branch deve ser '$($script:ExpectedBranch)'." }
    $hash = Get-Sha256 -Path $item.FullName
    return [pscustomobject]@{ Content = $content; Hash = $hash; Id = $idMatch.Groups[1].Value; Path = $item.FullName; Bytes = $item.Length }
}

function Read-State {
    param([Parameter(Mandatory = $true)][string] $Path)
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { return [pscustomobject]@{ completed = @() } }
    try {
        $state = Get-Content -LiteralPath $Path -Raw -Encoding UTF8 | ConvertFrom-Json
        if ($null -eq $state.completed) { $state | Add-Member -NotePropertyName completed -NotePropertyValue @() }
        return $state
    } catch { throw "Estado local inválido em '$Path': $($_.Exception.Message)" }
}

function Write-State {
    param([Parameter(Mandatory = $true)] $State, [Parameter(Mandatory = $true)][string] $Path)
    $json = $State | ConvertTo-Json -Depth 6
    [System.IO.File]::WriteAllText($Path, $json + [Environment]::NewLine, $script:Utf8NoBom)
}

function Stop-ProcessTree {
    param([Parameter(Mandatory = $true)][System.Diagnostics.Process] $Process)
    if ($Process.HasExited) { return }
    $taskKill = Join-Path $env:SystemRoot 'System32/taskkill.exe'
    if (Test-Path -LiteralPath $taskKill -PathType Leaf) {
        $previousErrorAction = $ErrorActionPreference
        try {
            $ErrorActionPreference = 'Continue'
            & $taskKill /PID $Process.Id /T /F 2>&1 | Out-Null
            return
        } catch {
        } finally {
            $ErrorActionPreference = $previousErrorAction
        }
    }
    try { $Process.Kill() } catch { }
}

function Invoke-CodexTask {
    param(
        [Parameter(Mandatory = $true)][string] $CodexPath,
        [Parameter(Mandatory = $true)][string] $RepositoryRoot,
        [Parameter(Mandatory = $true)][string] $Prompt,
        [Parameter(Mandatory = $true)][string] $LastMessagePath,
        [Parameter(Mandatory = $true)][int] $Timeout
    )
    $arguments = @('--ask-for-approval', 'never', 'exec', '--sandbox', 'workspace-write', '--ephemeral', '--color', 'never', '--output-last-message', $LastMessagePath, '-C', $RepositoryRoot, '-')
    $argumentLine = ($arguments | ForEach-Object { Quote-NativeArgument -Value $_ }) -join ' '
    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $CodexPath
    $startInfo.Arguments = $argumentLine
    $startInfo.WorkingDirectory = $RepositoryRoot
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.StandardOutputEncoding = $script:Utf8NoBom
    $startInfo.StandardErrorEncoding = $script:Utf8NoBom
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo
    try {
        if (-not $process.Start()) { throw 'O processo Codex não pôde ser iniciado.' }
        $stdoutTask = $process.StandardOutput.ReadToEndAsync()
        $stderrTask = $process.StandardError.ReadToEndAsync()
        $process.StandardInput.Write($Prompt)
        $process.StandardInput.Close()
        $timedOut = -not $process.WaitForExit($Timeout * 1000)
        if ($timedOut) {
            Stop-ProcessTree -Process $process
            $process.WaitForExit()
        }
        return [pscustomobject]@{
            ExitCode = $(if ($timedOut) { 124 } else { $process.ExitCode })
            TimedOut = $timedOut
            StdOut = $stdoutTask.GetAwaiter().GetResult()
            StdErr = $stderrTask.GetAwaiter().GetResult()
        }
    } finally {
        if (($null -ne $process) -and (-not $process.HasExited)) { Stop-ProcessTree -Process $process }
        $process.Dispose()
    }
}

$repositoryRoot = if ([string]::IsNullOrWhiteSpace($RepoPath)) {
    Find-RepositoryRoot -StartPath $PSScriptRoot
} else {
    Find-RepositoryRoot -StartPath (Get-FullPath -Path $RepoPath -BasePath $PSScriptRoot)
}
$resolvedTaskPath = if ([string]::IsNullOrWhiteSpace($TaskPath)) { Join-Path $repositoryRoot 'docs/ai/NEXT_TASK.md' } else { Get-FullPath -Path $TaskPath -BasePath $repositoryRoot }

$gitCommand = @(Get-Command git -CommandType Application -ErrorAction SilentlyContinue) | Select-Object -First 1
if ($null -eq $gitCommand) { throw 'Git não encontrado no PATH.' }
$gitPath = $gitCommand.Source
$inside = Invoke-NativeText -Executable $gitPath -Arguments @('-C', $repositoryRoot, 'rev-parse', '--is-inside-work-tree')
if ($inside.Text -ne 'true') { throw "O caminho '$repositoryRoot' não é um worktree Git." }
$gitRoot = (Invoke-NativeText -Executable $gitPath -Arguments @('-C', $repositoryRoot, 'rev-parse', '--show-toplevel')).Text
if ((Get-FullPath -Path $gitRoot -BasePath $repositoryRoot).TrimEnd('\', '/') -ne $repositoryRoot.TrimEnd('\', '/')) { throw "Raiz Git inesperada: '$gitRoot'. Esperada: '$repositoryRoot'." }
$branch = (Invoke-NativeText -Executable $gitPath -Arguments @('-C', $repositoryRoot, 'branch', '--show-current')).Text
if ($branch -cne $script:ExpectedBranch) { throw "Branch atual '$branch' difere da autorizada '$($script:ExpectedBranch)'. A ponte não altera branches." }
$head = (Invoke-NativeText -Executable $gitPath -Arguments @('-C', $repositoryRoot, 'rev-parse', 'HEAD')).Text
$worktreeStatus = (Invoke-NativeText -Executable $gitPath -Arguments @('-C', $repositoryRoot, 'status', '--porcelain')).Text

$codexCommand = @(Get-Command codex -CommandType Application -ErrorAction SilentlyContinue) | Select-Object -First 1
if ($null -eq $codexCommand) { throw 'Codex CLI não encontrado no PATH.' }
$codexPath = $codexCommand.Source
$version = (Invoke-NativeText -Executable $codexPath -Arguments @('--version')).Text
if ($version -notmatch '^codex-cli\s+([0-9]+\.[0-9]+\.[0-9]+[^\s]*)$') { throw "Versão do Codex CLI não reconhecida: '$version'." }
$auth = Invoke-NativeText -Executable $codexPath -Arguments @('login', 'status') -AllowFailure
if (($auth.ExitCode -ne 0) -or ($auth.Text -notmatch '(?i)logged in|autenticad')) { throw 'Codex CLI não está autenticado. Execute o login oficial antes de usar a ponte.' }

$task = Get-TaskMetadata -Path $resolvedTaskPath
$logDirectory = Join-Path $repositoryRoot 'storage/logs/ai-bridge'
New-Item -ItemType Directory -Force -Path $logDirectory | Out-Null
$statePath = Join-Path $logDirectory 'state.json'
$state = Read-State -Path $statePath
$completed = @($state.completed)
$duplicate = @($completed | Where-Object { ($_.task_id -eq $task.Id) -or ($_.sha256 -eq $task.Hash) }).Count -gt 0

Write-Host "Repositório: $repositoryRoot"
Write-Host "Branch: $branch"
Write-Host "HEAD: $head"
Write-Host "Codex CLI: $version"
Write-Host 'Autenticação Codex: ativa (detalhes ocultos)'
Write-Host "Tarefa: $($task.Id) | $($task.Bytes) bytes | SHA256 $($task.Hash)"
Write-Host "Alterações locais Git: $(@($worktreeStatus -split "`n" | Where-Object { $_ }).Count)"

if ($Action -eq 'check') {
    if ($duplicate) { Write-Warning 'A tarefa já consta como concluída e não será executada novamente.' }
    Write-Host 'CHECK_OK'
    exit 0
}
if ($Action -eq 'status') {
    if ($completed.Count -eq 0) { Write-Host 'Nenhuma execução concluída registrada.' } else {
        $last = $completed[-1]
        Write-Host "Última execução: task=$($last.task_id) exit=$($last.exit_code) concluída=$($last.completed_at)"
        Write-Host "Relatório local: $($last.report)"
    }
    if ($duplicate) { Write-Host 'Estado da tarefa atual: concluída (duplicata bloqueada).' } else { Write-Host 'Estado da tarefa atual: pronta para revisão e aprovação humana.' }
    exit 0
}

if (-not $Approve) { throw 'Execução bloqueada. Revise o arquivo e use -Action run -Approve para registrar a aprovação humana.' }
if ($duplicate) { throw "Execução bloqueada: Task-ID '$($task.Id)' ou o mesmo SHA256 já foi concluído." }

$lockPath = Join-Path $logDirectory 'bridge.lock'
$lockStream = $null
try {
    try { $lockStream = New-Object System.IO.FileStream($lockPath, [System.IO.FileMode]::OpenOrCreate, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None) }
    catch { throw 'Já existe uma execução da ponte em andamento; nenhuma segunda execução foi iniciada.' }
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $reportPath = Join-Path $logDirectory "$stamp-$($task.Id)-report.txt"
    $lastMessagePath = Join-Path $logDirectory "$stamp-$($task.Id)-last-message.txt"
    $prompt = @"
Leia AGENTS.md e docs/ai/IMPLEMENTATION_PROGRESS.md. Execute somente a tarefa autorizada abaixo no repositório $($script:ExpectedRepository), branch exata $($script:ExpectedBranch).

Limites obrigatórios: não altere branch; não faça push, merge, deploy, compras ou chamadas pagas; não revele credenciais; não leia conversas privadas do ChatGPT. Se a tarefa for de diagnóstico, não modifique arquivos. Termine com um relatório curto, factual e verificável.

$($task.Content)
"@
    $startedAt = Get-Date
    $result = Invoke-CodexTask -CodexPath $codexPath -RepositoryRoot $repositoryRoot -Prompt $prompt -LastMessagePath $lastMessagePath -Timeout $TimeoutSeconds
    $completedAt = Get-Date
    $safeLastMessage = ''
    if (Test-Path -LiteralPath $lastMessagePath) {
        $lastMessage = [System.IO.File]::ReadAllText($lastMessagePath, [System.Text.Encoding]::UTF8)
        $safeLastMessage = Protect-LogText -Text $lastMessage
        [System.IO.File]::WriteAllText($lastMessagePath, $safeLastMessage, $script:Utf8NoBom)
    }
    $reportLines = @(
        "task_id=$($task.Id)",
        "task_sha256=$($task.Hash)",
        "branch=$branch",
        "head_before=$head",
        "started_at=$($startedAt.ToString('o'))",
        "completed_at=$($completedAt.ToString('o'))",
        "duration_seconds=$([math]::Round(($completedAt - $startedAt).TotalSeconds, 3))",
        "exit_code=$($result.ExitCode)",
        "timed_out=$($result.TimedOut.ToString().ToLowerInvariant())"
    )
    if (-not [string]::IsNullOrWhiteSpace($safeLastMessage)) {
        $reportLines += @('', 'last_message:', $safeLastMessage)
    } elseif ($result.ExitCode -ne 0) {
        $diagnostic = Protect-LogText -Text (($result.StdErr + [Environment]::NewLine + $result.StdOut).Trim())
        if ($diagnostic.Length -gt 2000) { $diagnostic = $diagnostic.Substring(0, 2000) + ' [truncated]' }
        $reportLines += @('', 'diagnostic:', $diagnostic)
    }
    [System.IO.File]::WriteAllText($reportPath, ($reportLines -join [Environment]::NewLine) + [Environment]::NewLine, $script:Utf8NoBom)
    if ($result.TimedOut) { throw "Codex excedeu o limite de $TimeoutSeconds segundos, foi cancelado e terminou com código 124. Consulte o relatório local: $reportPath" }
    if ($result.ExitCode -ne 0) { throw "Codex terminou com código $($result.ExitCode). Consulte o relatório local: $reportPath" }
    $entry = [pscustomobject]@{
        task_id = $task.Id; sha256 = $task.Hash; branch = $branch; head_before = $head
        started_at = $startedAt.ToString('o'); completed_at = $completedAt.ToString('o')
        duration_seconds = [math]::Round(($completedAt - $startedAt).TotalSeconds, 3)
        exit_code = $result.ExitCode; report = $reportPath
    }
    $state.completed = @($completed) + @($entry)
    Write-State -State $state -Path $statePath
    Write-Host "RUN_OK | relatório local: $reportPath"
} finally {
    if ($null -ne $lockStream) { $lockStream.Dispose() }
    Remove-Item -LiteralPath $lockPath -Force -ErrorAction SilentlyContinue
}
