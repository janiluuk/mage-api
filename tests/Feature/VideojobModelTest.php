<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Videojob;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class VideojobModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('storage');
        $this->user = User::factory()->create();
    }

    public function test_update_progress_updates_all_progress_fields(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'job_time' => 0,
            'progress' => 0,
            'estimated_time_left' => 0,
        ]);

        $videoJob->updateProgress(120, 75, 30);

        $this->assertSame(120, $videoJob->job_time);
        $this->assertSame(75, $videoJob->progress);
        $this->assertSame(30, $videoJob->estimated_time_left);
    }

    public function test_reset_progress_resets_all_progress_fields_and_status(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_PROCESSING,
            'job_time' => 100,
            'progress' => 50,
            'estimated_time_left' => 25,
            'queued_at' => null,
        ]);

        Carbon::setTestNow('2024-01-01 12:00:00');
        $result = $videoJob->resetProgress(Videojob::STATUS_APPROVED);

        $this->assertSame($videoJob, $result);
        $this->assertSame(Videojob::STATUS_APPROVED, $videoJob->status);
        $this->assertSame(0, $videoJob->job_time);
        $this->assertSame(0, $videoJob->progress);
        $this->assertSame(0, $videoJob->estimated_time_left);
        $this->assertNotNull($videoJob->queued_at);
        Carbon::setTestNow();
    }

    public function test_reset_progress_clears_queued_at_when_not_approved(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_PROCESSING,
            'queued_at' => Carbon::now(),
        ]);

        $videoJob->resetProgress(Videojob::STATUS_CANCELLED);

        $this->assertSame(Videojob::STATUS_CANCELLED, $videoJob->status);
        $this->assertNull($videoJob->queued_at);
    }

    public function test_get_url_returns_media_url_when_finished_media_exists(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create();

        // Create a fake finished media file with proper MIME type
        $mediaFile = UploadedFile::fake()->createWithContent('test-video.mp4', 'fake video content');
        $media = $videoJob->addMedia($mediaFile)
            ->toMediaCollection(Videojob::MEDIA_FINISHED);

        $url = $videoJob->getUrl();

        $this->assertNotNull($url);
        // getUrl() returns getFullUrl() which is a URL, not a path
        $this->assertStringContainsString('test-video', $url);
        $this->assertEquals($media->getFullUrl(), $url);
    }

    public function test_get_url_returns_database_url_when_no_finished_media(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'url' => 'https://example.com/video.mp4',
        ]);

        $url = $videoJob->getUrl();

        $this->assertSame('https://example.com/video.mp4', $url);
    }

    public function test_get_url_returns_null_when_no_url_and_no_media(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'url' => null,
        ]);

        $url = $videoJob->getUrl();

        $this->assertNull($url);
    }

    public function test_get_queue_info_returns_empty_array_for_non_approved_status(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'status' => Videojob::STATUS_PROCESSING,
        ]);

        $queueInfo = $videoJob->getQueueInfo();

        $this->assertIsArray($queueInfo);
        $this->assertEmpty($queueInfo);
    }

    public function test_get_queue_info_returns_correct_queue_information(): void
    {
        Carbon::setTestNow('2024-01-01 12:00:00');

        $user = User::factory()->create();
        
        // Create jobs in different states
        $processingJob = Videojob::factory()->create([
            'status' => Videojob::STATUS_PROCESSING,
            'model_id' => 1,
        ]);

        $approvedJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_APPROVED,
            'queued_at' => Carbon::now()->addMinute(),
            'model_id' => 1,
            'frame_count' => 10,
        ]);

        $testJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_APPROVED,
            'queued_at' => Carbon::now(),
            'model_id' => 1,
            'frame_count' => 5,
        ]);

        // Create finished jobs for average calculation
        Videojob::factory()->create([
            'status' => Videojob::STATUS_FINISHED,
            'model_id' => 1,
            'job_time' => 100,
            'frame_count' => 10,
        ]);

        $queueInfo = $testJob->getQueueInfo();

        $this->assertIsArray($queueInfo);
        $this->assertArrayHasKey('total_jobs_processing', $queueInfo);
        $this->assertArrayHasKey('total_jobs_in_queue', $queueInfo);
        $this->assertArrayHasKey('your_position', $queueInfo);
        $this->assertArrayHasKey('your_estimated_time', $queueInfo);
        $this->assertSame(1, $queueInfo['total_jobs_processing']);
        $this->assertSame(2, $queueInfo['total_jobs_in_queue']);
        $this->assertSame(1, $queueInfo['your_position']); // First in queue

        Carbon::setTestNow();
    }

    public function test_get_finished_video_path_returns_correct_path(): void
    {
        config(['app.paths.processed' => '/path/to/processed']);
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'outfile' => 'output-12345.mp4',
        ]);

        $path = $videoJob->getFinishedVideoPath();

        $this->assertSame('/path/to/processed/output-12345.mp4', $path);
    }

    public function test_get_preview_image_path_returns_correct_path(): void
    {
        config(['app.paths.preview' => '/path/to/preview']);
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'preview_img' => 'preview-12345.jpg',
        ]);

        $path = $videoJob->getPreviewImagePath();

        $this->assertSame('/path/to/preview/preview-12345.jpg', $path);
    }

    public function test_get_preview_animation_path_returns_correct_path(): void
    {
        config(['app.paths.preview' => '/path/to/preview']);
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'preview_animation' => 'animation-12345.gif',
        ]);

        $path = $videoJob->getPreviewAnimationPath();

        $this->assertSame('/path/to/preview/animation-12345.gif', $path);
    }

    public function test_has_finished_video_returns_true_when_file_exists(): void
    {
        Storage::fake('local');
        $processedPath = storage_path('app/processed');
        if (!is_dir($processedPath)) {
            mkdir($processedPath, 0755, true);
        }

        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'outfile' => 'test-video.mp4',
        ]);
        config(['app.paths.processed' => $processedPath]);

        file_put_contents($videoJob->getFinishedVideoPath(), 'fake video content');

        $this->assertTrue($videoJob->hasFinishedVideo());

        // Cleanup
        if (file_exists($videoJob->getFinishedVideoPath())) {
            unlink($videoJob->getFinishedVideoPath());
        }
    }

    public function test_has_finished_video_returns_false_when_file_not_exists(): void
    {
        config(['app.paths.processed' => '/nonexistent/path']);
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'outfile' => 'nonexistent.mp4',
        ]);

        $this->assertFalse($videoJob->hasFinishedVideo());
    }

    public function test_add_attachment_adds_media_to_collection(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'revision' => 'rev-123',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $videoJob->addAttachment($tempFile, Videojob::MEDIA_TYPE_IMAGE, Videojob::MEDIA_PREVIEW, 'test-generator');

        $media = $videoJob->getMedia(Videojob::MEDIA_PREVIEW);
        $this->assertCount(1, $media);
        $this->assertSame('test-generator', $media->first()->getCustomProperty('generator'));
        $this->assertSame('rev-123', $media->first()->getCustomProperty('revision'));
        $this->assertSame(Videojob::MEDIA_TYPE_IMAGE, $media->first()->getCustomProperty('type'));

        unlink($tempFile);
    }

    /**
     * End-to-end test: Verify complete workflow from job creation to file retrieval
     */
    public function test_end_to_end_job_creation_and_file_retrieval(): void
    {
        // Step 1: Create a user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Step 2: Create a video job via factory
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_PENDING,
            'filename' => 'test-video.mp4',
            'outfile' => 'output-test-video.mp4',
        ]);

        // Step 3: Add original media (simulating upload)
        $originalFile = UploadedFile::fake()->createWithContent('original.mp4', 'fake video content');
        $originalMedia = $videoJob->addMedia($originalFile)
            ->preservingOriginal()
            ->toMediaCollection(Videojob::MEDIA_ORIGINAL);

        $videoJob->original_url = $originalMedia->getFullUrl();
        $videoJob->save();

        // Verify original media is accessible
        $this->assertNotNull($videoJob->original_url);
        $originalMediaCollection = $videoJob->getMedia(Videojob::MEDIA_ORIGINAL);
        $this->assertCount(1, $originalMediaCollection);

        // Step 4: Simulate job processing - update progress
        $videoJob->status = Videojob::STATUS_PROCESSING;
        $videoJob->updateProgress(60, 50, 30);
        $videoJob->save();

        $this->assertSame(Videojob::STATUS_PROCESSING, $videoJob->status);
        $this->assertSame(50, $videoJob->progress);

        // Step 5: Add finished video media (simulating job completion)
        $finishedFile = UploadedFile::fake()->createWithContent('finished-video.mp4', 'fake finished video content');
        $finishedMedia = $videoJob->addMedia($finishedFile)
            ->toMediaCollection(Videojob::MEDIA_FINISHED);

        // Refresh to get updated media relationships
        $videoJob->refresh();

        // Step 6: Mark job as finished
        $videoJob->status = Videojob::STATUS_FINISHED;
        $videoJob->progress = 100;
        $videoJob->url = $finishedMedia->getFullUrl();
        $videoJob->save();

        // Step 7: Verify finished media is accessible
        $finishedMediaCollection = $videoJob->getMedia(Videojob::MEDIA_FINISHED);
        $this->assertCount(1, $finishedMediaCollection);
        $this->assertNotNull($videoJob->url);

        // Step 8: Verify getUrl() returns the finished media URL
        $retrievedUrl = $videoJob->getUrl();
        $this->assertNotNull($retrievedUrl);
        $this->assertStringContainsString('finished-video', $retrievedUrl);

        // Step 9: Verify status endpoint returns the job with URL
        $response = $this->getJson("/status/{$videoJob->id}");
        $response->assertOk();
        $response->assertJson([
            'id' => $videoJob->id,
            'status' => Videojob::STATUS_FINISHED,
            'progress' => 100,
        ]);

        // Step 10: Verify we can retrieve media through model relationships
        $previewMedia = $videoJob->previewImages();
        $this->assertNotNull($previewMedia);

        $finishedMediaRelation = $videoJob->finished();
        $this->assertNotNull($finishedMediaRelation);

        // Step 11: Verify original media is still accessible
        $originalMediaRelation = $videoJob->original();
        $this->assertNotNull($originalMediaRelation);
    }

    /**
     * End-to-end test: Verify file retrieval through API status endpoint
     */
    public function test_end_to_end_file_retrieval_via_status_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create a finished job with media
        $videoJob = Videojob::factory()->for($user, 'user')->create([
            'status' => Videojob::STATUS_FINISHED,
            'url' => 'https://example.com/video.mp4',
        ]);

        // Add finished media
        $finishedFile = UploadedFile::fake()->createWithContent('completed-video.mp4', 'fake completed video content');
        $videoJob->addMedia($finishedFile)
            ->toMediaCollection(Videojob::MEDIA_FINISHED);

        // Update URL to point to media
        $finishedMedia = $videoJob->getMedia(Videojob::MEDIA_FINISHED)->first();
        $videoJob->url = $finishedMedia->getFullUrl();
        $videoJob->save();

        // Request status via API
        $response = $this->getJson("/api/video-jobs/{$videoJob->id}/status");

        $response->assertOk();
        
        // Verify the response contains job details
        $responseData = $response->json();
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('status', $responseData);
        $this->assertSame(Videojob::STATUS_FINISHED, $responseData['status']);

        // Verify URL can be retrieved via getUrl method
        $url = $videoJob->getUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('completed-video', $url);
    }

    public function test_verify_and_clean_previews_removes_invalid_files(): void
    {
        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'preview_animation' => 'nonexistent.gif',
            'preview_img' => 'nonexistent.jpg',
            'url' => 'nonexistent.mp4',
        ]);

        // Files don't exist, so they should be cleaned
        $videoJob->verifyAndCleanPreviews();

        $videoJob->refresh();
        $this->assertNull($videoJob->preview_animation);
        $this->assertNull($videoJob->preview_img);
        $this->assertNull($videoJob->url);
    }

    public function test_verify_and_clean_previews_preserves_valid_files(): void
    {
        Storage::fake('local');
        $previewPath = storage_path('app/preview');
        if (!is_dir($previewPath)) {
            mkdir($previewPath, 0755, true);
        }

        config(['app.paths.preview' => $previewPath]);
        config(['app.paths.processed' => storage_path('app/processed')]);

        $processedPath = storage_path('app/processed');
        if (!is_dir($processedPath)) {
            mkdir($processedPath, 0755, true);
        }

        $videoJob = Videojob::factory()->for($this->user, 'user')->create([
            'preview_animation' => 'valid-animation.gif',
            'preview_img' => 'valid-image.jpg',
            'outfile' => 'valid-video.mp4',
        ]);

        // Create actual files
        file_put_contents($videoJob->getPreviewAnimationPath(), 'animation content');
        file_put_contents($videoJob->getPreviewImagePath(), 'image content');
        file_put_contents($videoJob->getFinishedVideoPath(), 'video content');

        $originalAnimation = $videoJob->preview_animation;
        $originalImage = $videoJob->preview_img;
        $originalUrl = $videoJob->url;

        $videoJob->verifyAndCleanPreviews();

        $videoJob->refresh();
        // Files exist, so they should be preserved (but url might still be false if not set)
        $this->assertSame($originalAnimation, $videoJob->preview_animation);
        $this->assertSame($originalImage, $videoJob->preview_img);

        // Cleanup
        @unlink($videoJob->getPreviewAnimationPath());
        @unlink($videoJob->getPreviewImagePath());
        @unlink($videoJob->getFinishedVideoPath());
    }
}

