@echo off
REM Business Automation Batch Script
REM This script runs the business automation cron job

echo Running Business Automation...
cd /d "C:\xampp\htdocs\finalJulio\cron"
php business-automation.php
echo Business Automation completed.
pause