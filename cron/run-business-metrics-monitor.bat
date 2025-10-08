@echo off
REM Business Metrics Monitor Batch Script
REM This script runs the business metrics monitor cron job

echo Running Business Metrics Monitor...
cd /d "C:\xampp\htdocs\finalJulio\cron"
php business-metrics-monitor.php
echo Business Metrics Monitor completed.
pause