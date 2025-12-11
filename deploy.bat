@echo off
REM VentDepot Deployment Script for Linode Instance (Windows Batch Version)
REM This script deploys the finalJulio project to the Linode production server

set LINODE_IP=198.58.124.137
set DEPLOY_USER=root
set REMOTE_PATH=/var/www/html

echo === VentDepot Production Deployment ===
echo Deploying to: %DEPLOY_USER%@%LINODE_IP%:%REMOTE_PATH%
echo.

REM Create a temporary directory for deployment files
echo Creating deployment package...
if exist deploy_temp rd /s /q deploy_temp
mkdir deploy_temp

REM Copy all files except .git directory and deployment scripts
robocopy . deploy_temp /XD .git /XF deploy.bat deploy.ps1 deploy.sh .gitignore /E /NFL /NDL

if %ERRORLEVEL% LEQ 3 (
    echo Deployment package created successfully!
) else (
    echo Failed to create deployment package!
    goto cleanup
)

REM Create ZIP archive
echo Creating ZIP archive...
if exist ventdepot-deploy.zip del ventdepot-deploy.zip
powershell -Command "Compress-Archive -Path deploy_temp\* -DestinationPath ventdepot-deploy.zip -Force"

if exist ventdepot-deploy.zip (
    echo ZIP archive created successfully!
) else (
    echo Failed to create ZIP archive!
    goto cleanup
)

REM Instructions for manual upload
echo.
echo === MANUAL DEPLOYMENT REQUIRED ===
echo The automatic deployment couldn't proceed because PuTTY tools are not installed.
echo.
echo Please follow these steps to manually deploy:
echo 1. Install PuTTY tools (pscp and plink) from https://www.chiark.greenend.org.uk/~sgtatham/putty/latest.html
echo 2. Add PuTTY installation directory to your PATH environment variable
echo 3. Run this script again
echo.
echo Alternatively, you can manually upload and deploy:
echo 1. Upload ventdepot-deploy.zip to your Linode server:
echo    scp ventdepot-deploy.zip root@%LINODE_IP%:/tmp/
echo 2. SSH into your server:
echo    ssh root@%LINODE_IP%
echo 3. Run these commands on the server:
echo    cd /tmp
echo    unzip ventdepot-deploy.zip
echo    cp -r deploy_temp/* %REMOTE_PATH%
echo    chown -R www-data:www-data %REMOTE_PATH%
echo    chmod -R 755 %REMOTE_PATH%
echo    systemctl reload apache2
echo    echo 'Deployment completed'
echo.
echo Your application will be available at: http://%LINODE_IP%
echo ================================

:cleanup
REM Clean up temporary files
if exist deploy_temp rd /s /q deploy_temp

echo === Deployment Process Completed ===
pause