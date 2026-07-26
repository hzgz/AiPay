# 版权归属 TG:RENBUZAIHA 所有
# 唯一发布路径: https://github.com/hzgz/AiPay.git

[CmdletBinding()]
param(
    [string]$Tag,
    [switch]$SkipFrontendBuild,
    [switch]$SkipWorkspaceCleanup,
    [switch]$SkipPackageVerification
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)
    Write-Host "[INFO] $Message"
}

function Assert-PathExists {
    param(
        [string]$Path,
        [string]$Label
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "$Label not found: $Path"
    }
}

function Ensure-Directory {
    param([string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        New-Item -ItemType Directory -Path $Path | Out-Null
    }
}

function Reset-Directory {
    param([string]$Path)

    if (Test-Path -LiteralPath $Path) {
        Remove-Item -LiteralPath $Path -Recurse -Force
    }

    New-Item -ItemType Directory -Path $Path | Out-Null
}

function Invoke-NativeCommand {
    param(
        [string]$FilePath,
        [string[]]$ArgumentList,
        [string]$WorkingDirectory
    )

    $previousLocation = Get-Location
    try {
        Set-Location -LiteralPath $WorkingDirectory
        & $FilePath @ArgumentList
        if ($LASTEXITCODE -ne 0) {
            throw "Command failed with exit code ${LASTEXITCODE}: $FilePath $($ArgumentList -join ' ')"
        }
    }
    finally {
        Set-Location -LiteralPath $previousLocation
    }
}

function Copy-DirectoryContents {
    param(
        [string]$SourcePath,
        [string]$DestinationPath
    )

    Assert-PathExists -Path $SourcePath -Label 'Source directory'
    Ensure-Directory -Path $DestinationPath

    Get-ChildItem -LiteralPath $SourcePath -Force | ForEach-Object {
        Copy-Item -LiteralPath $_.FullName -Destination $DestinationPath -Recurse -Force
    }
}

function Remove-MatchingChildren {
    param(
        [string]$Root,
        [string[]]$Patterns
    )

    if (-not (Test-Path -LiteralPath $Root)) {
        return
    }

    foreach ($pattern in $Patterns) {
        Get-ChildItem -LiteralPath $Root -Recurse -Force -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -like $pattern } |
            ForEach-Object {
                Remove-Item -LiteralPath $_.FullName -Recurse -Force -ErrorAction SilentlyContinue
            }
    }
}

function Clear-DirectoryContents {
    param([string]$Path)

    Ensure-Directory -Path $Path
    Get-ChildItem -LiteralPath $Path -Force -ErrorAction SilentlyContinue | ForEach-Object {
        Remove-Item -LiteralPath $_.FullName -Recurse -Force
    }
}

function Get-GitValue {
    param(
        [string]$WorkingDirectory,
        [string[]]$Arguments
    )

    try {
        $previousLocation = Get-Location
        Set-Location -LiteralPath $WorkingDirectory
        $value = & git @Arguments 2>$null
        if ($LASTEXITCODE -ne 0) {
            return $null
        }

        return ($value | Select-Object -First 1)
    }
    finally {
        Set-Location -LiteralPath $previousLocation
    }
}

function Verify-Package {
    param([string]$ReleaseRoot)

    $requiredPaths = @(
        'backend\app',
        'backend\config',
        'backend\database',
        'backend\deploy',
        'backend\plugins',
        'backend\public',
        'backend\support',
        'backend\vendor',
        'backend\.env.example',
        'backend\composer.json',
        'backend\composer.lock',
        'backend\start.php',
        'console\index.html',
        'database\install\core-install.sql',
        'database\install\base-schema.sql',
        'database\install\admin-auth-seed.sql',
        'docs\release-package.md',
        'docs\deployment-handbook.md',
        'docs\deployment-verification.md',
        'docs\aapanel-install.md',
        'docs\database-installation.md',
        'docs\rollback-playbook.md',
        'README-FIRST.txt',
        'release-manifest.json'
    )

    foreach ($relativePath in $requiredPaths) {
        $fullPath = Join-Path $ReleaseRoot $relativePath
        if (-not (Test-Path -LiteralPath $fullPath)) {
            throw "Release package verification failed. Missing path: $relativePath"
        }
    }

    $unexpectedPaths = @(
        (Join-Path $ReleaseRoot 'backend\.env'),
        (Join-Path $ReleaseRoot 'console\node_modules')
    )

    foreach ($unexpectedPath in $unexpectedPaths) {
        if (Test-Path -LiteralPath $unexpectedPath) {
            throw "Release package verification failed. Unexpected path present: $unexpectedPath"
        }
    }

    $noisePatterns = @('.webman*.log', 'vite-8132*.log', 'vite.dev.log', 'vite.dev.err', 'dev-server.log', '*.tmp')
    foreach ($pattern in $noisePatterns) {
        $matches = Get-ChildItem -LiteralPath $ReleaseRoot -Recurse -Force -ErrorAction SilentlyContinue |
            Where-Object { -not $_.PSIsContainer -and $_.Name -like $pattern }
        if ($matches) {
            $firstMatch = $matches | Select-Object -First 1
            throw "Release package verification failed. Residual file detected: $($firstMatch.FullName)"
        }
    }
}

$backendRoot = Split-Path -Parent $PSScriptRoot
$modernizationRoot = Split-Path -Parent $backendRoot
$frontendRoot = Join-Path $modernizationRoot 'art-design-admin'
$frontendDistRoot = Join-Path $frontendRoot 'dist'
$databaseRoot = Join-Path $modernizationRoot 'database'
$releasesRoot = Join-Path $modernizationRoot 'releases'
$workspaceRoot = Split-Path -Parent $modernizationRoot

Assert-PathExists -Path $backendRoot -Label 'Backend root'
Assert-PathExists -Path $frontendRoot -Label 'Frontend root'
Assert-PathExists -Path $databaseRoot -Label 'Database root'
Ensure-Directory -Path $releasesRoot

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
if ([string]::IsNullOrWhiteSpace($Tag)) {
    $Tag = $timestamp
}

$safeTag = ($Tag -replace '[^A-Za-z0-9._-]', '-').Trim('-')
if ([string]::IsNullOrWhiteSpace($safeTag)) {
    throw 'Tag is empty after sanitization.'
}

$releaseName = if ($safeTag -like 'aipay-release-*') { $safeTag } else { "aipay-release-$safeTag" }
$releaseRoot = Join-Path $releasesRoot $releaseName
$archivePath = Join-Path $releasesRoot "$releaseName.zip"

if (-not $SkipWorkspaceCleanup) {
    Write-Step 'Cleaning known temporary residue from the workspace'
    Remove-MatchingChildren -Root $backendRoot -Patterns @('.webman*.log', 'tmp-*')
    Clear-DirectoryContents -Path (Join-Path $backendRoot 'runtime\cache')
    Clear-DirectoryContents -Path (Join-Path $backendRoot 'runtime\logs')
    Remove-MatchingChildren -Root $frontendRoot -Patterns @(
        'vite-8132*.log',
        'vite.dev.log',
        'vite.dev.err',
        'dev-server.log',
        'dist-deploy*.tar',
        'dist-deploy*.zip',
        'dist-sync*.tar.gz'
    )
}

if (-not $SkipFrontendBuild) {
    Write-Step 'Resetting frontend dist directory'
    Reset-Directory -Path $frontendDistRoot
    Write-Step 'Building frontend production bundle'
    Invoke-NativeCommand -FilePath 'pnpm' -ArgumentList @('build') -WorkingDirectory $frontendRoot
}

Assert-PathExists -Path $frontendDistRoot -Label 'Frontend dist root'
Assert-PathExists -Path (Join-Path $frontendDistRoot 'index.html') -Label 'Frontend dist entry'

Write-Step "Preparing release directory: $releaseRoot"
Reset-Directory -Path $releaseRoot

$backendReleaseRoot = Join-Path $releaseRoot 'backend'
$consoleReleaseRoot = Join-Path $releaseRoot 'console'
$databaseReleaseRoot = Join-Path $releaseRoot 'database\install'
$docsReleaseRoot = Join-Path $releaseRoot 'docs'

Ensure-Directory -Path $backendReleaseRoot
Ensure-Directory -Path $consoleReleaseRoot
Ensure-Directory -Path $databaseReleaseRoot
Ensure-Directory -Path $docsReleaseRoot

Write-Step 'Copying backend runtime package'
$backendItems = @(
    'app',
    'config',
    'database',
    'deploy',
    'plugins',
    'public',
    'support',
    'vendor',
    '.env.example',
    'composer.json',
    'composer.lock',
    'LICENSE',
    'start.php',
    'windows.bat',
    'windows.php'
)

foreach ($item in $backendItems) {
    $sourcePath = Join-Path $backendRoot $item
    Assert-PathExists -Path $sourcePath -Label "Backend item $item"
    Copy-Item -LiteralPath $sourcePath -Destination $backendReleaseRoot -Recurse -Force
}

Ensure-Directory -Path (Join-Path $backendReleaseRoot 'runtime')
Ensure-Directory -Path (Join-Path $backendReleaseRoot 'runtime\cache')
Ensure-Directory -Path (Join-Path $backendReleaseRoot 'runtime\logs')
Ensure-Directory -Path (Join-Path $backendReleaseRoot 'runtime\payment-plugins')
Ensure-Directory -Path (Join-Path $backendReleaseRoot 'upload-assets')
Ensure-Directory -Path (Join-Path $backendReleaseRoot 'upload-assets\images')
Ensure-Directory -Path (Join-Path $backendReleaseRoot 'upload-assets\news')
Ensure-Directory -Path (Join-Path $backendReleaseRoot 'upload-assets\payment-accounts')
Ensure-Directory -Path (Join-Path $backendReleaseRoot 'upload-assets\plugins')
Ensure-Directory -Path (Join-Path $backendReleaseRoot 'upload-assets\qrcode')
Clear-DirectoryContents -Path (Join-Path $backendReleaseRoot 'runtime\cache')
Clear-DirectoryContents -Path (Join-Path $backendReleaseRoot 'runtime\logs')
Clear-DirectoryContents -Path (Join-Path $backendReleaseRoot 'runtime\payment-plugins')
Clear-DirectoryContents -Path (Join-Path $backendReleaseRoot 'upload-assets\images')
Clear-DirectoryContents -Path (Join-Path $backendReleaseRoot 'upload-assets\news')
Clear-DirectoryContents -Path (Join-Path $backendReleaseRoot 'upload-assets\payment-accounts')
Clear-DirectoryContents -Path (Join-Path $backendReleaseRoot 'upload-assets\plugins')
Clear-DirectoryContents -Path (Join-Path $backendReleaseRoot 'upload-assets\qrcode')

$backendEnvPath = Join-Path $backendReleaseRoot '.env'
if (Test-Path -LiteralPath $backendEnvPath) {
    Remove-Item -LiteralPath $backendEnvPath -Force
}

Write-Step 'Copying frontend shell'
Copy-DirectoryContents -SourcePath $frontendDistRoot -DestinationPath $consoleReleaseRoot

Write-Step 'Copying database install SQL'
Copy-DirectoryContents -SourcePath (Join-Path $databaseRoot 'install') -DestinationPath $databaseReleaseRoot

Write-Step 'Copying deployment docs'
$docsToCopy = @(
    'release-package.md',
    'deployment-handbook.md',
    'deployment-verification.md',
    'aapanel-install.md',
    'database-installation.md',
    'rollback-playbook.md',
    'one-click-install.md'
)
foreach ($docName in $docsToCopy) {
    $sourceDoc = Join-Path (Join-Path $backendRoot 'docs') $docName
    Assert-PathExists -Path $sourceDoc -Label "Documentation file $docName"
    Copy-Item -LiteralPath $sourceDoc -Destination $docsReleaseRoot -Force
}

Write-Step 'Writing release readme and manifest'
$readmeLines = @(
    'AiPay Release Package',
    '',
    '1. Import database/install/core-install.sql into a clean database or use backend/deploy/linux/install-database.sh.',
    '2. Review docs/aapanel-install.md for aaPanel deployment or docs/deployment-handbook.md for general deployment.',
    '3. Run backend/deploy/linux/install-aapanel.sh or backend/deploy/linux/install-oneclick.sh from the backend directory.',
    '4. Keep the backend bound to 127.0.0.1 and expose only 80/443 through Nginx.'
)
Set-Content -LiteralPath (Join-Path $releaseRoot 'README-FIRST.txt') -Value $readmeLines -Encoding UTF8

$gitCommit = Get-GitValue -WorkingDirectory $workspaceRoot -Arguments @('rev-parse', 'HEAD')
$gitBranch = Get-GitValue -WorkingDirectory $workspaceRoot -Arguments @('rev-parse', '--abbrev-ref', 'HEAD')
$manifest = [ordered]@{
    release_name = $releaseName
    tag = $safeTag
    created_at = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
    backend_root = $backendRoot
    frontend_root = $frontendRoot
    database_root = $databaseRoot
    git_commit = $gitCommit
    git_branch = $gitBranch
    frontend_built = (-not $SkipFrontendBuild)
    workspace_cleanup = (-not $SkipWorkspaceCleanup)
    archive = [System.IO.Path]::GetFileName($archivePath)
}
$manifest | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $releaseRoot 'release-manifest.json') -Encoding UTF8

if (-not $SkipPackageVerification) {
    Write-Step 'Verifying release package structure'
    Verify-Package -ReleaseRoot $releaseRoot
}

if (Test-Path -LiteralPath $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

Write-Step "Compressing release archive: $archivePath"
$previousLocation = Get-Location
try {
    Set-Location -LiteralPath $releaseRoot
    & tar.exe -a -cf $archivePath .
    if ($LASTEXITCODE -ne 0) {
        throw "tar.exe failed with exit code ${LASTEXITCODE} while creating $archivePath"
    }
}
finally {
    Set-Location -LiteralPath $previousLocation
}

Write-Host ''
Write-Host '========================================'
Write-Host 'AiPay release package created'
Write-Host '========================================'
Write-Host "Release Root : $releaseRoot"
Write-Host "Release Zip  : $archivePath"
