# Dhama Podcast API Documentation

## Overview

The Dhama Podcast API is a RESTful JSON API that provides access to artists, songs, categories, and statistics for the podcast application. All responses are returned in JSON format with proper CORS headers enabled.

**Version:** 1.0  
**Base URL:** `http://localhost/dhama/api/`

## Table of Contents

- [Authentication](#authentication)
- [Response Format](#response-format)
- [Error Handling](#error-handling)
- [Endpoints](#endpoints)
  - [Artists](#artists)
  - [Songs](#songs)
  - [Featured Songs](#featured-songs)
  - [Categories](#categories)
  - [Statistics](#statistics)

## Authentication

Currently, the API endpoints are publicly accessible. No authentication is required for read operations.

## Response Format

All API responses follow a consistent JSON format:

### Success Response
```json
{
  "data": "...",
  "count": 10,
  "total": 50
}
```

### Error Response
```json
{
  "error": "Error message here"
}
```

## Error Handling

The API uses standard HTTP status codes:

- `200 OK` - Request successful
- `400 Bad Request` - Invalid request parameters
- `404 Not Found` - Resource not found
- `500 Internal Server Error` - Server error

## Endpoints

### Artists

#### Get All Artists

Retrieve a list of all artists with their song counts.

**Endpoint:** `GET /api/artists.php` or `GET /api/?path=artists`

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `limit` | integer | No | 100 | Maximum number of artists to return |
| `offset` | integer | No | 0 | Number of artists to skip |

**Response:**
```json
{
  "artists": [
    {
      "id": 1,
      "name": "Artist Name",
      "bio": "Artist biography",
      "image_url": "/uploads/images/artists/image.jpg",
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-01 12:00:00",
      "songs_count": 5
    }
  ],
  "count": 1,
  "total": 1
}
```

**Example Request:**
```
GET /api/artists.php?limit=20&offset=0
```

---

#### Get Single Artist

Retrieve detailed information about a specific artist.

**Endpoint:** `GET /api/artists.php?id={id}` or `GET /api/?path=artists/{id}`

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Artist ID |

**Response:**
```json
{
  "id": 1,
  "name": "Artist Name",
  "bio": "Artist biography",
  "image_url": "/uploads/images/artists/image.jpg",
  "created_at": "2024-01-01 12:00:00",
  "updated_at": "2024-01-01 12:00:00",
  "songs_count": 5
}
```

**Example Request:**
```
GET /api/artists.php?id=1
```

**Error Response (404):**
```json
{
  "error": "Artist not found"
}
```

---

### Songs

#### Get All Songs

Retrieve a list of all songs with optional filtering and pagination.

**Endpoint:** `GET /api/songs.php` or `GET /api/?path=songs`

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `artist_id` | integer | No | - | Filter songs by artist ID |
| `search` | string | No | - | Search in title, description, and artist name |
| `limit` | integer | No | 50 | Maximum number of songs to return |
| `offset` | integer | No | 0 | Number of songs to skip |

**Response:**
```json
{
  "songs": [
    {
      "id": 1,
      "title": "Song Title",
      "artist_id": 1,
      "artist_name": "Artist Name",
      "artist_image": "/uploads/images/artists/image.jpg",
      "description": "Song description",
      "audio_url": "/uploads/audio/songs/song.mp3",
      "cover_image_url": "/uploads/images/songs/cover.jpg",
      "duration": 180,
      "play_count": 42,
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-01 12:00:00"
    }
  ],
  "count": 1,
  "total": 1,
  "limit": 50,
  "offset": 0
}
```

**Example Requests:**
```
GET /api/songs.php
GET /api/songs.php?artist_id=1
GET /api/songs.php?search=keyword
GET /api/songs.php?limit=20&offset=0
GET /api/songs.php?artist_id=1&search=keyword&limit=10
```

---

#### Get Single Song

Retrieve detailed information about a specific song. **Note:** This endpoint automatically increments the play count when accessed.

**Endpoint:** `GET /api/songs.php?id={id}` or `GET /api/?path=songs/{id}`

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Song ID |

**Response:**
```json
{
  "id": 1,
  "title": "Song Title",
  "artist_id": 1,
  "artist_name": "Artist Name",
  "artist_image": "/uploads/images/artists/image.jpg",
  "description": "Song description",
  "audio_url": "/uploads/audio/songs/song.mp3",
  "cover_image_url": "/uploads/images/songs/cover.jpg",
  "duration": 180,
  "play_count": 43,
  "created_at": "2024-01-01 12:00:00",
  "updated_at": "2024-01-01 12:00:00"
}
```

**Example Request:**
```
GET /api/songs.php?id=1
```

**Error Response (404):**
```json
{
  "error": "Song not found"
}
```

**Note:** The `play_count` in the response reflects the incremented value after the request.

---

### Featured Songs

#### Get Featured/Popular Songs

Retrieve the most popular songs based on play count, sorted by play count (descending) and creation date.

**Endpoint:** `GET /api/featured.php` or `GET /api/?path=featured`

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `limit` | integer | No | 10 | Maximum number of songs to return |

**Response:**
```json
{
  "songs": [
    {
      "id": 1,
      "title": "Popular Song",
      "artist_id": 1,
      "artist_name": "Artist Name",
      "artist_image": "/uploads/images/artists/image.jpg",
      "description": "Song description",
      "audio_url": "/uploads/audio/songs/song.mp3",
      "cover_image_url": "/uploads/images/songs/cover.jpg",
      "duration": 180,
      "play_count": 1000,
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-01 12:00:00"
    }
  ],
  "count": 1
}
```

**Example Request:**
```
GET /api/featured.php?limit=20
```

---

### Categories

#### Get All Categories

Retrieve a list of all categories.

**Endpoint:** `GET /api/categories.php` or `GET /api/?path=categories`

**Response:**
```json
{
  "categories": [
    {
      "id": 1,
      "name": "Category Name",
      "description": "Category description",
      "created_at": "2024-01-01 12:00:00"
    }
  ],
  "count": 1
}
```

**Example Request:**
```
GET /api/categories.php
```

---

### Statistics

#### Get Statistics

Retrieve overall statistics about the podcast platform.

**Endpoint:** `GET /api/stats.php` or `GET /api/?path=stats`

**Response:**
```json
{
  "total_artists": 10,
  "total_songs": 50,
  "total_plays": 5000
}
```

**Example Request:**
```
GET /api/stats.php
```

---

## API Root

#### Get API Information

Get information about the API and available endpoints.

**Endpoint:** `GET /api/` or `GET /api/index.php`

**Response:**
```json
{
  "message": "Dhama Podcast API",
  "version": "1.0",
  "endpoints": {
    "GET /api/?path=artists": "Get all artists",
    "GET /api/?path=artists/{id}": "Get single artist",
    "GET /api/?path=songs": "Get all songs (supports ?artist_id=, ?search=, ?limit=, ?offset=)",
    "GET /api/?path=songs/{id}": "Get single song (increments play count)",
    "GET /api/?path=featured": "Get featured/popular songs",
    "GET /api/?path=categories": "Get all categories",
    "GET /api/?path=stats": "Get statistics"
  }
}
```

---

## Data Models

### Artist Object
```json
{
  "id": 1,
  "name": "string",
  "bio": "string",
  "image_url": "string",
  "created_at": "datetime",
  "updated_at": "datetime",
  "songs_count": 0
}
```

### Song Object
```json
{
  "id": 1,
  "title": "string",
  "artist_id": 1,
  "artist_name": "string",
  "artist_image": "string",
  "description": "string",
  "audio_url": "string",
  "cover_image_url": "string",
  "duration": 0,
  "play_count": 0,
  "created_at": "datetime",
  "updated_at": "datetime"
}
```

### Category Object
```json
{
  "id": 1,
  "name": "string",
  "description": "string",
  "created_at": "datetime"
}
```

---

## CORS Support

The API includes CORS headers to allow cross-origin requests:

- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type`

Preflight OPTIONS requests are automatically handled.

---

## Usage Examples

### JavaScript (Fetch API)

```javascript
// Get all artists
fetch('http://localhost/dhama/api/artists.php')
  .then(response => response.json())
  .then(data => console.log(data));

// Get songs by artist
fetch('http://localhost/dhama/api/songs.php?artist_id=1')
  .then(response => response.json())
  .then(data => console.log(data));

// Search songs
fetch('http://localhost/dhama/api/songs.php?search=keyword')
  .then(response => response.json())
  .then(data => console.log(data));

// Get featured songs
fetch('http://localhost/dhama/api/featured.php?limit=10')
  .then(response => response.json())
  .then(data => console.log(data));
```

### cURL

```bash
# Get all artists
curl http://localhost/dhama/api/artists.php

# Get single artist
curl http://localhost/dhama/api/artists.php?id=1

# Get all songs
curl http://localhost/dhama/api/songs.php

# Get songs with filters
curl "http://localhost/dhama/api/songs.php?artist_id=1&search=keyword&limit=10"

# Get featured songs
curl http://localhost/dhama/api/featured.php?limit=20

# Get statistics
curl http://localhost/dhama/api/stats.php
```

---

## Notes

1. **Play Count Increment**: Accessing a single song via `GET /api/songs.php?id={id}` automatically increments the play count. This happens server-side and cannot be disabled.

2. **Pagination**: When using pagination with `limit` and `offset`, the response includes:
   - `count`: Number of items in the current response
   - `total`: Total number of items available
   - `limit`: The limit used
   - `offset`: The offset used

3. **Search**: The search parameter searches across:
   - Song title
   - Song description
   - Artist name

4. **URL Encoding**: When using the router endpoint (`/api/?path=...`), ensure proper URL encoding for special characters.

5. **File URLs**: All file URLs (images, audio) are relative paths. Prepend your base URL when constructing full URLs for client applications.

---

## Support

For issues or questions, please refer to:
- `README.md` - General project documentation
- `README_SETUP.md` - Setup instructions
- `TROUBLESHOOTING.md` - Troubleshooting guide
