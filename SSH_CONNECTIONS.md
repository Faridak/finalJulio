# SSH Connection Information for Linode Deployment

## Primary Access Method
- **IP Address**: 198.58.124.137
- **Username**: root
- **Purpose**: Direct server access for deployment and administration
- **Command**: `ssh root@198.58.124.137`

## Alternative Access Method (LISH Console)
- **Service**: Linode LISH (Linode Shell)
- **Username**: ventdepot
- **Instance**: ProductUploadNew
- **Command**: `ssh -t ventdepot@lish-us-central.linode.com ProductUploadNew`

## Deployment Process
The deployment scripts (deploy.ps1, deploy.sh, deploy.bat) use the primary SSH access method to:
1. Package application files
2. Upload to the server via SCP
3. Extract files to `/var/www/html`
4. Set proper permissions
5. Restart the Apache web server

## SSH Key Requirements
Ensure your SSH keys are properly configured in `~/.ssh/authorized_keys` on the Linode server for passwordless authentication.

## Security Notes
- Always use SSH keys rather than passwords for authentication
- Keep private keys secure and never share them
- Regularly update and rotate SSH keys
- Monitor SSH access logs for unauthorized attempts