# URGENT: Update projects.php on Live Server

## Issue
The live server's projects.php file has an old version that causes the projects page to show no data.

## Solution
Upload the updated `pages/projects.php` file from your local development to the live server.

## Steps to Fix

### Option 1: Via FTP/File Manager (Recommended)
1. Connect to your live server via FTP or cPanel File Manager
2. Navigate to: `/home/u218342218/domains/login.buildonqatar.com/public_html/pages/`
3. **Backup the current file first:**
   - Rename `projects.php` to `projects.php.backup`
4. Upload the new `projects.php` from your local:
   - Local path: `c:\Users\srahv\OneDrive\Desktop\buildon\pages\projects.php`
   - Upload to: `/home/u218342218/domains/login.buildonqatar.com/public_html/pages/projects.php`
5. Refresh the projects page on live server

### Option 2: Via SSH/Terminal
```bash
# Navigate to pages directory
cd /home/u218342218/domains/login.buildonqatar.com/public_html/pages/

# Backup current file
cp projects.php projects.php.backup

# Then upload the new file via SFTP or paste the content
```

## What Was Fixed
The updated projects.php file includes:
- ✅ Cross-database compatibility (MySQL and SQLite)
- ✅ Proper labour cost calculation using TIME_TO_SEC (MySQL) or strftime (SQLite)
- ✅ Fixed query that was causing NULL values for total_income, total_expenses, and profit
- ✅ Error handling to catch SQL errors

## Verification
After uploading, check:
1. Projects page shows the project data
2. Total Income displays correctly
3. Total Expenses displays correctly
4. **Total Labour Cost** displays correctly (not 0 or negative)
5. Profit calculation is correct

## If Still Not Working
1. Check PHP error logs on live server
2. Verify database connection is working
3. Check if the live server is using MySQL or SQLite
4. Contact me with the error message from the live server logs
