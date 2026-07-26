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
    $statusOutput = & $PhpBinary 'windows.php' 'status' 2>$null
    if ($LASTEXITCODE -eq 0) {
        $statusOutput | Out-Host
        return
    }

    $process = Start-Process -FilePath $PhpBinary -ArgumentList 'windows.php', 'start' -WorkingDirectory $ProjectRoot -WindowStyle Hidden -PassThru
    Start-Sleep -Seconds 2
    & $PhpBinary 'windows.php' 'status'
}
finally {
    Pop-Location
}
