# Remove database.php from Git Tracking

If `config/database.php` is already tracked in git, follow these steps to stop tracking it:

## Steps:

1. **Remove from git tracking (keeps local file):**
   ```bash
   git rm --cached config/database.php
   ```

2. **Commit the change:**
   ```bash
   git commit -m "Stop tracking config/database.php - use database.php.example as template"
   ```

3. **Push to remote:**
   ```bash
   git push
   ```

4. **On the server, ensure database.php exists:**
   - If it doesn't exist, copy from the example:
     ```bash
     cp config/database.php.example config/database.php
     ```
   - Edit `config/database.php` with your server's database credentials

## After this:

- `config/database.php` will never be overwritten by `git pull`
- Each environment (local, server) can have its own database configuration
- The template file `config/database.php.example` is tracked for reference
