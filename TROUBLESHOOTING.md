# Troubleshooting Guide

## Login Not Working

### Issue: Cannot login with admin/admin123

**Solution 1: Run Setup Script**
1. Open in browser: `http://localhost/dhama/setup_admin.php`
2. This will create/update the admin account with correct password hash
3. Try logging in again

**Solution 2: Manual SQL Fix**
Run this SQL in phpMyAdmin:
```sql
UPDATE admins SET password = '$2y$10$7e0YmHuHcFKJKldhQ0MHWu29Ev6Xtmqc/NXQ2CtNEKjiVwwPAynNe' WHERE username = 'admin';
```

**Solution 3: Create New Admin**
```sql
DELETE FROM admins WHERE username = 'admin';
INSERT INTO admins (username, password, email) VALUES 
('admin', '$2y$10$7e0YmHuHcFKJKldhQ0MHWu29Ev6Xtmqc/NXQ2CtNEKjiVwwPAynNe', 'admin@dhama.com');
```

## Database Connection Error

**Error:** "Connection failed" or "Database connection error"

**Solutions:**
1. Check if MySQL is running in XAMPP Control Panel
2. Update `config/database.php` with correct credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');      // Your MySQL username
   define('DB_PASS', '');          // Your MySQL password (empty for XAMPP default)
   define('DB_NAME', 'dhama_podcast');
   ```
3. Make sure database `dhama_podcast` exists
4. Import `database/schema.sql` if database is empty

## Windows Desktop Not Showing

**Issue:** Desktop interface not loading or windows not working

**Solutions:**
1. Check browser console (F12) for JavaScript errors
2. Make sure `admin/assets/js/windows.js` is loading
3. Clear browser cache and reload
4. Check if JavaScript is enabled in browser
5. Try a different browser (Chrome, Firefox, Edge)

## API Not Working

**Issue:** API endpoints returning errors

**Solutions:**
1. Check if `.htaccess` file exists in root directory
2. Make sure Apache mod_rewrite is enabled
3. Test API: `http://localhost/dhama/api/?path=artists`
4. Check browser console for CORS errors
5. Verify database connection

## Common Issues

### "Table doesn't exist" error
- Import `database/schema.sql` again
- Make sure you're using the correct database

### "Access denied" or permission errors
- Check file permissions (should be readable)
- Check database user permissions

### Blank page or 500 error
- Check PHP error logs in XAMPP
- Enable error display in `config/config.php` (already enabled)
- Check for syntax errors in PHP files

## Still Having Issues?

1. Check XAMPP error logs: `C:\xampp\apache\logs\error.log`
2. Check PHP error logs: `C:\xampp\php\logs\php_error_log`
3. Verify all files are in correct locations
4. Make sure PHP version is 7.4 or higher
