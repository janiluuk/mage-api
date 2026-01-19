<?php
$_ENV['APP_DEBUG'] = 'true';

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DebugTest extends TestCase {
    use RefreshDatabase;

    public function test_debug() {
        $user = User::factory()->create();
        $audioFile = UploadedFile::fake()->create('test-audio.wav', 100);
        
        $this->actingAs($user, 'api');
        
        $response = $this->call('POST', '/api/v1/custom-jobs/process', [
            'job_type' => 'audio-track-split',
            'input_type' => 'files',
            'options' => json_encode([
                'model' => 'MDX-Net-InstVoc_HQ_3',
                'output_format' => 'wav',
            ]),
        ], [], [
            'audio_file' => $audioFile,
        ], [
            'Accept' => 'application/json',
        ]);
        
        echo "Status: " . $response->status() . "\n";
        echo "Response: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
        echo "Content: " . $response->getContent() . "\n";
    }
}

// Run test
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

