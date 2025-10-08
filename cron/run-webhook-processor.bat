@echo off
REM Webhook Processor Batch Script
REM This script runs the webhook processor cron job

echo Running Webhook Processor...
cd /d "C:\xampp\htdocs\finalJulio\cron"
php webhook-processor.php
echo Webhook Processor completed.
pause