<?php

namespace App\Services;

use App\Models\ModelFile;
use App\Models\Videojob;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DeforumProcessingService
{
    public function parseJob(Videojob $videoJob, string $path)
    {

        if (strstr($videoJob->mimetype, 'image') !== false) {
            try {
                list($width, $height, $type, $attr) = getimagesize($path);


                $videoJob->size = filesize($path);
                $videoJob->width = $width;
                $videoJob->height = $height;
                $scaled = $this->getScaledSize($videoJob);
                $videoJob->width = $scaled[0];
                $videoJob->height = $scaled[1];

                return $videoJob;
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                throw $e;
            }
        }
    }
    public function getScaledSize(Videojob $videoJob)
    {
        $max_dimension = 960;
        $width = $videoJob->width;
        $height = $videoJob->height;

        if ($width == $height && $width >= $max_dimension) {
            return [500, 500];
        } else if ($width > $height) {
            while ($width > $max_dimension) {
                $height = ($height * $max_dimension) / $width;
                $width = $max_dimension;
            }
        } else if ($width < $height) {
            while ($height > $max_dimension) {
                $width = ($width * $max_dimension) / $height;
                $height = $max_dimension;
            }
        }

        return [$width, $height];
    }


    public function startProcess(Videojob $videoJob, $previewFrames = 0)
    {
        $isPreview = $previewFrames > 0;

        try {
            $videoJob = $this->parseJob($videoJob, $videoJob->getOriginalVideoPath());
            $videoJob->save();
            $cmd = $this->buildCommandLine($videoJob, $videoJob->getOriginalVideoPath(), $videoJob->getFinishedVideoPath(), $previewFrames);
            $this->killProcess($videoJob->id);
            Log::info("Deforum Conversion {$videoJob->id}: Running {$cmd}");
            $process = Process::fromShellCommandline($cmd);
            $process->setTimeout(7200);
            try {
                $time = time();
                $output = $process->mustRun();
                // Parse the JSON output
                $decoded_output = json_decode($output->getOutput(), true);
                // Get the first job ID
                $first_job_id = $decoded_output['job_ids'][0];

                $running = true;
                $client = new \GuzzleHttp\Client();
                $execution_times = [];
                $progresses = [];
                while ($running) {

                    // Using GuzzleHttp\Client to make an API request
                    $response = $client->request('GET', 'http://192.168.2.100:7860/deforum_api/jobs/' . $first_job_id);
                    $data = json_decode($response->getBody(), true);
                    Log::info("Got response: {$response->getBody()}", ['data' => $data]);

                    $videoJob = Videojob::findOrFail($videoJob->id);
                    
                    if ($videoJob->status == 'cancelled' || $videoJob->status == 'error') {
                        $response = $client->request('DELETE', 'http://192.168.2.100:7860/deforum_api/jobs/' . $first_job_id);
                        Log::info("Deleted job {$first_job_id}: {$response->getBody()}");
                        $running = false;
                        
                    }
                    // Initialize arrays to hold last n values of execution_time and phase_progress


                   
                    // Update database
                    if ($data['phase'] == 'GENERATING') {
                            // Update arrays with latest values
                    
                        array_push($execution_times, $data['execution_time']);
                        array_push($progresses, $data['phase_progress']*100);

                        // Keep only the last n values
                        $n = 5; // You can choose a different value for n
                        if (count($execution_times) > $n) {
                            array_shift($execution_times);
                        }
                        if (count($progresses) > $n) {
                            array_shift($progresses);
                        }

                        // Calculate moving averages
                        $avg_execution_time = array_sum($execution_times) / count($execution_times);
                        $avg_progress = array_sum($progresses) / count($progresses);

                        // Calculate estimated time left using moving averages
                        if ($avg_progress > 0) {
                            $videoJob->estimated_time_left = round((($avg_execution_time / $avg_progress) * 100) - $data['execution_time']);
                        }
                        
                        $videoJob->progress = $data['phase_progress'] * 100;
                        $videoJob->job_time = $data['execution_time'];
          
                    }
                    
                    if ($data['phase'] === 'QUEUED' && $videoJob->status !== "approved") {
                        $videoJob->status = 'approved';
                    } else if ($data['phase'] == 'GENERATING' && $videoJob->status != "processing") {
                        $videoJob->status = 'processing';
                    }

                    if ($data['status'] === 'SUCCEEDED' && $data['phase'] == 'DONE') {
                        $sourceFile = implode("/", [$data['outdir'],  $data['timestring'] . '.mp4']);
                        $sourceAnimation = implode("/", [$data['outdir'],  $data['timestring'] . '.gif']);
                        $previewPic = implode("/", [$data['outdir'],  $data['timestring'] . '_000000010.png']);

                        if (is_file($sourceFile)) {
                            $videoJob->outfile = basename($sourceFile);
                            $targetUrl = config('app.url') . '/processed/' . $videoJob->outfile;
                            $videoJob->url = $targetUrl;
                            rename($sourceFile, $videoJob->getFinishedVideoPath());
                        }
                        if (is_file($sourceAnimation)) {
                            $videoJob->preview_animation = $sourceAnimation;
                            rename($sourceAnimation, $videoJob->getPreviewAnimationPath());
                        }
                        if (is_file($previewPic)) {
                            $videoJob->preview_img = config('app.url') . '/preview/' . $videoJob->outfile;
                            rename($previewPic, $videoJob->getPreviewImagePath());
                        }


                        $videoJob->save();
                        $running = false;

                    } elseif ($data['status'] !== 'ACCEPTED' && $data['status'] !== 'SUCCEEDED') {
                        $videoJob->status = 'error';
                        $videoJob->save();
                        $running = false;
                        
                        throw new \Exception("Error in job: " . json_encode($data));

                    }

                    $videoJob->save();
                    if ($running) sleep(5);
                }


                $elapsed = time() - $time;
                $videoJob->updateProgress($elapsed, 99, 7)->save();

                if ($videoJob->frame_count == 0)
                    $videoJob->frame_count++;

                Log::info("Finished in {" . (time() - $time) . "} seconds :  {$videoJob->frame_count} frames on " . round($videoJob->frame_count / $elapsed) . "  frames/s speed. {output} ", ['output' => $process->getOutput()]);

                $videoJob->attachResults();
                $videoJob->save();

                $videoJob->refresh();

                Log::info("Paths: ", ['preview' => $videoJob->getMediaFilesForRevision('image'), 'animation' => $videoJob->getMediaFilesForRevision('animation'), 'finished_video' => $videoJob->getMediaFilesForRevision('video', 'finished')]);

                //$videoJob->verifyAndCleanPreviews();
                $videoJob->status = ($isPreview) ? 'preview' : 'finished';

                $videoJob->updateProgress(time() - $time, 100, 0)->save();

            } catch (ProcessFailedException $exception) {
                Log::info('Error while making ' . ($isPreview ? "preview" : "final") . ' conversion for ' . $videoJob->filename, ['exception' => $exception->getMessage()]);
                $videoJob->status = "error";
                $videoJob->save();

                throw $exception;
            }
        } catch (\Exception $e) {

            Log::info("Error while processing video {$videoJob->filename}: {$e->getMessage()} ", ['error' => $e->getMessage(), 'videoFile' => $videoJob->filename]);
            $videoJob->resetProgress('error');
            $videoJob->save();
            throw new \Exception($e->getMessage());
        }
    }
    private function buildPreviewParameters(VideoJob $videoJob, $previewFrames = 0): array
    {
        $params = [];
        if ($previewFrames > 0) {
            $filename_ext = pathinfo($videoJob->outfile, PATHINFO_EXTENSION);
            $previewFile = preg_replace('/^(.*)\.' . $filename_ext . '$/', '$1_preview.' . 'png', $videoJob->outfile);
            $previewPath = sprintf('%s', rtrim(config('app.paths.preview'), '/'));
            $animationFile = preg_replace('/^(.*)\.' . $filename_ext . '$/', '$1_animated_preview.' . 'png', $videoJob->outfile);

            $params['preview_img'] = $previewFrames >= 1 ? sprintf("%s/%s", $previewPath, basename($previewFile)) : '';
            $params['preview_animation'] = $previewFrames > 1 ? sprintf("%s/%s", $previewPath, basename($animationFile)) : '';
            $params['limit_frames_amount'] = $previewFrames;

            Log::info(sprintf("Setting paths for preview_img, preview_animation to path: %s / %s / %s", $params['preview_img'], $params['preview_animation'], $previewPath));
        }
        return $params;
    }

    private function buildCommandLine(VideoJob $videoJob, $sourceFile, $outFile, $previewFrames = 0)
    {

        $modelFile = ModelFile::find($videoJob->model_id);
        $file = explode(" [", $modelFile->filename);
        $modelFilename = $file[0];

        $cmdString = '';
        $json_settings=[];

        $params = [
            'modelFile' => $modelFile->filename,
            'init_img' => $videoJob->getOriginalVideoPath(),
            'json_settings_file' => '/www/api/scripts/zoom.json',
        ];
        
        $json_settings['prompts'] = json_decode('{"0": "' . $videoJob->prompt . ' --neg bad-picture-chill-75v"}');
        $json_settings['checkpoint_schedule'] = '"0": "' . $modelFilename . '", "100": "'.  $modelFilename . '"'; 
        $json_settings['sd_model_name'] = $modelFilename;
        $json_settings['max_frames'] = $videoJob->frame_count;
        $json_settings['sd_model_hash'] = str_replace("]", "", $file[1]);
        $params['json_settings'] = json_encode($json_settings);


        $videoJob->generation_parameters = json_encode($params);
        $videoJob->revision = md5($videoJob->generation_parameters);

        $videoJob->save();

        // $params += $this->buildPreviewParameters($videoJob, $previewFrames);


        foreach ($params as $key => $val) {
            if ($key == 'modelFile' || $key == 'json_settings') {
                $cmdString .= sprintf("--%s='%s' ", $key, $val);
            } else
                $cmdString .= sprintf('--%s=%s ', $key, $val);
        }

        $processor = config('app.paths.deforum_processor_path');

        $cmdParts = [
            $processor,
            $cmdString,
            '--start'
        ];

        return implode(' ', $cmdParts);
    }
    public function applyPrompts(Videojob $videoJob)
    {

        $promptSuffix = config('app.processing.default_prompt_suffix');
        $negPromptSuffix = config('app.processing.default_negative_prompt_suffix');

        $prompt = sprintf('%s, %s', str_replace('"', "", trim($videoJob->prompt)), $promptSuffix);

        if (empty($videoJob->negative_prompt)) {
            $negativePrompt = $negPromptSuffix;
        } else {
            $negativePrompt = sprintf("%s, %s", str_replace('"', "", trim($videoJob->negative_prompt)), $negPromptSuffix);
        }
        Log::info("Resolved prompts as " . $prompt . " && " . $negativePrompt);

        return [$prompt, $negativePrompt];
    }
    public function cancelJob(Videojob $videoJob)
    {

        $this->killProcess($videoJob->id);

        if ($videoJob->status == Videojob::STATUS_PROCESSING || $videoJob->status == Videojob::STATUS_APPROVED) {
            $videoJob->resetProgress('cancelled');
            $videoJob->save();
        }
    }
    /**
     * Kill existing process
     *
     * @param int $id
     * @return void
     */
    public function killProcess($sessionId)
    {
        try {
            $pids = false;

            exec('ps aux | grep -i deforum.py | grep -i \"\-\-jobid=' . $sessionId . '\" | grep -v grep', $pids);

            if (empty($pids) || count($pids) < 1) {
                return;
            } else {

                Log::info("Killing process {$sessionId}", ['pids' => $pids]);
                $command = sprintf("kill -9 %s", $pids[0]);
                $process = \Illuminate\Support\Facades\Process::run($command);
                Log::info($process->output());
            }
        } catch (ProcessFailedException $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}