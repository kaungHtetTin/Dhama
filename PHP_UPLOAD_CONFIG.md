# PHP Upload Configuration for 1GB Files

To enable 1GB file uploads, you need to update your PHP configuration. The application code has been updated, but you also need to configure PHP itself.

## For XAMPP (Windows)

1. **Open `php.ini` file:**
   - Location: `C:\xampp\php\php.ini`
   - Or click "Config" → "PHP (php.ini)" in XAMPP Control Panel

2. **Find and update these settings:**
   ```ini
   upload_max_filesize = 1024M
   post_max_size = 1024M
   max_execution_time = 0
   max_input_time = 0
   memory_limit = -1
   ```

3. **Save the file and restart Apache** in XAMPP Control Panel

## Verify Settings

After restarting, you can verify the settings by:
- Creating a PHP file with: `<?php phpinfo(); ?>`
- Or check the error logs - the application will show current limits in error messages

## Important Notes

- `post_max_size` must be **equal to or greater than** `upload_max_filesize`
- If using `.htaccess` (already configured), it will work if PHP is running as Apache module
- If `.htaccess` doesn't work, you **must** edit `php.ini` directly
- After changing `php.ini`, **restart Apache** for changes to take effect

## Current Application Settings

The application has been configured with:
- ✅ Max file size: 1GB (1024MB)
- ✅ Unlimited execution time
- ✅ Unlimited memory
- ✅ Updated UI messages to show "max 1GB"

## Troubleshooting

If uploads still fail after configuration:

1. Check PHP error logs: `C:\xampp\php\logs\php_error_log`
2. Check Apache error logs: `C:\xampp\apache\logs\error.log`
3. Verify settings with `phpinfo()`
4. Check browser console for detailed error messages (now includes debug info)
