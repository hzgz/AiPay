param(
    [string]$PhpBinary = 'php',
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [switch]$WithBaseSchema,
    [switch]$DryRun
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$arguments = @(
    (Join-Path $ProjectRoot 'deploy\shared\install-database.php')
)

if ($WithBaseSchema) {
    $arguments += '--with-base-schema'
}

if ($DryRun) {
    $arguments += '--dry-run'
}

Push-Location $ProjectRoot
try {
    & $PhpBinary @arguments
    exit $LASTEXITCODE
}
finally {
    Pop-Location
}
