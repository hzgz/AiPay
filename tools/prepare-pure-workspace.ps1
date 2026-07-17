param(
    [switch]$PruneFrontendDependencies = $true,
    [switch]$PruneFrontendDist = $true,
    [switch]$PruneBackendRuntime = $true,
    [switch]$PruneReleaseArtifacts = $true,
    [switch]$PruneArchives = $true
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$frontendRoot = Join-Path $repoRoot 'modernization\art-design-admin'
$backendRoot = Join-Path $repoRoot 'modernization\webman-api'
$releaseRoot = Join-Path $repoRoot 'modernization\releases'

function Get-WorkspaceSizeMb {
    $sum = (Get-ChildItem -LiteralPath $repoRoot -Recurse -File -ErrorAction SilentlyContinue | Measure-Object Length -Sum).Sum
    return [math]::Round($sum / 1MB, 2)
}

function Remove-PathSafely {
    param(
        [Parameter(Mandatory = $true)]
        [string]$LiteralPath
    )

    if (-not (Test-Path -LiteralPath $LiteralPath)) {
        return
    }

    $resolved = (Get-Item -LiteralPath $LiteralPath).FullName
    if (-not $resolved.StartsWith($repoRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "refusing to remove path outside repo: $resolved"
    }

    try {
        Remove-Item -LiteralPath $resolved -Recurse -Force -ErrorAction Stop
        Write-Host "[clean] removed $resolved"
        return
    }
    catch {
        if (-not (Test-Path -LiteralPath $resolved)) {
            return
        }
    }

    $emptyMirror = Join-Path $repoRoot 'tools\__empty_delete__'
    if (-not (Test-Path -LiteralPath $emptyMirror)) {
        New-Item -ItemType Directory -Path $emptyMirror | Out-Null
    }

    & robocopy $emptyMirror $resolved /MIR /R:1 /W:1 /NFL /NDL /NJH /NJS /NP | Out-Null
    $code = $LASTEXITCODE
    if ($code -gt 7) {
        throw "robocopy cleanup failed for $resolved with exit code $code"
    }

    Remove-Item -LiteralPath $resolved -Recurse -Force -ErrorAction SilentlyContinue
    if (Test-Path -LiteralPath $resolved) {
        try {
            if ((Get-Item -LiteralPath $resolved -ErrorAction SilentlyContinue) -is [System.IO.DirectoryInfo]) {
                [System.IO.Directory]::Delete($resolved, $true)
            }
            else {
                [System.IO.File]::Delete($resolved)
            }
        }
        catch {
        }
    }

    if (Test-Path -LiteralPath $resolved) {
        Remove-Item -LiteralPath $resolved -Force -ErrorAction SilentlyContinue
    }

    if (Test-Path -LiteralPath $resolved) {
        throw "failed to remove $resolved"
    }

    Write-Host "[clean] removed $resolved"
}

function Remove-ArchivesInRepo {
    $extensions = @('.zip', '.rar', '.7z', '.tar', '.gz', '.tgz')
    Get-ChildItem -LiteralPath $repoRoot -Recurse -File -ErrorAction SilentlyContinue |
        Where-Object { $extensions -contains $_.Extension.ToLowerInvariant() } |
        ForEach-Object {
            Remove-PathSafely -LiteralPath $_.FullName
        }
}

$sizeBeforeMb = Get-WorkspaceSizeMb
Write-Host "[clean] workspace size before: $sizeBeforeMb MB"

if ($PruneFrontendDependencies) {
    Remove-PathSafely -LiteralPath (Join-Path $frontendRoot 'node_modules')
}

if ($PruneFrontendDist) {
    Remove-PathSafely -LiteralPath (Join-Path $frontendRoot 'dist')
}

if ($PruneBackendRuntime) {
    Remove-PathSafely -LiteralPath (Join-Path $backendRoot 'runtime')
}

if ($PruneReleaseArtifacts) {
    Remove-PathSafely -LiteralPath $releaseRoot
}

@(
    (Join-Path $frontendRoot '.codex-logs'),
    (Join-Path $frontendRoot 'vite-8132.log'),
    (Join-Path $frontendRoot 'vite-8132.err.log'),
    (Join-Path $frontendRoot '.vite-8132.stderr.log'),
    (Join-Path $frontendRoot '.vite-8132.stdout.log'),
    (Join-Path $backendRoot '.webman.stderr.log'),
    (Join-Path $backendRoot '.webman.stdout.log')
) | ForEach-Object {
    Remove-PathSafely -LiteralPath $_
}

if ($PruneArchives) {
    Remove-ArchivesInRepo
}

$emptyMirror = Join-Path $repoRoot 'tools\__empty_delete__'
if (Test-Path -LiteralPath $emptyMirror) {
    Remove-Item -LiteralPath $emptyMirror -Recurse -Force -ErrorAction SilentlyContinue
}

$sizeAfterMb = Get-WorkspaceSizeMb
Write-Host "[clean] workspace size after: $sizeAfterMb MB"
Write-Host "[clean] reclaimed: $([math]::Round($sizeBeforeMb - $sizeAfterMb, 2)) MB"
