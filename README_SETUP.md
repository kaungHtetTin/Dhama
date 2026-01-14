# Setup Instructions

## Quick Setup

1. **Import Database**
   - Open phpMyAdmin or MySQL command line
   - Import `database/schema.sql` to create the database and tables

2. **Fix Admin Password (IMPORTANT)**
   - The password hash in the SQL file might not work
   - Run this in your browser: `http://localhost/dhama/setup_admin.php`
   - This will create/update the admin account with the correct password hash
   - Or manually run this SQL:
     ```sql
     UPDATE admins SET password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' WHERE username = 'admin';
     ```

3. **Login**
   - Go to: `http://localhost/dhama/admin/login.php`
   - Username: `admin`
   - Password: `admin123`

## Troubleshooting

### Login Not Working
- Make sure the database is imported
- Run `setup_admin.php` to fix the password hash
- Check database connection in `config/database.php`

### Database Connection Error
- Update `config/database.php` with your MySQL credentials
- Make sure MySQL is running in XAMPP

### Windows Desktop Not Showing
- Make sure JavaScript is enabled
- Check browser console for errors
- Verify `admin/assets/js/windows.js` is loading
