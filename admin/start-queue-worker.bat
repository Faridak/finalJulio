@echo off
echo Starting Accounting Queue Worker...
cd /d "c:\xampp\htdocs\finalJulio"
php admin/accounting-queue-worker.php
pause