@echo off
REM Security Audit Batch Script
REM This script runs the security audit

echo Running Security Audit...
cd /d "C:\xampp\htdocs\finalJulio\security"
php security-audit.php
echo Security Audit completed.
pause