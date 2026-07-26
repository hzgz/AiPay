# 版权归属 TG:RENBUZAIHA 所有
# 唯一发布路径: https://github.com/hzgz/AiPay.git

param(
    [string]$PhpBinary = 'php',
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [string]$BackendUrl = '',
    [string]$ConsoleUrl = '',
    [string]$MerchantUrl = '',
    [string]$PublicUrl = '',
    [int]$Timeout = 8,
    [switch]$SkipHttp
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$arguments = @(
    (Join-Path $ProjectRoot 'deploy\shared\verify-deployment.php'),
    "--timeout=$Timeout"
)

if ($BackendUrl -ne '') {
    $arguments += "--backend-url=$BackendUrl"
}

if ($ConsoleUrl -ne '') {
    $arguments += "--console-url=$ConsoleUrl"
}

if ($MerchantUrl -ne '') {
    $arguments += "--merchant-url=$MerchantUrl"
}

if ($PublicUrl -ne '') {
    $arguments += "--public-url=$PublicUrl"
}

if ($SkipHttp) {
    $arguments += '--skip-http'
}

Push-Location $ProjectRoot
try {
    & $PhpBinary @arguments
    exit $LASTEXITCODE
}
finally {
    Pop-Location
}
