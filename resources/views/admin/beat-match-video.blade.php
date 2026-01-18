@extends('layouts.admin')

@section('title', 'Beat Match Music Video')

@php
    $pageTitle = 'Beat Match Music Video';
@endphp

@section('breadcrumbs')
<x-breadcrumbs :items="[
    ['label' => 'Admin', 'url' => route('admin.files')],
    ['label' => 'Beat Match Music Video']
]" />
@endsection

@section('content')
<h1>Beat Match Music Video</h1>
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
@endsection

@push('scripts')
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
        statusDiv.classList.remove('show');
        progressBar.classList.add('show');
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
                progressBar.classList.remove('show');
            }
        } catch (error) {
            showStatus('error', 'Error: ' + error.message);
            progressBar.classList.remove('show');
        } finally {
            submitBtn.disabled = false;
        }
    });

    function showStatus(type, message) {
        statusDiv.className = 'status ' + type + ' show';
        statusDiv.innerHTML = message;
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
                    progressBar.classList.remove('show');
                    
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
@endpush
