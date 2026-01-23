<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use App\Models\UserFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileTaggingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    // ============================================
    // Authentication Tests
    // ============================================

    public function test_attach_tags_requires_authentication(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag = Tag::factory()->create();

        $response = $this->postJson("/api/files/{$file->id}/tags", [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertStatus(401);
    }

    public function test_detach_tag_requires_authentication(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag = Tag::factory()->create();

        $response = $this->deleteJson("/api/files/{$file->id}/tags/{$tag->id}");

        $response->assertStatus(401);
    }

    public function test_sync_tags_requires_authentication(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag = Tag::factory()->create();

        $response = $this->putJson("/api/files/{$file->id}/tags", [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertStatus(401);
    }

    public function test_list_files_by_tags_requires_authentication(): void
    {
        $response = $this->getJson('/api/files/by-tags');

        $response->assertStatus(401);
    }

    public function test_list_files_by_tag_requires_authentication(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->getJson("/api/files/by-tag/{$tag->id}");

        $response->assertStatus(401);
    }

    // ============================================
    // Authorization Tests - User Isolation
    // ============================================

    public function test_user_cannot_attach_tags_to_other_users_file(): void
    {
        $file = UserFile::factory()->for($this->otherUser, 'user')->create();
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/files/{$file->id}/tags", [
                'tag_ids' => [$tag->id],
            ]);

        $response->assertStatus(404);
    }

    public function test_user_cannot_detach_tags_from_other_users_file(): void
    {
        $file = UserFile::factory()->for($this->otherUser, 'user')->create();
        $tag = Tag::factory()->create();
        $file->tags()->attach($tag->id);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/files/{$file->id}/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    public function test_user_cannot_sync_tags_on_other_users_file(): void
    {
        $file = UserFile::factory()->for($this->otherUser, 'user')->create();
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/files/{$file->id}/tags", [
                'tag_ids' => [$tag->id],
            ]);

        $response->assertStatus(404);
    }

    public function test_user_only_sees_own_files_in_by_tags_endpoint(): void
    {
        // Create files for current user
        $file1 = UserFile::factory()->for($this->user, 'user')->create();
        $file2 = UserFile::factory()->for($this->user, 'user')->create();

        // Create files for other user
        $otherFile = UserFile::factory()->for($this->otherUser, 'user')->create();

        // Create tags
        $tag1 = Tag::factory()->create(['name' => 'Tag1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag2']);

        // Attach tags
        $file1->tags()->attach($tag1->id);
        $file2->tags()->attach($tag2->id);
        $otherFile->tags()->attach($tag1->id);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/files/by-tags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'groups',
                'total_tags',
            ]);

        $groups = $response->json('groups');
        $this->assertCount(2, $groups);

        // Verify only current user's files are included
        $allFileIds = collect($groups)->flatMap(fn($group) => $group['files'])
            ->pluck('id')
            ->toArray();

        $this->assertContains($file1->id, $allFileIds);
        $this->assertContains($file2->id, $allFileIds);
        $this->assertNotContains($otherFile->id, $allFileIds);
    }

    public function test_user_only_sees_own_files_in_by_tag_endpoint(): void
    {
        $tag = Tag::factory()->create(['name' => 'Shared Tag']);

        // Create files for current user
        $file1 = UserFile::factory()->for($this->user, 'user')->create();
        $file2 = UserFile::factory()->for($this->user, 'user')->create();

        // Create files for other user
        $otherFile = UserFile::factory()->for($this->otherUser, 'user')->create();

        // Attach tag to all files
        $file1->tags()->attach($tag->id);
        $file2->tags()->attach($tag->id);
        $otherFile->tags()->attach($tag->id);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/files/by-tag/{$tag->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tag',
                'files' => ['data', 'total'],
            ]);

        $files = $response->json('files.data');
        $fileIds = collect($files)->pluck('id')->toArray();

        $this->assertCount(2, $files);
        $this->assertContains($file1->id, $fileIds);
        $this->assertContains($file2->id, $fileIds);
        $this->assertNotContains($otherFile->id, $fileIds);
    }

    // ============================================
    // Validation Tests
    // ============================================

    public function test_attach_tags_validates_tag_ids_required(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/files/{$file->id}/tags", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_ids']);
    }

    public function test_attach_tags_validates_tag_ids_is_array(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/files/{$file->id}/tags", [
                'tag_ids' => 'not-an-array',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_ids']);
    }

    public function test_attach_tags_validates_tag_ids_exist(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/files/{$file->id}/tags", [
                'tag_ids' => [999, 1000], // Non-existent tag IDs
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_ids.0', 'tag_ids.1']);
    }

    public function test_sync_tags_validates_tag_ids(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/files/{$file->id}/tags", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_ids']);
    }

    // ============================================
    // Tag Attachment Tests
    // ============================================

    public function test_attach_tags_to_file(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag1 = Tag::factory()->create(['name' => 'Tag 1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag 2']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/files/{$file->id}/tags", [
                'tag_ids' => [$tag1->id, $tag2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'file' => ['id', 'tags'],
            ]);

        $this->assertDatabaseHas('user_file_tag', [
            'user_file_id' => $file->id,
            'tag_id' => $tag1->id,
        ]);

        $this->assertDatabaseHas('user_file_tag', [
            'user_file_id' => $file->id,
            'tag_id' => $tag2->id,
        ]);

        // Verify relationship
        $file->refresh();
        $this->assertCount(2, $file->tags);
        $this->assertTrue($file->tags->contains($tag1));
        $this->assertTrue($file->tags->contains($tag2));
    }

    public function test_attach_tags_does_not_create_duplicates(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag = Tag::factory()->create(['name' => 'Tag 1']);

        // Attach tag first time
        $response1 = $this->actingAs($this->user, 'api')
            ->postJson("/api/files/{$file->id}/tags", [
                'tag_ids' => [$tag->id],
            ]);

        $response1->assertStatus(200);

        // Attach same tag again
        $response2 = $this->actingAs($this->user, 'api')
            ->postJson("/api/files/{$file->id}/tags", [
                'tag_ids' => [$tag->id],
            ]);

        $response2->assertStatus(200);

        // Verify tag is only attached once
        $file->refresh();
        $this->assertCount(1, $file->tags);
        $this->assertEquals(1, $file->tags()->count());
    }

    public function test_attach_multiple_tags_at_once(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tags = Tag::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/files/{$file->id}/tags", [
                'tag_ids' => $tags->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200);

        $file->refresh();
        $this->assertCount(5, $file->tags);
    }

    // ============================================
    // Tag Detachment Tests
    // ============================================

    public function test_detach_tag_from_file(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag1 = Tag::factory()->create(['name' => 'Tag 1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag 2']);

        // Attach both tags
        $file->tags()->attach([$tag1->id, $tag2->id]);

        // Detach one tag
        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/files/{$file->id}/tags/{$tag1->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'file' => ['id', 'tags'],
            ]);

        $this->assertDatabaseMissing('user_file_tag', [
            'user_file_id' => $file->id,
            'tag_id' => $tag1->id,
        ]);

        // Verify other tag still attached
        $file->refresh();
        $this->assertCount(1, $file->tags);
        $this->assertTrue($file->tags->contains($tag2));
        $this->assertFalse($file->tags->contains($tag1));
    }

    public function test_detach_non_existent_tag_returns_success(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag = Tag::factory()->create();

        // Try to detach tag that's not attached
        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/files/{$file->id}/tags/{$tag->id}");

        // Should still return success (idempotent operation)
        $response->assertStatus(200);
    }

    // ============================================
    // Tag Sync Tests
    // ============================================

    public function test_sync_tags_replaces_all_tags(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag1 = Tag::factory()->create(['name' => 'Tag 1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag 2']);
        $tag3 = Tag::factory()->create(['name' => 'Tag 3']);
        $tag4 = Tag::factory()->create(['name' => 'Tag 4']);

        // Initially attach tag1 and tag2
        $file->tags()->attach([$tag1->id, $tag2->id]);

        // Sync with tag3 and tag4 (should replace tag1 and tag2)
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/files/{$file->id}/tags", [
                'tag_ids' => [$tag3->id, $tag4->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'file' => ['id', 'tags'],
            ]);

        $file->refresh();
        $this->assertCount(2, $file->tags);
        $this->assertTrue($file->tags->contains($tag3));
        $this->assertTrue($file->tags->contains($tag4));
        $this->assertFalse($file->tags->contains($tag1));
        $this->assertFalse($file->tags->contains($tag2));
    }

    public function test_sync_tags_with_empty_array_removes_all_tags(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag1 = Tag::factory()->create(['name' => 'Tag 1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag 2']);

        // Initially attach tags
        $file->tags()->attach([$tag1->id, $tag2->id]);

        // Sync with empty array
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/files/{$file->id}/tags", [
                'tag_ids' => [],
            ]);

        $response->assertStatus(200);

        $file->refresh();
        $this->assertCount(0, $file->tags);
    }

    // ============================================
    // Listing Files by Tags Tests
    // ============================================

    public function test_list_files_grouped_by_tags(): void
    {
        // Create files
        $file1 = UserFile::factory()->for($this->user, 'user')->create(['original_name' => 'file1.mp4']);
        $file2 = UserFile::factory()->for($this->user, 'user')->create(['original_name' => 'file2.mp4']);
        $file3 = UserFile::factory()->for($this->user, 'user')->create(['original_name' => 'file3.mp4']);

        // Create tags
        $tag1 = Tag::factory()->create(['name' => 'Video']);
        $tag2 = Tag::factory()->create(['name' => 'Music']);

        // Attach tags
        $file1->tags()->attach($tag1->id);
        $file2->tags()->attach($tag1->id);
        $file3->tags()->attach($tag2->id);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/files/by-tags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'groups' => [
                    '*' => [
                        'tag' => ['id', 'name', 'color', 'files_count'],
                        'files' => [
                            '*' => ['id', 'original_name'],
                        ],
                    ],
                ],
                'total_tags',
            ]);

        $groups = $response->json('groups');
        $this->assertCount(2, $groups);

        // Verify tag groups
        $videoGroup = collect($groups)->firstWhere('tag.name', 'Video');
        $musicGroup = collect($groups)->firstWhere('tag.name', 'Music');

        $this->assertNotNull($videoGroup);
        $this->assertNotNull($musicGroup);
        $this->assertCount(2, $videoGroup['files']);
        $this->assertCount(1, $musicGroup['files']);
        $this->assertEquals(2, $videoGroup['tag']['files_count']);
        $this->assertEquals(1, $musicGroup['tag']['files_count']);
    }

    public function test_list_files_by_tag_with_pagination(): void
    {
        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        // Create 25 files with the tag
        $files = UserFile::factory()->count(25)->for($this->user, 'user')->create();
        foreach ($files as $file) {
            $file->tags()->attach($tag->id);
        }

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/files/by-tag/{$tag->id}?per_page=15");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tag',
                'files' => ['data', 'total', 'per_page', 'current_page'],
            ]);

        $filesData = $response->json('files');
        $this->assertEquals(15, count($filesData['data']));
        $this->assertEquals(25, $filesData['total']);
    }

    public function test_list_files_by_tag_with_sorting(): void
    {
        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        // Create files with different names
        $file1 = UserFile::factory()->for($this->user, 'user')->create(['original_name' => 'zzz.mp4']);
        $file2 = UserFile::factory()->for($this->user, 'user')->create(['original_name' => 'aaa.mp4']);
        $file3 = UserFile::factory()->for($this->user, 'user')->create(['original_name' => 'mmm.mp4']);

        foreach ([$file1, $file2, $file3] as $file) {
            $file->tags()->attach($tag->id);
        }

        // Sort by name ascending
        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/files/by-tag/{$tag->id}?sort_by=original_name&sort_order=asc");

        $response->assertStatus(200);

        $files = $response->json('files.data');
        $this->assertEquals('aaa.mp4', $files[0]['original_name']);
        $this->assertEquals('mmm.mp4', $files[1]['original_name']);
        $this->assertEquals('zzz.mp4', $files[2]['original_name']);

        // Sort by name descending
        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/files/by-tag/{$tag->id}?sort_by=original_name&sort_order=desc");

        $files = $response->json('files.data');
        $this->assertEquals('zzz.mp4', $files[0]['original_name']);
        $this->assertEquals('mmm.mp4', $files[1]['original_name']);
        $this->assertEquals('aaa.mp4', $files[2]['original_name']);
    }

    public function test_list_files_by_tag_with_size_sorting(): void
    {
        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $file1 = UserFile::factory()->for($this->user, 'user')->create(['size' => 1000]);
        $file2 = UserFile::factory()->for($this->user, 'user')->create(['size' => 5000]);
        $file3 = UserFile::factory()->for($this->user, 'user')->create(['size' => 3000]);

        foreach ([$file1, $file2, $file3] as $file) {
            $file->tags()->attach($tag->id);
        }

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/files/by-tag/{$tag->id}?sort_by=size&sort_order=asc");

        $files = $response->json('files.data');
        $this->assertEquals(1000, $files[0]['size']);
        $this->assertEquals(3000, $files[1]['size']);
        $this->assertEquals(5000, $files[2]['size']);
    }

    public function test_list_files_by_tag_filters_by_project_id(): void
    {
        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $file1 = UserFile::factory()->for($this->user, 'user')->create(['project_id' => 'project1']);
        $file2 = UserFile::factory()->for($this->user, 'user')->create(['project_id' => 'project1']);
        $file3 = UserFile::factory()->for($this->user, 'user')->create(['project_id' => 'project2']);

        foreach ([$file1, $file2, $file3] as $file) {
            $file->tags()->attach($tag->id);
        }

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/files/by-tag/{$tag->id}?project_id=project1");

        $files = $response->json('files.data');
        $this->assertCount(2, $files);
        $this->assertEquals('project1', $files[0]['project_id']);
        $this->assertEquals('project1', $files[1]['project_id']);
    }

    // ============================================
    // File Index with Tag Filtering Tests
    // ============================================

    public function test_file_index_filters_by_tag_id(): void
    {
        $tag1 = Tag::factory()->create(['name' => 'Tag 1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag 2']);

        $file1 = UserFile::factory()->for($this->user, 'user')->create();
        $file2 = UserFile::factory()->for($this->user, 'user')->create();
        $file3 = UserFile::factory()->for($this->user, 'user')->create();

        $file1->tags()->attach($tag1->id);
        $file2->tags()->attach($tag1->id);
        $file3->tags()->attach($tag2->id);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/files?tag_id={$tag1->id}");

        $response->assertStatus(200);

        $files = $response->json('data');
        $this->assertCount(2, $files);
        $fileIds = collect($files)->pluck('id')->toArray();
        $this->assertContains($file1->id, $fileIds);
        $this->assertContains($file2->id, $fileIds);
        $this->assertNotContains($file3->id, $fileIds);
    }

    public function test_file_index_filters_by_tag_name(): void
    {
        $tag1 = Tag::factory()->create(['name' => 'Video']);
        $tag2 = Tag::factory()->create(['name' => 'Music']);

        $file1 = UserFile::factory()->for($this->user, 'user')->create();
        $file2 = UserFile::factory()->for($this->user, 'user')->create();
        $file3 = UserFile::factory()->for($this->user, 'user')->create();

        $file1->tags()->attach($tag1->id);
        $file2->tags()->attach($tag1->id);
        $file3->tags()->attach($tag2->id);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/files?tag_name=Video');

        $response->assertStatus(200);

        $files = $response->json('data');
        $this->assertCount(2, $files);
    }

    public function test_file_index_includes_tags_relationship(): void
    {
        $tag = Tag::factory()->create(['name' => 'Test Tag']);
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $file->tags()->attach($tag->id);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/files');

        $response->assertStatus(200);

        $fileData = collect($response->json('data'))->firstWhere('id', $file->id);
        $this->assertNotNull($fileData);
        $this->assertArrayHasKey('tags', $fileData);
        $this->assertCount(1, $fileData['tags']);
        $this->assertEquals($tag->id, $fileData['tags'][0]['id']);
    }

    public function test_file_index_supports_sorting(): void
    {
        $file1 = UserFile::factory()->for($this->user, 'user')->create(['original_name' => 'zzz.mp4']);
        $file2 = UserFile::factory()->for($this->user, 'user')->create(['original_name' => 'aaa.mp4']);
        $file3 = UserFile::factory()->for($this->user, 'user')->create(['original_name' => 'mmm.mp4']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/files?sort_by=original_name&sort_order=asc');

        $files = $response->json('data');
        $this->assertEquals('aaa.mp4', $files[0]['original_name']);
        $this->assertEquals('mmm.mp4', $files[1]['original_name']);
        $this->assertEquals('zzz.mp4', $files[2]['original_name']);
    }

    // ============================================
    // Edge Cases
    // ============================================

    public function test_list_files_by_nonexistent_tag_returns_empty(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/files/by-tag/99999');

        $response->assertStatus(404);
    }

    public function test_list_files_grouped_by_tags_returns_empty_when_no_tags(): void
    {
        // Create files without tags
        UserFile::factory()->count(3)->for($this->user, 'user')->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/files/by-tags');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('groups'));
        $this->assertEquals(0, $response->json('total_tags'));
    }

    public function test_file_with_multiple_tags_appears_in_multiple_groups(): void
    {
        $file = UserFile::factory()->for($this->user, 'user')->create();
        $tag1 = Tag::factory()->create(['name' => 'Tag 1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag 2']);

        $file->tags()->attach([$tag1->id, $tag2->id]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/files/by-tags');

        $groups = $response->json('groups');
        $this->assertCount(2, $groups);

        // File should appear in both groups
        $tag1Group = collect($groups)->firstWhere('tag.id', $tag1->id);
        $tag2Group = collect($groups)->firstWhere('tag.id', $tag2->id);

        $this->assertNotNull($tag1Group);
        $this->assertNotNull($tag2Group);
        $this->assertCount(1, $tag1Group['files']);
        $this->assertCount(1, $tag2Group['files']);
        $this->assertEquals($file->id, $tag1Group['files'][0]['id']);
        $this->assertEquals($file->id, $tag2Group['files'][0]['id']);
    }
}

