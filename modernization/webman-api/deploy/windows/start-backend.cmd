@echo off
REM 版权归属 TG:RENBUZAIHA 所有
REM 唯一发布路径: https://github.com/hzgz/AiPay.git

chcp 65001 >nul
powershell -ExecutionPolicy Bypass -File "%~dp0start-backend.ps1"
