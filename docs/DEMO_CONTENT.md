# Demo Content Generator

A Laravel Artisan command to populate the database with demo content for testing and showcasing the admin panel.

## Usage

### Basic Usage

Generate demo content with default amounts:
```bash
php artisan demo:content
```

This will create:
- 5 demo users
- 20 video jobs
- 30 user files (videos, images, audio)

### Customize Amounts

Specify custom amounts for each content type:

```bash
php artisan demo:content --users=10 --jobs=50 --files=100
```

### Clear Existing Demo Content

To clear existing demo content before generating new content:

```bash
php artisan demo:content --clear
```

**Note:** This only works in non-production environments.

## What Gets Generated

### Users
- Demo users with random usernames and emails
- Password for all demo users: `password`
- Users are assigned regular user roles (not admin)

### User Files
- Various file types: videos (MP4, MOV), audio (MP3, WAV), images (JPG, PNG)
- Files are assigned to random users and projects
- Random file sizes and metadata
- Placeholder image URLs using Picsum Photos service

### Video Jobs
- Various statuses: finished, processing, pending, approved, error, etc.
- Different job types: beat-match, audio-track-split, video-generation, etc.
- Realistic progress percentages based on status
- Preview images and thumbnails using placeholder services
- Generation parameters specific to each job type

## Preview Images

The command uses [Picsum Photos](https://picsum.photos/) to generate random placeholder images for:
- Video job preview images
- Video job thumbnails
- User file cover images

These images are served from external URLs, so an internet connection is required for them to display properly.

## Running in Docker

If you're running the application in Docker:

```bash
docker exec -it mage-api php artisan demo:content
```

## Examples

Generate a large amount of content for stress testing:
```bash
php artisan demo:content --users=50 --jobs=200 --files=500
```

Generate minimal content for quick testing:
```bash
php artisan demo:content --users=2 --jobs=5 --files=10
```

## Requirements

- A model file must exist in the `model_files` table (used as reference for video jobs)
- An admin user should exist (will be created if missing: `admin@jsonapi.com` / `secret`)

## Notes

- The command is idempotent and can be run multiple times
- Each run creates new content (does not check for duplicates)
- Use `--clear` to remove demo content before regenerating
- Demo content uses predictable patterns (e.g., `demo_` prefix) for easy identification

