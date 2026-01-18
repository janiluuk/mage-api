<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mage Admin · Beat Match Music Video</title>
    <style>
        body {
            font-family: "Inter", "Segoe UI", sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            margin: 0;
            padding: 32px;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        p {
            color: #94a3b8;
        }
        .panel {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #e2e8f0;
        }
        input[type="file"],
        input[type="number"],
        input[type="text"],
        select {
            background: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 8px 12px;
            border-radius: 6px;
            width: 100%;
            max-width: 400px;
        }
        input[type="file"] {
            padding: 6px;
            cursor: pointer;
        }
        button {
            background: #2563eb;
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 12px;
        }
        button:disabled {
            background: #334155;
            cursor: not-allowed;
        }
        button:hover:not(:disabled) {
            background: #1d4ed8;
        }
        .status {
            font-size: 14px;
            margin-top: 12px;
            padding: 12px;
            border-radius: 6px;
            display: none;
        }
        .status.success {
            background: #065f46;
            color: #6ee7b7;
            border: 1px solid #047857;
        }
        .status.error {
            background: #7f1d1d;
            color: #fca5a5;
            border: 1px solid #991b1b;
        }
        .status.info {
            background: #1e3a8a;
            color: #93c5fd;
            border: 1px solid #1e40af;
        }
        .file-list {
            margin-top: 8px;
            font-size: 14px;
            color: #94a3b8;
        }
        .help-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #1e293b;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
            display: none;
        }
        .progress-bar-fill {
            height: 100%;
            background: #2563eb;
            transition: width 0.3s;
        }
        #job-status {
            margin-top: 20px;
        }
        .job-info {
            background: #1e293b;
            padding: 12px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 14px;
        }
        .job-info strong {
            color: #e2e8f0;
        }
    </style>
</head>
<body>
    <h1>Mage Admin · Beat Match Music Video</h1>
    <p>Create a music video with cuts synchronized to bass beats in audio.</p>

    <div class="panel">
        <form id="beat-match-form" enctype="multipart/form-data">
            <div class="form-group">
                <label for="audio_file">Audio File (MP3, WAV, AAC, M4A) *</label>
                <input type="file" id="audio_file" name="audio_file" accept="audio/*" required>
                <div class="help-text">Upload the audio file to analyze for beats.</div>
            </div>

            <div class="form-group">
                <label for="video_files">Video Files (MP4, MOV, WebM) *</label>
                <input type="file" id="video_files" name="video_files[]" accept="video/*" multiple required>
                <div class="help-text">Select multiple video files to use for the music video. At least 1 file required.</div>
                <div id="video-files-list" class="file-list"></div>
            </div>

            <div class="form-group">
                <label for="cut_intensity">Cut Intensity *</label>
                <select id="cut_intensity" name="cut_intensity" required>
                    <option value="1">1 - Every beat</option>
                    <option value="2">2 - Every 2nd beat</option>
                    <option value="3" selected>3 - Every 3rd beat</option>
                </select>
                <div class="help-text">How often to cut between video clips based on detected beats.</div>
            </div>

            <div class="form-group">
                <label for="direction">Playback Direction</label>
                <select id="direction" name="direction">
                    <option value="random" selected>Random - Mix of forward and backward</option>
                    <option value="forward">Forward - Normal playback</option>
                    <option value="backward">Backward - Reverse playback</option>
                </select>
                <div class="help-text">Direction of video playback for each clip.</div>
            </div>

            <div class="form-group">
                <label for="speed_factor">Speed Factor</label>
                <input type="number" id="speed_factor" name="speed_factor" min="0.1" max="2.0" step="0.1" value="1.0">
                <div class="help-text">Speed multiplier for video playback (0.5 = half speed, 2.0 = double speed).</div>
            </div>

            <div class="form-group">
                <label for="start_time">Start Time (seconds)</label>
                <input type="number" id="start_time" name="start_time" min="0" step="0.1" value="0">
                <div class="help-text">Start time in seconds for audio processing (optional).</div>
            </div>

            <div class="form-group">
                <label for="end_time">End Time (seconds)</label>
                <input type="number" id="end_time" name="end_time" min="0" step="0.1">
                <div class="help-text">End time in seconds for audio processing (optional, leave empty for full duration).</div>
            </div>

            <button type="submit" id="submit-btn">Create Music Video</button>
            <div class="status" id="status"></div>
            <div class="progress-bar" id="progress-bar">
                <div class="progress-bar-fill" id="progress-fill"></div>
            </div>
        </form>

        <div id="job-status" style="display: none;">
            <h3>Job Status</h3>
            <div class="job-info" id="job-info"></div>
        </div>
    </div>

    <script>
        const form = document.getElementById('beat-match-form');
        const statusDiv = document.getElementById('status');
        const progressBar = document.getElementById('progress-bar');
        const progressFill = document.getElementById('progress-fill');
        const submitBtn = document.getElementById('submit-btn');
        const videoFilesInput = document.getElementById('video_files');
        const videoFilesList = document.getElementById('video-files-list');
        const jobStatusDiv = document.getElementById('job-status');
        const jobInfoDiv = document.getElementById('job-info');
        let statusCheckInterval = null;
        let currentJobId = null;

        // Show selected video files
        videoFilesInput.addEventListener('change', function() {
            const files = Array.from(this.files);
            if (files.length > 0) {
                videoFilesList.textContent = `${files.length} file(s) selected: ${files.map(f => f.name).join(', ')}`;
            } else {
                videoFilesList.textContent = '';
            }
        });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            submitBtn.disabled = true;
            statusDiv.style.display = 'none';
            progressBar.style.display = 'block';
            progressFill.style.width = '0%';

            try {
                const response = await fetch('/administration/beat-match-video/process', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: formData,
                    credentials: 'same-origin',
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showStatus('success', 'Music video job queued successfully! Job ID: ' + data.job_id);
                    currentJobId = data.job_id;
                    jobStatusDiv.style.display = 'block';
                    startStatusCheck(data.job_id);
                } else {
                    showStatus('error', data.message || 'Failed to create music video job');
                    progressBar.style.display = 'none';
                }
            } catch (error) {
                showStatus('error', 'Error: ' + error.message);
                progressBar.style.display = 'none';
            } finally {
                submitBtn.disabled = false;
            }
        });

        function showStatus(type, message) {
            statusDiv.className = 'status ' + type;
            statusDiv.textContent = message;
            statusDiv.style.display = 'block';
        }

        function startStatusCheck(jobId) {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
            }

            statusCheckInterval = setInterval(async () => {
                try {
                    const response = await fetch(`/administration/beat-match-video/status/${jobId}`);
                    const data = await response.json();

                    updateJobStatus(data);

                    if (data.status === 'finished' || data.status === 'error') {
                        clearInterval(statusCheckInterval);
                        progressBar.style.display = 'none';
                        
                        if (data.status === 'finished' && data.url) {
                            showStatus('success', `Processing complete! <a href="${data.url}" target="_blank" style="color: #6ee7b7;">View Video</a>`);
                        } else if (data.status === 'error') {
                            showStatus('error', 'Processing failed: ' + (data.error || 'Unknown error'));
                        }
                    } else {
                        const progress = data.progress || 0;
                        progressFill.style.width = progress + '%';
                    }
                } catch (error) {
                    console.error('Error checking status:', error);
                }
            }, 2000);
        }

        function updateJobStatus(data) {
            const info = `
                <strong>Job ID:</strong> ${data.id}<br>
                <strong>Status:</strong> ${data.status}<br>
                <strong>Progress:</strong> ${data.progress || 0}%<br>
                ${data.job_time ? `<strong>Processing Time:</strong> ${data.job_time}s<br>` : ''}
                ${data.estimated_time_left ? `<strong>Estimated Time Left:</strong> ${data.estimated_time_left}s<br>` : ''}
                ${data.url ? `<strong>Video URL:</strong> <a href="${data.url}" target="_blank" style="color: #93c5fd;">${data.url}</a>` : ''}
            `;
            jobInfoDiv.innerHTML = info;
        }
    </script>
</body>
</html>

