@echo off
REM VentDepot Comprehensive Deployment Script
REM This script deploys the finalJulio project to both GitHub and Linode production server

set LINODE_IP=198.58.124.137
set DEPLOY_USER=root
set REMOTE_PATH=/var/www/html
set GITHUB_REPO=https://github.com/Faridak/finalJulio.git

echo === VentDepot Comprehensive Deployment ===
echo Deploying to: %DEPLOY_USER%@%LINODE_IP%:%REMOTE_PATH%
echo GitHub Repository: %GITHUB_REPO%
echo.

REM Step 1: Git Operations - Commit and Push to GitHub
echo === Step 1: Git Operations ===
echo Checking Git status...
cd /d "c:\xampp\htdocs\finalJulio"
git status --porcelain > nul
if %ERRORLEVEL% EQU 0 (
    echo No uncommitted changes found.
) else (
    echo Uncommitted changes detected. Adding and committing...
    git add .
    git commit -m "Automated deployment commit - %date% %time%"
    if %ERRORLEVEL% EQU 0 (
        echo Changes committed successfully.
    ) else (
        echo Failed to commit changes.
    )
)

echo Pushing to GitHub...
git push
if %ERRORLEVEL% EQU 0 (
    echo Successfully pushed to GitHub!
) else (
    echo Failed to push to GitHub.
)

echo.
REM Step 2: Create deployment package
echo === Step 2: Creating Deployment Package ===
echo Cleaning up previous deployment files...
if exist deploy_temp rd /s /q deploy_temp
if exist ventdepot-deploy.zip del ventdepot-deploy.zip

echo Creating deployment package...
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
powershell -Command "Compress-Archive -Path deploy_temp\* -DestinationPath ventdepot-deploy.zip -Force"

if exist ventdepot-deploy.zip (
    echo ZIP archive created successfully!
) else (
    echo Failed to create ZIP archive!
    goto cleanup
)

echo.
REM Step 3: Deploy to Linode
echo === Step 3: Deploying to Linode ===

REM Check if PuTTY tools are available
where pscp >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    echo PuTTY tools found. Proceeding with automated deployment...
    
    REM Upload the archive to the Linode server
    echo Uploading to Linode server...
    pscp -scp ventdepot-deploy.zip %DEPLOY_USER%@%LINODE_IP%:/tmp/
    
    if %ERRORLEVEL% EQU 0 (
        echo Upload successful!
        
        REM Deploy on remote server
        echo Deploying on remote server...
        plink %DEPLOY_USER%@%LINODE_IP% "cd /tmp && unzip -o ventdepot-deploy.zip && cp -r deploy_temp/* %REMOTE_PATH% && chown -R www-data:www-data %REMOTE_PATH% && chmod -R 755 %REMOTE_PATH% && systemctl reload apache2 && echo 'Deployment completed at $(date)'"
        
        if %ERRORLEVEL% EQU 0 (
            echo Deployment completed successfully!
            echo Your application is now live at: http://%LINODE_IP%
        ) else (
            echo Remote deployment commands failed!
        )
    ) else (
        echo Upload failed!
        goto manual_instructions
    )
) else (
    echo PuTTY tools not found. Providing manual deployment instructions...
    goto manual_instructions
)

goto cleanup

:manual_instructions
echo.
echo === MANUAL DEPLOYMENT INSTRUCTIONS ===
echo PuTTY tools (pscp and plink) are not installed.
echo Please follow these steps to manually deploy:
echo 1. Install PuTTY tools from https://www.chiark.greenend.org.uk/~sgtatham/putty/latest.html
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

:cleanup
REM Clean up temporary files
if exist deploy_temp rd /s /q deploy_temp

echo.
echo === Deployment Process Completed ===
echo Both GitHub and Linode deployment attempted.
pause