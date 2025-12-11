#!/bin/bash

# VentDepot Deployment Script for Linode Instance
# This script deploys the finalJulio project to the Linode production server

# Configuration
LINODE_IP="198.58.124.137"
PROJECT_PATH="/c/xampp/htdocs/finalJulio"
DEPLOY_USER="root"
REMOTE_PATH="/var/www/html"

echo "=== VentDepot Production Deployment ==="
echo "Deploying from: $PROJECT_PATH"
echo "Deploying to: $DEPLOY_USER@$LINODE_IP:$REMOTE_PATH"
echo ""

# Check if we're in the right directory
if [ ! -d ".git" ]; then
    echo "Error: Please run this script from the project root directory (should contain .git folder)"
    exit 1
fi

# Check Git status
echo "Checking Git status..."
if [[ -n $(git status --porcelain) ]]; then
    echo "Warning: You have uncommitted changes:"
    git status --short
    echo ""
    read -p "Do you want to continue deployment anyway? (y/N): " response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        echo "Deployment cancelled."
        exit 1
    fi
else
    echo "Working directory is clean."
fi

# Ensure we have the latest changes
echo "Pulling latest changes from repository..."
git pull origin master

# Create a temporary archive of the project
echo "Creating deployment package..."
TIMESTAMP=$(date +"%Y%m%d-%H%M%S")
ARCHIVE_NAME="ventdepot-deploy-$TIMESTAMP.tar.gz"

# Create archive excluding .git and deployment scripts
tar -czf "$ARCHIVE_NAME" --exclude='.git' --exclude='deploy.sh' --exclude='deploy.ps1' .

if [ -f "$ARCHIVE_NAME" ]; then
    echo "Deployment package created: $ARCHIVE_NAME"
else
    echo "Failed to create deployment package!"
    exit 1
fi

# Upload the archive to the Linode server
echo "Uploading to Linode server..."
scp "$ARCHIVE_NAME" "$DEPLOY_USER@$LINODE_IP:/tmp/"

if [ $? -eq 0 ]; then
    echo "Upload successful!"
else
    echo "Upload failed!"
    rm -f "$ARCHIVE_NAME"
    exit 1
fi

# Clean up local archive
rm -f "$ARCHIVE_NAME"

# Deploy on remote server
echo "Deploying on remote server..."
ssh $DEPLOY_USER@$LINODE_IP << EOF
cd /tmp
tar -xzf $ARCHIVE_NAME -C $REMOTE_PATH
chown -R www-data:www-data $REMOTE_PATH
chmod -R 755 $REMOTE_PATH
systemctl reload apache2
echo "Deployment completed at \$(date)"
EOF

if [ $? -eq 0 ]; then
    echo "Deployment completed successfully!"
    echo "Your application is now live at: http://$LINODE_IP"
else
    echo "Remote deployment commands failed!"
    exit 1
fi

echo "=== Deployment Process Completed ==="