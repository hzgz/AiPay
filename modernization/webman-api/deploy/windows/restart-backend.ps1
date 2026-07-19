param(
    [string]$PhpBinary = 'php',
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Push-Location $ProjectRoot
try {
    & (Join-Path $PSScriptRoot 'stop-backend.ps1') -PhpBinary $PhpBinary -ProjectRoot $ProjectRoot
    & (Join-Path $PSScriptRoot 'start-backend.ps1') -PhpBinary $PhpBinary -ProjectRoot $ProjectRoot
}
finally {
    Pop-Location
}
