param(
    [string]$Tag = (Get-Date -Format 'yyyyMMdd-HHmmss'),
    [switch]$SkipFrontendBuild,
    [switch]$SkipWorkspaceCleanup,
    [switch]$SkipPackageVerification
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$modernizationRoot = Join-Path $repoRoot 'modernization'
$backendRoot = Join-Path $modernizationRoot 'webman-api'
$frontendRoot = Join-Path $modernizationRoot 'art-design-admin'
$releaseRoot = Join-Path $modernizationRoot 'releases'
$packageName = "aipay-release-$Tag"
$stageRoot = Join-Path $releaseRoot $packageName
$zipPath = "$stageRoot.zip"
$hashPath = "$zipPath.sha256"
$summaryPath = "$stageRoot.release.txt"
$rootCoreInstallPath = Join-Path $repoRoot 'modernization\database\install\core-install.sql'

function Remove-IfExists {
    param(
        [string]$LiteralPath,
        [switch]$BestEffort
    )

    if (Test-Path -LiteralPath $LiteralPath) {
        try {
            Remove-Item -LiteralPath $LiteralPath -Recurse -Force
        }
        catch {
            if (-not $BestEffort) {
                throw
            }

            Write-Warning "skip locked or protected path: $LiteralPath"
        }
    }
}

function Clear-DirectoryContents {
    param([string]$LiteralPath)

    if (-not (Test-Path -LiteralPath $LiteralPath)) {
        return
    }

    Get-ChildItem -LiteralPath $LiteralPath -Force | ForEach-Object {
        Remove-Item -LiteralPath $_.FullName -Recurse -Force
    }
}

function Invoke-RobocopyMirror {
    param(
        [string]$Source,
        [string]$Destination,
        [string[]]$ExcludeDirectories = @(),
        [string[]]$ExcludeFiles = @()
    )

    if (-not (Test-Path -LiteralPath $Destination)) {
        New-Item -ItemType Directory -Path $Destination | Out-Null
    }

    $arguments = @(
        $Source,
        $Destination,
        '/MIR',
        '/R:1',
        '/W:1',
        '/NFL',
        '/NDL',
        '/NJH',
        '/NJS',
        '/NP'
    )

    if ($ExcludeDirectories.Count -gt 0) {
        $arguments += '/XD'
        $arguments += $ExcludeDirectories
    }

    if ($ExcludeFiles.Count -gt 0) {
        $arguments += '/XF'
        $arguments += $ExcludeFiles
    }

    & robocopy @arguments | Out-Null
    $code = $LASTEXITCODE
    if ($code -ge 8) {
        throw "robocopy failed for $Source -> $Destination with exit code $code"
    }
}

function New-Directory {
    param([string]$LiteralPath)

    if (-not (Test-Path -LiteralPath $LiteralPath)) {
        New-Item -ItemType Directory -Path $LiteralPath | Out-Null
    }
}

function New-PortableZipArchive {
    param(
        [string]$SourceDirectory,
        [string]$DestinationZip
    )

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    if (Test-Path -LiteralPath $DestinationZip) {
        Remove-Item -LiteralPath $DestinationZip -Force
    }

    $resolvedSource = (Resolve-Path -LiteralPath $SourceDirectory).Path
    $zip = [System.IO.Compression.ZipFile]::Open($DestinationZip, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        $sourcePrefixLength = $resolvedSource.Length + 1
        Get-ChildItem -LiteralPath $resolvedSource -Recurse -Force | ForEach-Object {
            if ($_.PSIsContainer) {
                return
            }

            $relativePath = $_.FullName.Substring($sourcePrefixLength).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $zip,
                $_.FullName,
                $relativePath,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
    finally {
        $zip.Dispose()
    }
}

function Remove-StaleReleaseArtifacts {
    param(
        [string]$ReleaseRoot,
        [string]$CurrentPackageName
    )

    if (-not (Test-Path -LiteralPath $ReleaseRoot)) {
        return
    }

    Get-ChildItem -LiteralPath $ReleaseRoot -Force -ErrorAction SilentlyContinue | ForEach-Object {
        $name = $_.Name
        $keep = @(
            $CurrentPackageName,
            "$CurrentPackageName.zip",
            "$CurrentPackageName.zip.sha256",
            "$CurrentPackageName.release.txt"
        ) -contains $name

        if ($keep) {
            return
        }

        Remove-IfExists -LiteralPath $_.FullName -BestEffort
    }
}

function Invoke-WorkspaceCleanup {
    Write-Host '[release] cleaning workspace residue'

    Get-ChildItem -LiteralPath $repoRoot -Force | Where-Object {
        $_.Name -like '.codex*'
    } | ForEach-Object {
        Remove-IfExists -LiteralPath $_.FullName -BestEffort
    }

    @(
        (Join-Path $repoRoot 'tmp-admin-dashboard.png'),
        (Join-Path $repoRoot 'tmp-admin-menus.json'),
        (Join-Path $repoRoot 'tmp_rows.php'),
        (Join-Path $repoRoot 'tmp_schema.php'),
        (Join-Path $repoRoot '0'),
        (Join-Path $repoRoot '1]'),
        (Join-Path $frontendRoot '.codex-logs'),
        (Join-Path $frontendRoot 'vite-8132.log'),
        (Join-Path $frontendRoot 'vite-8132.err.log'),
        (Join-Path $frontendRoot '.vite-8132-local.log'),
        (Join-Path $frontendRoot '.vite-8132.stderr.log'),
        (Join-Path $frontendRoot '.vite-8132.stdout.log'),
        (Join-Path $backendRoot '.webman.stderr.log'),
        (Join-Path $backendRoot '.webman.stdout.log'),
        (Join-Path $backendRoot '.webman-local.log'),
        (Join-Path $backendRoot '.webman-local.err.log'),
        (Join-Path $backendRoot '.webman-local.out.log'),
        (Join-Path $backendRoot '.webman.stderr.20260708181041.log'),
        (Join-Path $backendRoot '.webman.stdout.20260708181041.log'),
        (Join-Path $backendRoot 'runtime\tmp-config-audit.php'),
        (Join-Path $backendRoot 'runtime\legacy-api-compat-state.json'),
        (Join-Path $backendRoot 'tools\_latest_smoke_suite.log'),
        (Join-Path $backendRoot 'theme-assets\home\smokehome_23aa7136d582'),
        (Join-Path $backendRoot 'theme-assets\home\smokehome_88a2c34a54ae'),
        (Join-Path $backendRoot 'theme-assets\home\smokehome_b8e556adbfc9'),
        (Join-Path $backendRoot 'theme-assets\pay\smokepay_23aa7136d582'),
        (Join-Path $backendRoot 'theme-assets\pay\smokepay_88a2c34a54ae'),
        (Join-Path $backendRoot 'theme-assets\pay\smokepay_b8e556adbfc9'),
        (Join-Path $repoRoot 'public\web\home\smokehome_23aa7136d582'),
        (Join-Path $repoRoot 'public\web\home\smokehome_88a2c34a54ae'),
        (Join-Path $repoRoot 'public\web\home\smokehome_b8e556adbfc9'),
        (Join-Path $repoRoot 'public\pay\smokepay_23aa7136d582'),
        (Join-Path $repoRoot 'public\pay\smokepay_88a2c34a54ae'),
        (Join-Path $repoRoot 'public\pay\smokepay_b8e556adbfc9')
    ) | ForEach-Object {
        Remove-IfExists -LiteralPath $_ -BestEffort
    }

    @(
        (Join-Path $backendRoot 'runtime\cache'),
        (Join-Path $backendRoot 'runtime\logs'),
        (Join-Path $backendRoot 'runtime\launch-cutover'),
        (Join-Path $backendRoot 'runtime\merchant-impersonation'),
        (Join-Path $backendRoot 'runtime\payment-plugin-audit'),
        (Join-Path $backendRoot 'runtime\payment-plugin-snapshots'),
        (Join-Path $backendRoot 'runtime\software-signatures')
    ) | ForEach-Object {
        Remove-IfExists -LiteralPath $_ -BestEffort
    }

    Get-ChildItem -LiteralPath (Join-Path $backendRoot 'vendor\composer') -Filter 'tmp-*' -ErrorAction SilentlyContinue | ForEach-Object {
        Remove-IfExists -LiteralPath $_.FullName -BestEffort
    }
}

function Build-Frontend {
    if ($SkipFrontendBuild) {
        Write-Host '[release] skip frontend build'
        return
    }

    Write-Host '[release] building frontend dist'
    Push-Location $frontendRoot
    try {
        if (-not (Test-Path -LiteralPath (Join-Path $frontendRoot 'node_modules'))) {
            Write-Host '[release] frontend dependencies missing, running pnpm install'
            & pnpm install
            if ($LASTEXITCODE -ne 0) {
                throw 'pnpm install failed'
            }
        }

        & pnpm build
        if ($LASTEXITCODE -ne 0) {
            throw 'pnpm build failed'
        }
    }
    finally {
        Pop-Location
    }
}

function Prepare-Stage {
    Write-Host "[release] creating stage $stageRoot"

    Remove-IfExists -LiteralPath $stageRoot
    Remove-IfExists -LiteralPath $zipPath
    Remove-IfExists -LiteralPath $hashPath
    Remove-IfExists -LiteralPath $summaryPath
    New-Directory -LiteralPath $releaseRoot

    $stageBackend = Join-Path $stageRoot 'backend'
    $stageConsole = Join-Path $stageRoot 'console'
    $stageDocs = Join-Path $stageRoot 'docs'
    $stageDatabase = Join-Path $stageRoot 'database'
    $stageInstall = Join-Path $stageDatabase 'install'

    New-Directory -LiteralPath $stageRoot
    New-Directory -LiteralPath $stageBackend
    New-Directory -LiteralPath $stageConsole
    New-Directory -LiteralPath $stageDocs
    New-Directory -LiteralPath $stageInstall

    Invoke-RobocopyMirror -Source $backendRoot -Destination $stageBackend -ExcludeDirectories @(
        (Join-Path $backendRoot 'docs'),
        (Join-Path $backendRoot 'tools'),
        (Join-Path $backendRoot 'runtime\cache'),
        (Join-Path $backendRoot 'runtime\logs'),
        (Join-Path $backendRoot 'runtime\launch-cutover'),
        (Join-Path $backendRoot 'runtime\merchant-impersonation'),
        (Join-Path $backendRoot 'runtime\payment-plugin-audit'),
        (Join-Path $backendRoot 'runtime\payment-plugin-snapshots'),
        (Join-Path $backendRoot 'runtime\software-signatures'),
        (Join-Path $backendRoot 'runtime\payment-plugins')
    ) -ExcludeFiles @(
        '.webman.stderr.log',
        '.webman.stdout.log',
        '.webman-local.log',
        '.webman-local.err.log',
        '.webman-local.out.log',
        '.webman.stderr.*.log',
        '.webman.stdout.*.log',
        'tmp-config-audit.php',
        'legacy-api-compat-state.json',
        'payment_plugins.json'
    )

    # Strip legacy demo/theme residue that is not needed in the production package.
    Remove-IfExists -LiteralPath (Join-Path $stageBackend 'theme-assets\demo')
    Remove-IfExists -LiteralPath (Join-Path $stageBackend 'app\model\Test.php')
    Remove-IfExists -LiteralPath (Join-Path $stageBackend 'console.__backup')
    Get-ChildItem -LiteralPath $stageBackend -Directory -Recurse -Force -ErrorAction SilentlyContinue | Where-Object {
        $_.Name -in @('.git', '.github')
    } | ForEach-Object {
        Remove-IfExists -LiteralPath $_.FullName -BestEffort
    }

    Invoke-RobocopyMirror -Source (Join-Path $frontendRoot 'dist') -Destination $stageConsole
    Remove-IfExists -LiteralPath (Join-Path $stageConsole 'console.__backup')

    Copy-Item -LiteralPath (Join-Path $backendRoot '.env.example') -Destination (Join-Path $stageBackend '.env.example') -Force
    Copy-Item -LiteralPath (Join-Path $backendRoot '.env.example') -Destination (Join-Path $stageBackend '.env') -Force
    Copy-Item -LiteralPath (Join-Path $backendRoot 'docs\release-package.md') -Destination (Join-Path $stageDocs 'release-package.md') -Force
    Copy-Item -LiteralPath (Join-Path $backendRoot 'docs\database-installation.md') -Destination (Join-Path $stageDocs 'database-installation.md') -Force
    Copy-Item -LiteralPath (Join-Path $backendRoot 'docs\delivery-checklist.md') -Destination (Join-Path $stageDocs 'delivery-checklist.md') -Force
    Copy-Item -LiteralPath (Join-Path $backendRoot 'docs\deployment-verification.md') -Destination (Join-Path $stageDocs 'deployment-verification.md') -Force
    Copy-Item -LiteralPath (Join-Path $backendRoot 'docs\deployment-handbook.md') -Destination (Join-Path $stageDocs 'deployment-handbook.md') -Force
    Copy-Item -LiteralPath (Join-Path $backendRoot 'docs\one-click-install.md') -Destination (Join-Path $stageDocs 'one-click-install.md') -Force
    Copy-Item -LiteralPath (Join-Path $backendRoot 'docs\aapanel-install.md') -Destination (Join-Path $stageDocs 'aapanel-install.md') -Force
    Copy-Item -LiteralPath (Join-Path $backendRoot 'docs\production-profile.example.json') -Destination (Join-Path $stageDocs 'production-profile.example.json') -Force

    if (Test-Path -LiteralPath $rootCoreInstallPath) {
        Copy-Item -LiteralPath $rootCoreInstallPath -Destination (Join-Path $stageInstall 'core-install.sql') -Force
    }

    New-Directory -LiteralPath (Join-Path $stageBackend 'runtime')
    New-Directory -LiteralPath (Join-Path $stageBackend 'runtime\cache')
    New-Directory -LiteralPath (Join-Path $stageBackend 'runtime\logs')
    New-Directory -LiteralPath (Join-Path $stageBackend 'runtime\payment-plugins')

    New-Directory -LiteralPath (Join-Path $stageBackend 'upload-assets')
    Clear-DirectoryContents -LiteralPath (Join-Path $stageBackend 'upload-assets')
    @('images', 'news', 'payment-accounts', 'plugins', 'qrcode') | ForEach-Object {
        New-Directory -LiteralPath (Join-Path (Join-Path $stageBackend 'upload-assets') $_)
    }
    Set-Content -LiteralPath (Join-Path $stageBackend 'upload-assets\.workspace-ready') -Value (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') -Encoding utf8

    $manifest = [ordered]@{
        package = $packageName
        built_at = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
        backend = 'backend'
        console = 'console'
        database_core_install = 'database/install/core-install.sql'
        plugins = (Get-ChildItem -LiteralPath (Join-Path $backendRoot 'plugins\payments') -Directory | Select-Object -ExpandProperty Name)
    } | ConvertTo-Json -Depth 4
    Set-Content -LiteralPath (Join-Path $stageRoot 'release-manifest.json') -Value $manifest -Encoding utf8

    $quickStart = @(
        'AiPay release package quick start',
        '',
        'Recommended architecture:',
        '  Frontend shell: /, /#/merchant/login, /#/auth/login',
        '  Backend only:   127.0.0.1:8787 for API/payment/callback',
        '',
        '1. Read docs/deployment-handbook.md first.',
        '2. Edit backend/.env with your production database and unified frontend shell URL.',
        '3. Initialize the database:',
        '   Windows: powershell -ExecutionPolicy Bypass -File backend/deploy/windows/install-database.ps1 -WithBaseSchema',
        '   Linux:   bash backend/deploy/linux/install-database.sh --with-base-schema',
        '4. Small white installer:',
        '   Linux:   bash backend/deploy/linux/install-oneclick.sh',
        '   aaPanel: bash backend/deploy/linux/install-aapanel.sh',
        '5. Start backend:',
        '   Windows: powershell -ExecutionPolicy Bypass -File backend/deploy/windows/start-backend.ps1',
        '   Linux:   bash backend/deploy/linux/start-backend.sh',
        '6. Put console/ behind Nginx and reverse proxy /api, /submit.php, /mapi.php, /Pay/* to 127.0.0.1:8787.',
        '7. Run deployment verification:',
        '   Windows: powershell -ExecutionPolicy Bypass -File backend/deploy/windows/verify-deployment.ps1',
        '   Linux:   bash backend/deploy/linux/verify-deployment.sh --skip-http',
        '',
        'Package layout:',
        '  backend/',
        '  console/',
        '  database/install/core-install.sql',
        '  docs/'
    )
    Set-Content -LiteralPath (Join-Path $stageRoot 'README-FIRST.txt') -Value $quickStart -Encoding ascii

    New-PortableZipArchive -SourceDirectory $stageRoot -DestinationZip $zipPath

    $hash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    Set-Content -LiteralPath $hashPath -Value "$hash *$(Split-Path -Leaf $zipPath)" -Encoding ascii

    $zipItem = Get-Item -LiteralPath $zipPath
    $summary = @(
        "package=$packageName"
        "built_at=$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        "zip=$(Split-Path -Leaf $zipPath)"
        "zip_size_bytes=$($zipItem.Length)"
        "sha256=$hash"
        "stage_dir=$(Split-Path -Leaf $stageRoot)"
    )
    Set-Content -LiteralPath $summaryPath -Value $summary -Encoding ascii

    Write-Host "[release] package ready: $zipPath"
}

function Verify-StagePackage {
    if ($SkipPackageVerification) {
        Write-Host '[release] skip package verification'
        return
    }

    Write-Host '[release] verifying staged package'
    & powershell -ExecutionPolicy Bypass -File (Join-Path $repoRoot 'tools\verify-release-package.ps1') -ReleaseRoot $stageRoot
    if ($LASTEXITCODE -ne 0) {
        throw 'release package verification failed'
    }
}

if (-not $SkipWorkspaceCleanup) {
    Invoke-WorkspaceCleanup
}

New-Directory -LiteralPath $releaseRoot
Remove-StaleReleaseArtifacts -ReleaseRoot $releaseRoot -CurrentPackageName $packageName

Build-Frontend
Prepare-Stage
Verify-StagePackage
