<?php

namespace Tests\Unit\Services;

use App\Services\VideoJobs\FileSystemWatcher;
use Tests\TestCase;

class FileSystemWatcherTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/file_watcher_test_' . time();
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testDir)) {
            array_map('unlink', glob($this->testDir . '/*'));
            rmdir($this->testDir);
        }
        parent::tearDown();
    }

    public function test_watch_path_adds_directory()
    {
        $watcher = new FileSystemWatcher();
        $watcher->watchPath($this->testDir);

        $this->assertContains($this->testDir, $watcher->getWatchedPaths());
    }

    public function test_watch_path_throws_exception_for_invalid_directory()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $watcher = new FileSystemWatcher();
        $watcher->watchPath('/nonexistent/path/that/does/not/exist');
    }

    public function test_detects_new_files()
    {
        $watcher = new FileSystemWatcher(1);
        $watcher->watchPath($this->testDir);

        $detectedFiles = [];
        
        // Start watcher in background
        $pid = pcntl_fork();
        if ($pid == 0) {
            // Child process - run watcher for 5 seconds
            $watcher->start(function($file) use (&$detectedFiles) {
                $detectedFiles[] = $file;
            });
            exit(0);
        }

        // Parent process - create a test file
        sleep(1);
        $testFile = $this->testDir . '/test_video.mp4';
        file_put_contents($testFile, 'test content');
        
        sleep(3); // Give watcher time to detect
        
        // Stop child process
        posix_kill($pid, SIGTERM);
        pcntl_wait($status);

        $this->assertNotEmpty($detectedFiles);
    }

    public function test_is_running_returns_correct_state()
    {
        $watcher = new FileSystemWatcher();
        
        $this->assertFalse($watcher->isRunning());
    }

    public function test_scan_directory_finds_video_files()
    {
        // Create test video files
        touch($this->testDir . '/video1.mp4');
        touch($this->testDir . '/video2.webm');
        touch($this->testDir . '/video3.mov');
        touch($this->testDir . '/not_video.txt');

        $watcher = new FileSystemWatcher();
        $watcher->watchPath($this->testDir);

        $watchedPaths = $watcher->getWatchedPaths();
        $this->assertContains($this->testDir, $watchedPaths);
    }
}
