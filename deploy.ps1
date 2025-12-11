# VentDepot Comprehensive Deployment Script
# This script deploys the finalJulio project to both GitHub and Linode production server

# Configuration
$LINODE_IP = "198.58.124.137"
$PROJECT_PATH = "c:\xampp\htdocs\finalJulio"
$DEPLOY_USER = "root"
$REMOTE_PATH = "/var/www/html"
$GITHUB_REPO = "https://github.com/Faridak/finalJulio.git"

# Colors for output
$GREEN = [ConsoleColor]::Green
$YELLOW = [ConsoleColor]::Yellow
$RED = [ConsoleColor]::Red

Write-Host "=== VentDepot Comprehensive Deployment ===" -ForegroundColor $GREEN
Write-Host "Deploying to: $DEPLOY_USER@$LINODE_IP`:$REMOTE_PATH" -ForegroundColor $YELLOW
Write-Host "GitHub Repository: $GITHUB_REPO" -ForegroundColor $YELLOW
Write-Host ""

# Change to project directory
Set-Location -Path $PROJECT_PATH

# Step 1: Git Operations - Commit and Push to GitHub
Write-Host "=== Step 1: Git Operations ===" -ForegroundColor $YELLOW
Write-Host "Checking Git status..." -ForegroundColor $YELLOW
$gitStatus = git status --porcelain
if ($gitStatus) {
    Write-Host "Uncommitted changes detected. Adding and committing..." -ForegroundColor $YELLOW
    git add .
    git commit -m "Automated deployment commit - $(Get-Date)"
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Changes committed successfully." -ForegroundColor $GREEN
    } else {
        Write-Host "Failed to commit changes." -ForegroundColor $RED
    }
} else {
    Write-Host "No uncommitted changes found." -ForegroundColor $GREEN
}

Write-Host "Pushing to GitHub..." -ForegroundColor $YELLOW
git push
if ($LASTEXITCODE -eq 0) {
    Write-Host "Successfully pushed to GitHub!" -ForegroundColor $GREEN
} else {
    Write-Host "Failed to push to GitHub." -ForegroundColor $RED
}

Write-Host ""

# Step 2: Create deployment package
Write-Host "=== Step 2: Creating Deployment Package ===" -ForegroundColor $YELLOW
Write-Host "Cleaning up previous deployment files..." -ForegroundColor $YELLOW

# Remove previous deployment files if they exist
if (Test-Path "deploy_temp") {
    Remove-Item "deploy_temp" -Recurse -Force
}
if (Test-Path "ventdepot-deploy.zip") {
    Remove-Item "ventdepot-deploy.zip" -Force
}

# Create a temporary directory for deployment files
Write-Host "Creating deployment package..." -ForegroundColor $YELLOW
New-Item -ItemType Directory -Name "deploy_temp" -Force | Out-Null

# Copy all files except .git directory and deployment scripts
$excludeDirs = @(".git")
$excludeFiles = @("deploy.bat", "deploy.ps1", "deploy.sh", ".gitignore")

# Get all items in the current directory
$items = Get-ChildItem -Path "." -Exclude $excludeDirs

foreach ($item in $items) {
    if ($excludeFiles -notcontains $item.Name) {
        if ($item.PSIsContainer) {
            # Copy directory
            Copy-Item -Path $item.FullName -Destination "deploy_temp" -Recurse -Force
        } else {
            # Copy file
            Copy-Item -Path $item.FullName -Destination "deploy_temp" -Force
        }
    }
}

Write-Host "Deployment package created successfully!" -ForegroundColor $GREEN

# Create ZIP archive
Write-Host "Creating ZIP archive..." -ForegroundColor $YELLOW
Compress-Archive -Path "deploy_temp\*" -DestinationPath "ventdepot-deploy.zip" -Force

if (Test-Path "ventdepot-deploy.zip") {
    Write-Host "ZIP archive created successfully!" -ForegroundColor $GREEN
} else {
    Write-Host "Failed to create ZIP archive!" -ForegroundColor $RED
    exit 1
}

Write-Host ""

# Step 3: Deploy to Linode
Write-Host "=== Step 3: Deploying to Linode ===" -ForegroundColor $YELLOW

# Check if PuTTY tools are available
$pscpExists = Get-Command "pscp" -ErrorAction SilentlyContinue
$plinkExists = Get-Command "plink" -ErrorAction SilentlyContinue

if ($pscpExists -and $plinkExists) {
    Write-Host "PuTTY tools found. Proceeding with automated deployment..." -ForegroundColor $GREEN
    
    # Upload the archive to the Linode server
    Write-Host "Uploading to Linode server..." -ForegroundColor $YELLOW
    $destination = "${DEPLOY_USER}@${LINODE_IP}:/tmp/"
    pscp -scp "ventdepot-deploy.zip" $destination
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Upload successful!" -ForegroundColor $GREEN
        
        # Deploy on remote server
        Write-Host "Deploying on remote server..." -ForegroundColor $YELLOW
        $remoteCommands = @"
cd /tmp
unzip -o ventdepot-deploy.zip
cp -r deploy_temp/* $REMOTE_PATH
chown -R www-data:www-data $REMOTE_PATH
chmod -R 755 $REMOTE_PATH
systemctl reload apache2
echo 'Deployment completed at $(date)'
"@
        
        $sshCommand = "${DEPLOY_USER}@${LINODE_IP}"
        echo $remoteCommands | plink $sshCommand
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "Deployment completed successfully!" -ForegroundColor $GREEN
            Write-Host "Your application is now live at: http://$LINODE_IP" -ForegroundColor $GREEN
        } else {
            Write-Host "Remote deployment commands failed!" -ForegroundColor $RED
        }
    } else {
        Write-Host "Upload failed!" -ForegroundColor $RED
    }
} else {
    Write-Host "PuTTY tools not found. Please install PuTTY tools for automated deployment." -ForegroundColor $YELLOW
    Write-Host "Showing manual deployment instructions..." -ForegroundColor $YELLOW
    Show-ManualInstructions
}

# Cleanup function
function Cleanup {
    Write-Host "Cleaning up temporary files..." -ForegroundColor $YELLOW
    if (Test-Path "deploy_temp") {
        Remove-Item "deploy_temp" -Recurse -Force
    }
}

# Manual instructions function
function Show-ManualInstructions {
    Write-Host ""
    Write-Host "=== MANUAL DEPLOYMENT INSTRUCTIONS ===" -ForegroundColor $YELLOW
    Write-Host "Please follow these steps to manually deploy:" -ForegroundColor $YELLOW
    Write-Host "1. Install PuTTY tools from https://www.chiark.greenend.org.uk/~sgtatham/putty/latest.html" -ForegroundColor $YELLOW
    Write-Host "2. Add PuTTY installation directory to your PATH environment variable" -ForegroundColor $YELLOW
    Write-Host "3. Run this script again" -ForegroundColor $YELLOW
    Write-Host ""
    Write-Host "Alternatively, you can manually upload and deploy:" -ForegroundColor $YELLOW
    Write-Host "1. Upload ventdepot-deploy.zip to your Linode server:" -ForegroundColor $YELLOW
    Write-Host "   scp ventdepot-deploy.zip root@$LINODE_IP:/tmp/" -ForegroundColor $YELLOW
    Write-Host "2. SSH into your server:" -ForegroundColor $YELLOW
    Write-Host "   ssh root@$LINODE_IP" -ForegroundColor $YELLOW
    Write-Host "3. Run these commands on the server:" -ForegroundColor $YELLOW
    Write-Host "   cd /tmp" -ForegroundColor $YELLOW
    Write-Host "   unzip ventdepot-deploy.zip" -ForegroundColor $YELLOW
    Write-Host "   cp -r deploy_temp/* $REMOTE_PATH" -ForegroundColor $YELLOW
    Write-Host "   chown -R www-data:www-data $REMOTE_PATH" -ForegroundColor $YELLOW
    Write-Host "   chmod -R 755 $REMOTE_PATH" -ForegroundColor $YELLOW
    Write-Host "   systemctl reload apache2" -ForegroundColor $YELLOW
    Write-Host "   echo 'Deployment completed'" -ForegroundColor $YELLOW
    Write-Host ""
    Write-Host "Your application will be available at: http://$LINODE_IP" -ForegroundColor $GREEN
}

# Perform cleanup
Cleanup

Write-Host ""
Write-Host "=== Deployment Process Completed ===" -ForegroundColor $GREEN
Write-Host "Both GitHub and Linode deployment attempted." -ForegroundColor $GREEN