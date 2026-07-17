param(
    [Parameter(Mandatory = $true)]
    [string]$ReleaseRoot
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$resolvedRoot = (Resolve-Path $ReleaseRoot).Path
$failures = New-Object System.Collections.Generic.List[string]

function Assert-Exists {
    param([string]$RelativePath)

    $target = Join-Path $resolvedRoot $RelativePath
    if (-not (Test-Path -LiteralPath $target)) {
        $failures.Add("missing: $RelativePath")
    }
}

function Assert-NotExists {
    param([string]$RelativePath)

    $target = Join-Path $resolvedRoot $RelativePath
    if (Test-Path -LiteralPath $target) {
        $failures.Add("unexpected residue: $RelativePath")
    }
}

function Assert-NoRecursiveResidue {
    param(
        [string[]]$DirectoryNames = @(),
        [string[]]$FileNames = @(),
        [string[]]$FilePatterns = @()
    )

    Get-ChildItem -LiteralPath $resolvedRoot -Recurse -Force -ErrorAction SilentlyContinue | ForEach-Object {
        $relativePath = $_.FullName.Substring($resolvedRoot.Length).TrimStart('\')
        if ($relativePath -eq '') {
            return
        }

        if ($_.PSIsContainer) {
            if ($DirectoryNames -contains $_.Name) {
                $failures.Add("unexpected directory residue: $relativePath")
            }
            return
        }

        if ($FileNames -contains $_.Name) {
            $failures.Add("unexpected file residue: $relativePath")
            return
        }

        foreach ($pattern in $FilePatterns) {
            if ($_.Name -like $pattern) {
                $failures.Add("unexpected file residue: $relativePath")
                break
            }
        }
    }
}

Assert-Exists 'backend'
Assert-Exists 'backend\.env'
Assert-Exists 'backend\.env.example'
Assert-Exists 'backend\composer.json'
Assert-Exists 'backend\start.php'
Assert-Exists 'backend\deploy\windows\start-backend.ps1'
Assert-Exists 'backend\deploy\windows\stop-backend.ps1'
Assert-Exists 'backend\deploy\windows\install-database.ps1'
Assert-Exists 'backend\deploy\windows\verify-deployment.ps1'
Assert-Exists 'backend\deploy\linux\start-backend.sh'
Assert-Exists 'backend\deploy\linux\install-database.sh'
Assert-Exists 'backend\deploy\linux\install-oneclick.sh'
Assert-Exists 'backend\deploy\linux\install-aapanel.sh'
Assert-Exists 'backend\deploy\linux\install-production.sh'
Assert-Exists 'backend\deploy\linux\aipay-webman.service.example'
Assert-Exists 'backend\deploy\linux\verify-deployment.sh'
Assert-Exists 'backend\deploy\shared\install-database.php'
Assert-Exists 'backend\deploy\shared\install_support.php'
Assert-Exists 'backend\deploy\shared\bootstrap-installation.php'
Assert-Exists 'backend\deploy\shared\manage-admin.php'
Assert-Exists 'backend\deploy\shared\verify-deployment.php'
Assert-Exists 'backend\deploy\nginx\console.example.conf'
Assert-Exists 'backend\deploy\nginx\public.example.conf'
Assert-Exists 'backend\deploy\nginx\aapanel.site.example.conf'
Assert-Exists 'console\index.html'
Assert-Exists 'console\assets'
Assert-Exists 'database\install\base-schema.sql'
Assert-Exists 'database\install\admin-auth-seed.sql'
Assert-Exists 'docs\release-package.md'
Assert-Exists 'docs\database-installation.md'
Assert-Exists 'docs\delivery-checklist.md'
Assert-Exists 'docs\deployment-verification.md'
Assert-Exists 'docs\deployment-handbook.md'
Assert-Exists 'docs\one-click-install.md'
Assert-Exists 'docs\aapanel-install.md'
Assert-Exists 'docs\production-profile.example.json'
Assert-Exists 'release-manifest.json'
Assert-Exists 'README-FIRST.txt'

Assert-NotExists 'backend\tools'
Assert-NotExists 'backend\runtime\payment_plugins.json'
Assert-NotExists 'backend\runtime\payment-plugin-snapshots'
Assert-NotExists 'backend\runtime\software-signatures'
Assert-NotExists 'backend\runtime\payment-plugin-audit'
Assert-NotExists 'backend\runtime\launch-cutover'
Assert-NotExists 'backend\runtime\merchant-impersonation'
Assert-NotExists 'backend\.webman.stderr.log'
Assert-NotExists 'backend\.webman.stdout.log'
Assert-NotExists 'backend\theme-assets\demo'
Assert-NotExists 'backend\app\model\Test.php'

Assert-NoRecursiveResidue `
    -DirectoryNames @('node_modules', '.git', '.github', 'console.__backup', '__pycache__') `
    -FileNames @('Thumbs.db', '.DS_Store') `
    -FilePatterns @('*.log', '*.zip', '*.sha256', '*.release.txt', '.codex*', 'vite-*.log')

$manifestPath = Join-Path $resolvedRoot 'release-manifest.json'
if (Test-Path -LiteralPath $manifestPath) {
    try {
        $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
        if (-not $manifest.package) {
            $failures.Add('release-manifest.json missing package field')
        }
        if (-not $manifest.plugins -or $manifest.plugins.Count -lt 1) {
            $failures.Add('release-manifest.json missing plugin list')
        }
    }
    catch {
        $failures.Add('release-manifest.json is not valid JSON')
    }
}

if ($failures.Count -gt 0) {
    Write-Host 'release package verification failed:' -ForegroundColor Red
    $failures | ForEach-Object { Write-Host " - $_" -ForegroundColor Red }
    exit 1
}

Write-Host "release package verified: $resolvedRoot" -ForegroundColor Green
