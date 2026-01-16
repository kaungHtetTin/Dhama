# Dhama Podcast API Documentation

## Overview

The Dhama Podcast API is a RESTful JSON API that provides access to artists, songs, categories, and statistics for the podcast application. All responses are returned in JSON format with proper CORS headers enabled.

**Version:** 1.0  
**Base URL:** `https://www.calamuseducation.com/dhama/api/`

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

**No authentication is required.** All API endpoints (including CRUD operations) are publicly accessible. This is by design for this API.

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
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request parameters
- `404 Not Found` - Resource not found
- `405 Method Not Allowed` - HTTP method not supported
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

#### Create Artist

Create a new artist.

**Endpoint:** `POST /api/artists.php`

**Request Body (JSON):**

```json
{
  "name": "Artist Name",
  "bio": "Artist biography (optional)",
  "image_url": "http://example.com/image.jpg (optional)",
  "image_base64": "data:image/jpeg;base64,... (optional, alternative to image_url)"
}
```

**Request Body (multipart/form-data):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Artist name |
| `bio` | string | No | Artist biography |
| `image` | file | No | Image file (JPG, PNG, GIF, WEBP, max 10MB) |
| `image_url` | string | No | Image URL (alternative to file upload) |

**Response (201 Created):**

```json
{
  "id": 1,
  "name": "Artist Name",
  "bio": "Artist biography",
  "image_url": "/uploads/images/artists/image.jpg",
  "created_at": "2024-01-01 12:00:00",
  "updated_at": "2024-01-01 12:00:00",
  "songs_count": 0
}
```

**Example Requests:**

**JSON:**

```bash
curl -X POST https://www.calamuseducation.com/dhama/api/artists.php \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Artist",
    "bio": "Artist biography",
    "image_url": "http://example.com/image.jpg"
  }'
```

**Multipart (with file upload):**

```bash
curl -X POST https://www.calamuseducation.com/dhama/api/artists.php \
  -F "name=New Artist" \
  -F "bio=Artist biography" \
  -F "image=@/path/to/image.jpg"
```

**Error Responses:**

```json
{
  "error": "Name is required"
}
```

---

#### Update Artist

Update an existing artist.

**Endpoint:** `PUT /api/artists.php?id={id}` or `PATCH /api/artists.php?id={id}`

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Artist ID |

**Request Body (JSON):**

```json
{
  "name": "Updated Artist Name",
  "bio": "Updated biography",
  "image_url": "http://example.com/new-image.jpg",
  "image_base64": "data:image/jpeg;base64,... (optional)"
}
```

**Request Body (multipart/form-data):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | No | Artist name |
| `bio` | string | No | Artist biography |
| `image` | file | No | New image file (replaces existing) |
| `image_url` | string | No | New image URL (replaces existing) |

**Response:**

```json
{
  "id": 1,
  "name": "Updated Artist Name",
  "bio": "Updated biography",
  "image_url": "/uploads/images/artists/new-image.jpg",
  "created_at": "2024-01-01 12:00:00",
  "updated_at": "2024-01-01 13:00:00",
  "songs_count": 5
}
```

**Example Request:**

```bash
curl -X PUT https://www.calamuseducation.com/dhama/api/artists.php?id=1 \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name",
    "bio": "Updated bio"
  }'
```

**Error Responses:**

```json
{
  "error": "Artist not found"
}
```

---

#### Delete Artist

Delete an artist and their associated image file.

**Endpoint:** `DELETE /api/artists.php?id={id}`

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Artist ID |

**Response:**

```json
{
  "message": "Artist deleted successfully",
  "id": 1
}
```

**Example Request:**

```bash
curl -X DELETE https://www.calamuseducation.com/dhama/api/artists.php?id=1
```

**Error Responses:**

```json
{
  "error": "Artist not found"
}
```

**Note:** Deleting an artist will also delete all associated songs due to foreign key constraints (CASCADE DELETE).

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

#### Create Song

Create a new song.

**Endpoint:** `POST /api/songs.php`

**Request Body (JSON):**

```json
{
  "title": "Song Title",
  "artist_id": 1,
  "description": "Song description (optional)",
  "audio_url": "http://example.com/audio.mp3",
  "cover_image_url": "http://example.com/cover.jpg (optional)",
  "cover_image_base64": "data:image/jpeg;base64,... (optional)",
  "duration": 180
}
```

**Request Body (multipart/form-data):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Song title |
| `artist_id` | integer | Yes | Artist ID |
| `description` | string | No | Song description |
| `audio` | file | Yes* | Audio file (MP3, WAV, OGG, M4A, max 1GB) |
| `audio_url` | string | Yes* | Audio URL (alternative to file upload) |
| `cover_image` | file | No | Cover image file (JPG, PNG, GIF, WEBP, max 10MB) |
| `cover_image_url` | string | No | Cover image URL |
| `duration` | integer | No | Duration in seconds |

\* Either `audio` file or `audio_url` is required.

**Response (201 Created):**

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
  "play_count": 0,
  "created_at": "2024-01-01 12:00:00",
  "updated_at": "2024-01-01 12:00:00"
}
```

**Example Requests:**

**JSON:**

```bash
curl -X POST https://www.calamuseducation.com/dhama/api/songs.php \
  -H "Content-Type: application/json" \
  -d '{
    "title": "New Song",
    "artist_id": 1,
    "description": "Song description",
    "audio_url": "http://example.com/audio.mp3",
    "duration": 180
  }'
```

**Multipart (with file uploads):**

```bash
curl -X POST https://www.calamuseducation.com/dhama/api/songs.php \
  -F "title=New Song" \
  -F "artist_id=1" \
  -F "description=Song description" \
  -F "audio=@/path/to/audio.mp3" \
  -F "cover_image=@/path/to/cover.jpg" \
  -F "duration=180"
```

**Error Responses:**

```json
{
  "error": "Title is required"
}
```

```json
{
  "error": "Valid artist_id is required"
}
```

```json
{
  "error": "Audio file or audio_url is required"
}
```

```json
{
  "error": "Artist not found"
}
```

---

#### Update Song

Update an existing song.

**Endpoint:** `PUT /api/songs.php?id={id}` or `PATCH /api/songs.php?id={id}`

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Song ID |

**Request Body (JSON):**

```json
{
  "title": "Updated Song Title",
  "artist_id": 1,
  "description": "Updated description",
  "audio_url": "http://example.com/new-audio.mp3",
  "cover_image_url": "http://example.com/new-cover.jpg",
  "cover_image_base64": "data:image/jpeg;base64,... (optional)",
  "duration": 200
}
```

**Request Body (multipart/form-data):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | No | Song title |
| `artist_id` | integer | No | Artist ID |
| `description` | string | No | Song description |
| `audio` | file | No | New audio file (replaces existing) |
| `audio_url` | string | No | New audio URL (replaces existing) |
| `cover_image` | file | No | New cover image file (replaces existing) |
| `cover_image_url` | string | No | New cover image URL (replaces existing) |
| `duration` | integer | No | Duration in seconds |

**Response:**

```json
{
  "id": 1,
  "title": "Updated Song Title",
  "artist_id": 1,
  "artist_name": "Artist Name",
  "artist_image": "/uploads/images/artists/image.jpg",
  "description": "Updated description",
  "audio_url": "/uploads/audio/songs/new-audio.mp3",
  "cover_image_url": "/uploads/images/songs/new-cover.jpg",
  "duration": 200,
  "play_count": 42,
  "created_at": "2024-01-01 12:00:00",
  "updated_at": "2024-01-01 13:00:00"
}
```

**Example Request:**

```bash
curl -X PUT https://www.calamuseducation.com/dhama/api/songs.php?id=1 \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Title",
    "description": "Updated description"
  }'
```

**Error Responses:**

```json
{
  "error": "Song not found"
}
```

```json
{
  "error": "Artist not found"
}
```

---

#### Delete Song

Delete a song and its associated audio and cover image files.

**Endpoint:** `DELETE /api/songs.php?id={id}`

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Song ID |

**Response:**

```json
{
  "message": "Song deleted successfully",
  "id": 1
}
```

**Example Request:**

```bash
curl -X DELETE https://www.calamuseducation.com/dhama/api/songs.php?id=1
```

**Error Responses:**

```json
{
  "error": "Song not found"
}
```

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
    "GET /api/artists.php": "Get all artists",
    "GET /api/artists.php?id={id}": "Get single artist",
    "POST /api/artists.php": "Create new artist",
    "PUT /api/artists.php?id={id}": "Update artist",
    "DELETE /api/artists.php?id={id}": "Delete artist",
    "GET /api/songs.php": "Get all songs (supports ?artist_id=, ?search=, ?limit=, ?offset=)",
    "GET /api/songs.php?id={id}": "Get single song (increments play count)",
    "POST /api/songs.php": "Create new song",
    "PUT /api/songs.php?id={id}": "Update song",
    "DELETE /api/songs.php?id={id}": "Delete song",
    "GET /api/featured.php": "Get featured/popular songs",
    "GET /api/categories.php": "Get all categories",
    "GET /api/stats.php": "Get statistics"
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
// GET - Get all artists
fetch("https://www.calamuseducation.com/dhama/api/artists.php")
  .then((response) => response.json())
  .then((data) => console.log(data));

// GET - Get single artist
fetch("https://www.calamuseducation.com/dhama/api/artists.php?id=1")
  .then((response) => response.json())
  .then((data) => console.log(data));

// POST - Create artist (JSON)
fetch("https://www.calamuseducation.com/dhama/api/artists.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    name: "New Artist",
    bio: "Artist biography",
    image_url: "http://example.com/image.jpg",
  }),
})
  .then((response) => response.json())
  .then((data) => console.log(data));

// POST - Create artist (multipart/form-data with file)
const formData = new FormData();
formData.append("name", "New Artist");
formData.append("bio", "Artist biography");
formData.append("image", fileInput.files[0]);

fetch("https://www.calamuseducation.com/dhama/api/artists.php", {
  method: "POST",
  body: formData,
})
  .then((response) => response.json())
  .then((data) => console.log(data));

// PUT - Update artist
fetch("https://www.calamuseducation.com/dhama/api/artists.php?id=1", {
  method: "PUT",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    name: "Updated Name",
    bio: "Updated bio",
  }),
})
  .then((response) => response.json())
  .then((data) => console.log(data));

// DELETE - Delete artist
fetch("https://www.calamuseducation.com/dhama/api/artists.php?id=1", {
  method: "DELETE",
})
  .then((response) => response.json())
  .then((data) => console.log(data));

// GET - Get songs by artist
fetch("https://www.calamuseducation.com/dhama/api/songs.php?artist_id=1")
  .then((response) => response.json())
  .then((data) => console.log(data));

// GET - Search songs
fetch("https://www.calamuseducation.com/dhama/api/songs.php?search=keyword")
  .then((response) => response.json())
  .then((data) => console.log(data));

// POST - Create song (JSON)
fetch("https://www.calamuseducation.com/dhama/api/songs.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    title: "New Song",
    artist_id: 1,
    description: "Song description",
    audio_url: "http://example.com/audio.mp3",
    duration: 180,
  }),
})
  .then((response) => response.json())
  .then((data) => console.log(data));

// POST - Create song (multipart/form-data with files)
const songFormData = new FormData();
songFormData.append("title", "New Song");
songFormData.append("artist_id", "1");
songFormData.append("description", "Song description");
songFormData.append("audio", audioInput.files[0]);
songFormData.append("cover_image", coverInput.files[0]);
songFormData.append("duration", "180");

fetch("https://www.calamuseducation.com/dhama/api/songs.php", {
  method: "POST",
  body: songFormData,
})
  .then((response) => response.json())
  .then((data) => console.log(data));

// PUT - Update song
fetch("https://www.calamuseducation.com/dhama/api/songs.php?id=1", {
  method: "PUT",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    title: "Updated Title",
    description: "Updated description",
  }),
})
  .then((response) => response.json())
  .then((data) => console.log(data));

// DELETE - Delete song
fetch("https://www.calamuseducation.com/dhama/api/songs.php?id=1", {
  method: "DELETE",
})
  .then((response) => response.json())
  .then((data) => console.log(data));

// GET - Get featured songs
fetch("https://www.calamuseducation.com/dhama/api/featured.php?limit=10")
  .then((response) => response.json())
  .then((data) => console.log(data));
```

### cURL

```bash
# GET - Get all artists
curl https://www.calamuseducation.com/dhama/api/artists.php

# GET - Get single artist
curl https://www.calamuseducation.com/dhama/api/artists.php?id=1

# POST - Create artist (JSON)
curl -X POST https://www.calamuseducation.com/dhama/api/artists.php \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Artist",
    "bio": "Artist biography",
    "image_url": "http://example.com/image.jpg"
  }'

# POST - Create artist (multipart/form-data with file)
curl -X POST https://www.calamuseducation.com/dhama/api/artists.php \
  -F "name=New Artist" \
  -F "bio=Artist biography" \
  -F "image=@/path/to/image.jpg"

# PUT - Update artist
curl -X PUT https://www.calamuseducation.com/dhama/api/artists.php?id=1 \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name",
    "bio": "Updated bio"
  }'

# DELETE - Delete artist
curl -X DELETE https://www.calamuseducation.com/dhama/api/artists.php?id=1

# GET - Get all songs
curl https://www.calamuseducation.com/dhama/api/songs.php

# GET - Get songs with filters
curl "https://www.calamuseducation.com/dhama/api/songs.php?artist_id=1&search=keyword&limit=10"

# POST - Create song (JSON)
curl -X POST https://www.calamuseducation.com/dhama/api/songs.php \
  -H "Content-Type: application/json" \
  -d '{
    "title": "New Song",
    "artist_id": 1,
    "description": "Song description",
    "audio_url": "http://example.com/audio.mp3",
    "duration": 180
  }'

# POST - Create song (multipart/form-data with files)
curl -X POST https://www.calamuseducation.com/dhama/api/songs.php \
  -F "title=New Song" \
  -F "artist_id=1" \
  -F "description=Song description" \
  -F "audio=@/path/to/audio.mp3" \
  -F "cover_image=@/path/to/cover.jpg" \
  -F "duration=180"

# PUT - Update song
curl -X PUT https://www.calamuseducation.com/dhama/api/songs.php?id=1 \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Title",
    "description": "Updated description"
  }'

# DELETE - Delete song
curl -X DELETE https://www.calamuseducation.com/dhama/api/songs.php?id=1

# GET - Get featured songs
curl https://www.calamuseducation.com/dhama/api/featured.php?limit=20

# GET - Get statistics
curl https://www.calamuseducation.com/dhama/api/stats.php
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

6. **File Uploads**:

   - **Image files**: Supported formats are JPG, PNG, GIF, WEBP. Maximum file size is 10MB. Images are automatically cropped to square (500x500px) for artists.
   - **Audio files**: Supported formats are MP3, WAV, OGG, M4A. Maximum file size is 500MB.
   - **Upload methods**: You can upload files using:
     - `multipart/form-data` with file field (e.g., `image` for artists, `audio` and `cover_image` for songs)
     - Base64 encoded strings in JSON (use `image_base64` or `cover_image_base64` fields)
     - Direct URLs (use `image_url` or `audio_url` fields)
   - When updating resources, providing a new file will replace the old one, and the old file will be automatically deleted.

7. **Request Content Types**:

   - Use `application/json` for JSON requests (without file uploads)
   - Use `multipart/form-data` for requests with file uploads
   - The API automatically detects the content type and handles both formats

8. **No Authentication**: All endpoints are publicly accessible. No authentication tokens or API keys are required.

---

## Support

For issues or questions, please refer to:

- `README.md` - General project documentation
- `README_SETUP.md` - Setup instructions
- `TROUBLESHOOTING.md` - Troubleshooting guide
