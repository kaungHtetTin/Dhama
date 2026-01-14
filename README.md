# Dhama Podcast App

A mini podcast/song application built with pure PHP, featuring an admin console and REST API for client-side applications.

## Features

- **Admin Console**: Full CRUD operations for artists, songs, and categories
- **REST API**: JSON API for client-side applications
- **Database**: MySQL database with proper relationships
- **Authentication**: Secure admin login system

## Installation

1. **Database Setup**

   - Import the database schema from `database/schema.sql` into your MySQL database
   - Update database credentials in `config/database.php` if needed

2. **Configuration**

   - Default admin credentials:
     - Username: `admin`
     - Password: `admin123`
   - Update `config/config.php` if you need to change the base URL

3. **Directory Structure**
   ```
   dhama/
   ├── admin/          # Admin console
   ├── api/            # REST API endpoints
   ├── config/         # Configuration files
   ├── database/       # Database schema
   └── uploads/        # Upload directory (auto-created)
   ```

## Usage

### Admin Console

Access the admin console at: `https://www.calamuseducation.com/dhama/admin/`

- **Dashboard**: View statistics and recent songs
- **Artists**: Manage artists (Create, Read, Update, Delete)
- **Songs**: Manage songs/podcasts (Create, Read, Update, Delete)
- **Categories**: Manage categories (optional feature)

### API Endpoints

Base URL: `https://www.calamuseducation.com/dhama/api/`

#### Get All Artists

```
GET /api/?path=artists
```

#### Get Single Artist

```
GET /api/?path=artists/{id}
```

#### Get All Songs

```
GET /api/?path=songs
GET /api/?path=songs?artist_id=1
GET /api/?path=songs?search=keyword
GET /api/?path=songs?limit=20&offset=0
```

#### Get Single Song

```
GET /api/?path=songs/{id}
```

_Note: This automatically increments the play count_

#### Get Featured Songs

```
GET /api/?path=featured?limit=10
```

#### Get Categories

```
GET /api/?path=categories
```

#### Get Statistics

```
GET /api/?path=stats
```

## Database Schema

### Main Tables

- **artists**: Artist information
- **songs**: Song/podcast information
- **categories**: Category information (optional)
- **admins**: Admin user accounts

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled (for clean URLs)

## Security Notes

- Change the default admin password after first login
- In production, set `display_errors` to 0 in `config/config.php`
- Use HTTPS in production
- Implement proper file upload validation if adding file upload functionality

## License

This is a proof of concept application.
