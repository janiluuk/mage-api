# File management API

The new file APIs are available under the `/api/files` namespace and require JWT authentication (`auth:api`). All payloads accept JSON unless otherwise noted.

## Upload a file
```
POST /api/files
Content-Type: multipart/form-data

file: <binary>
project_id?: string
meta?: object
```
- Enforces the 1 GB per-user quota.
- Stores metadata about the MIME type, original name, and optional project grouping.

## List files
```
GET /api/files?project_id=<id>&page=1&per_page=20
```
Returns a paginated feed of the current user's files with `limit`, `page`, and `data` entries.

## Delete a file
```
DELETE /api/files/{id}
```
Removes the physical asset and the metadata record when owned by the current user.

## Unzip an archive
```
POST /api/files/{id}/unzip
```
Expands a `.zip` file into individually tracked `user_files` rows. Quota is enforced before writing extracted files.

## Merge videos
```
POST /api/files/merge
{
  "file_ids": [10, 11, 12],
  "output_name": "final-cut",
  "project_id": "launch-trailer"
}
```
Concatenates at least two video files into a single MP4, returning the newly created `user_file` row.

## Import a file into another project
```
POST /api/files/{id}/import
{
  "project_id": "new-project-key"
}
```
Copies the asset into the target project folder and links it back to the original through `parent_file_id`.

## Transcode audio/video
```
POST /api/files/{id}/transcode
{
  "format": "mp4|mov|webm|mp3|aac",
  "width": 1280,
  "height": 720
}
```
Transcodes and optionally resizes the asset. The resulting file is recorded as a variant of the original.

## Attach audio to a video
```
POST /api/files/{id}/attach-audio
{
  "audio_file_id": 42,
  "start_seconds": 12.5,
  "end_seconds": 48.0,
  "output_name": "cut-with-soundtrack"
}
```
Mixes an uploaded audio track into a video. The optional `start_seconds`/`end_seconds` window trims the soundtrack to fit and the original audio file stays unchanged.

## Check quota usage
```
GET /api/files/quota
```
Returns the configured limit, the number of bytes used, and remaining space.

## Admin panel endpoints
All routes are protected by `AuthorizationChecker` and `IsAdministratorChecker`.

- `GET /api/administration/files/overview` — paginated storage usage for all users.
- `GET /api/administration/files/users/{userId}` — storage breakdown and files for a single user.
- `PUT /api/administration/files/users/{userId}/quota` — update a user's quota override in bytes.
- `GET /administration/files` — web admin panel with a tree view of user files and quota editing.
