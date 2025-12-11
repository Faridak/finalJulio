# VentDepot Deployment Script for Linode Instance
# This script deploys the finalJulio project to the Linode production server

# Configuration
$LINODE_IP = "198.58.124.137"
$PROJECT_PATH = "c:\xampp\htdocs\finalJulio"
$DEPLOY_USER = "root"
$REMOTE_PATH = "/var/www/html"

# Colors for output
$GREEN = [ConsoleColor]::Green
$YELLOW = [ConsoleColor]::Yellow
$RED = [ConsoleColor]::Red

Write-Host "=== VentDepot Production Deployment ===" -ForegroundColor $GREEN
Write-Host "Deploying from: $PROJECT_PATH" -ForegroundColor $YELLOW
Write-Host "Deploying to: $DEPLOY_USER@$LINODE_IP`:$REMOTE_PATH" -ForegroundColor $YELLOW
Write-Host ""

# Check if we're in the right directory
if ((Get-Location).Path -ne $PROJECT_PATH) {
    Write-Host "Please run this script from the project root directory: $PROJECT_PATH" -ForegroundColor $RED
    exit 1
}

# Check Git status
Write-Host "Checking Git status..." -ForegroundColor $YELLOW
$gitStatus = git status --porcelain
if ($gitStatus) {
    Write-Host "Warning: You have uncommitted changes:" -ForegroundColor $YELLOW
    git status --short
    Write-Host ""
    $response = Read-Host "Do you want to continue deployment anyway? (y/N)"
    if ($response -ne "y" -and $response -ne "Y") {
        Write-Host "Deployment cancelled." -ForegroundColor $RED
        exit 1
    }
} else {
    Write-Host "Working directory is clean." -ForegroundColor $GREEN
}

# Ensure we have the latest changes
Write-Host "Pulling latest changes from repository..." -ForegroundColor $YELLOW
git pull origin master

# Create a temporary archive of the project
Write-Host "Creating deployment package..." -ForegroundColor $YELLOW
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$archiveName = "ventdepot-deploy-$timestamp.tar.gz"
tar -czf $archiveName --exclude='.git' --exclude='deploy.ps1' .

if (Test-Path $archiveName) {
    Write-Host "Deployment package created: $archiveName" -ForegroundColor $GREEN
} else {
    Write-Host "Failed to create deployment package!" -ForegroundColor $RED
    exit 1
}

# Upload the archive to the Linode server
Write-Host "Uploading to Linode server..." -ForegroundColor $YELLOW
$destination = "${DEPLOY_USER}@${LINODE_IP}:/tmp/"
scp $archiveName $destination

if ($LASTEXITCODE -eq 0) {
    Write-Host "Upload successful!" -ForegroundColor $GREEN
} else {
    Write-Host "Upload failed!" -ForegroundColor $RED
    Remove-Item $archiveName -ErrorAction SilentlyContinue
    exit 1
}

# Clean up local archive
Remove-Item $archiveName -ErrorAction SilentlyContinue

# Deploy on remote server
Write-Host "Deploying on remote server..." -ForegroundColor $YELLOW
$remoteCommands = @"
cd /tmp
tar -xzf $archiveName -C $REMOTE_PATH
chown -R www-data:www-data $REMOTE_PATH
chmod -R 755 $REMOTE_PATH
systemctl reload apache2
echo 'Deployment completed at $(date)'
"@

# Execute remote commands
ssh "${DEPLOY_USER}@${LINODE_IP}" $remoteCommands

if ($LASTEXITCODE -eq 0) {
    Write-Host "Deployment completed successfully!" -ForegroundColor $GREEN
    Write-Host "Your application is now live at: http://$LINODE_IP" -ForegroundColor $GREEN
} else {
    Write-Host "Remote deployment commands failed!" -ForegroundColor $RED
    exit 1
}

Write-Host "=== Deployment Process Completed ===" -ForegroundColor $GREEN