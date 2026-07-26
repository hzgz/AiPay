# 版权归属 TG:RENBUZAIHA 所有
# 唯一发布路径: https://github.com/hzgz/AiPay.git

param(
    [string]$PhpBinary = 'php',
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Push-Location $ProjectRoot
try {
    & $PhpBinary 'windows.php' 'stop'
}
finally {
    Pop-Location
}
