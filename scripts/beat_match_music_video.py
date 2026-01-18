#!/usr/bin/env python3
"""Beat matching music video creation script.

Creates a music video by synchronizing video cuts to bass beats detected in audio.
"""

import argparse
import os
import random
from functools import partial
from typing import TypeAlias, Tuple, List
import numpy as np
import librosa
from moviepy.editor import AudioFileClip, VideoFileClip, concatenate_videoclips
from moviepy.video.fx.MultiplySpeed import MultiplySpeed
from pathlib import Path

AudioData: TypeAlias = np.ndarray
BeatTimes: TypeAlias = np.ndarray
VideoList: TypeAlias = List[str]


def reverse_time_transform(original_duration: float, fps: float, t: float) -> float:
    return max(0, min(original_duration - t - 1/fps, original_duration - 1/fps))


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description='Create a music video with cuts synchronized to bass beats'
    )
    parser.add_argument(
        'audio_file',
        type=str,
        help='Path to the input audio file (MP3/WAV)'
    )
    parser.add_argument(
        'video_directory',
        type=str,
        help='Directory containing MP4 video files'
    )
    parser.add_argument(
        'cut_intensity',
        type=int,
        choices=[1, 2, 3],
        help='Cut intensity: 1 (every beat), 2 (every 2nd beat), 3 (every 3rd beat)'
    )
    parser.add_argument(
        '-o', '--output',
        type=str,
        default='output_music_video.mp4',
        help='Output video file path (default: output_music_video.mp4)'
    )
    parser.add_argument(
        '-d', '--duration',
        type=float,
        default=2.0,
        help='Default clip duration in seconds (default: 2.0)'
    )
    parser.add_argument(
        '-s', '--start-time',
        type=float,
        default=0.0,
        help='Start time in seconds for audio processing (default: 0.0)'
    )
    parser.add_argument(
        '-e', '--end-time',
        type=float,
        default=None,
        help='End time in seconds for audio processing (default: full duration)'
    )
    parser.add_argument(
        '--direction',
        type=str,
        choices=['forward', 'backward', 'random'],
        default='random',
        help='Video playback direction: forward (normal), backward (reverse), or random (default: random)'
    )
    parser.add_argument(
        '--speed-factor',
        type=float,
        default=1.0,
        help='Speed factor for video playback (default: 1.0)'
    )

    return parser.parse_args()


def get_video_files(directory: str) -> VideoList:
    video_extensions = ['.mp4', '.MP4']
    video_files = []

    for ext in video_extensions:
        video_files.extend(Path(directory).glob(f'*{ext}'))

    if not video_files:
        raise ValueError(f'No MP4 files found in {directory}')

    return [str(f) for f in video_files]


def analyze_beats(audio_file: str, start_time: float = 0.0, end_time: float = None):
    """
    Analyzes beats by using beat tracking directly with a
    bass-focused onset curve. That is the most robust approach.
    """
    duration = None
    if end_time and end_time > start_time:
        duration = end_time - start_time

    y, sr = librosa.load(audio_file, sr=22050, offset=start_time, duration=duration)

    # --- Step 1: Create an onset envelope based ONLY on bass frequencies ---
    # We calculate the spectrogram and isolate the bass energy.
    stft = librosa.stft(y)
    freqs = librosa.fft_frequencies(sr=sr)
    bass_band = (freqs >= 20) & (freqs <= 200)
    # The sum of bass energy is our bass-specific onset envelope.
    bass_onset_env = np.sum(np.abs(stft[bass_band, :]), axis=0)

    # --- Step 2: Perform beat tracking with our bass envelope ---
    try:
        # This call requires a newer librosa version.
        tempo, beat_frames = librosa.beat.beat_track(onset_envelope=bass_onset_env, sr=sr, units='frames')
    except TypeError:
        # Fallback for older librosa versions
        print("Warning: Using fallback beat tracking due to older librosa version.")
        tempo, beat_frames = librosa.beat.beat_track(y=y, sr=sr, units='frames')

    if tempo.size > 0:
        print(f"Detected tempo: {tempo[0]:.2f} BPM")
    else:
        print("Could not detect tempo.")
        return np.array([]), y, sr  # Return blank array if nothing was found

    # Convert the frame indices to time stamps
    beat_times = librosa.frames_to_time(beat_frames, sr=sr, hop_length=512)

    print(f"Returning {len(beat_times)} bass-focused, regular beat times.")
    
    return beat_times, y, sr


def create_music_video(audio_file: str, video_files: VideoList, beat_times: BeatTimes, cut_intensity: int,
                      default_duration: float = 2.0, output_file: str = 'output_music_video.mp4',
                      start_time: float = 0.0, end_time: float = None, direction: str = 'random',
                      speed_factor: float = 1.0) -> str:
    """
    Creates a music video by editing video clips to match the detected beats.
    Supports variable playback speed of video clips.
    """
    if len(beat_times) == 0:
        raise ValueError("No beats were detected. Cannot create video.")

    # Load the audio file and cut it based on Start/End Time.
    full_audio = AudioFileClip(audio_file)
    if end_time and end_time > start_time:
        audio = full_audio.subclipped(start_time, end_time)
    elif start_time > 0:
        audio = full_audio.subclipped(start_time)
    else:
        audio = full_audio
    
    audio_duration = audio.duration
    print(f"Processing audio segment of duration: {audio_duration:.2f} seconds")

    # Select the beats based on the 'cut_intensity'.
    selected_beats = beat_times[::cut_intensity]

    # Make sure the beat list starts at the beginning (0) and stops at the end (audio_duration).
    if len(selected_beats) == 0 or selected_beats[0] > 0.1:
        selected_beats = np.insert(selected_beats, 0, 0)
    if selected_beats[-1] < audio_duration:
        selected_beats = np.append(selected_beats, audio_duration)

    clips = []
    videos_to_close = []
    target_size = None

    # Iterate through the beat pairs to determine the duration for each clip.
    for i in range(len(selected_beats) - 1):
        # The target duration of the clip in the final video (sync to the beat).
        final_duration = selected_beats[i + 1] - selected_beats[i]
        
        # Calculate the required duration of the *source* video clip.
        # For half speed (speed_factor=0.5) we need a clip twice as long.
        required_source_duration = final_duration / speed_factor

        video_file = random.choice(video_files)
        video = VideoFileClip(video_file)
        videos_to_close.append(video)

        # Cut a random snippet from the source video in the required length.
        if video.duration >= required_source_duration:
            max_start = video.duration - required_source_duration
            clip_start = random.uniform(0, max_start)
            clip = video.subclipped(clip_start, clip_start + required_source_duration)
        else:
            # If the source video is too short, take the whole video.
            clip = video

        # Use the speed effect.
        if speed_factor != 1.0:
            print(f"Applying speed factor {speed_factor} to clip {i+1}")
            speed_effect = MultiplySpeed(factor=speed_factor)
            clip = speed_effect.apply(clip)
            clip = clip.with_duration(final_duration)

        # Use the playback direction (forward, backward, random).
        if direction == 'backward':
            original_duration = clip.duration
            reverse_func = partial(reverse_time_transform, original_duration, clip.fps)
            clip = clip.time_transform(reverse_func)
            clip = clip.with_duration(original_duration)

        if direction == 'random':
            if random.choice([True, False]):  # Randomly choose whether to play backwards
                original_duration = clip.duration
                reverse_func = partial(reverse_time_transform, original_duration, clip.fps)
                clip = clip.time_transform(reverse_func)
                clip = clip.with_duration(original_duration)

        # Adjust the size of the videos to make them all the same size.
        if i == 0:
            target_size = clip.size
        if target_size and clip.size != target_size:
            clip = clip.resized(target_size)
            
        clips.append(clip)

    if not clips:
        raise ValueError('No valid video clips could be created')

    # Add all the created clips to a single video.
    final_video = concatenate_videoclips(clips)
    
    # Add the correctly cut audio track.
    final_video = final_video.with_audio(audio)

    # Make sure the final video is no longer than the audio track.
    final_video = final_video.subclipped(0, audio_duration)

    # Write the final video file.
    final_video.write_videofile(
        output_file,
        codec='libx264',
        audio_codec='aac',
        temp_audiofile='temp-audio.m4a',
        remove_temp=True
    )

    # Free up the resources.
    final_video.close()
    audio.close()
    full_audio.close()
    for video in videos_to_close:
        video.close()
    
    return output_file


def main() -> None:
    args = parse_arguments()

    if not os.path.exists(args.audio_file):
        raise FileNotFoundError('Audio file not found: ' + args.audio_file)

    if not os.path.isdir(args.video_directory):
        raise NotADirectoryError('Video directory not found: ' + args.video_directory)

    print('Analyzing audio file: ' + args.audio_file)
    if args.end_time:
        print('Processing from ' + str(args.start_time) + 's to ' + str(args.end_time) + 's')
    if args.start_time > 0 and args.end_time is None:
        print('Processing from ' + str(args.start_time) + 's to end')

    beat_times, _, _ = analyze_beats(
        args.audio_file,
        start_time=args.start_time,
        end_time=args.end_time
    )

    print(f'Found {len(beat_times)} beats')
    print(f'With cut intensity {args.cut_intensity}, will use {len(beat_times[::args.cut_intensity])} cuts')

    video_files = get_video_files(args.video_directory)
    print(f'Found {len(video_files)} video files')

    print('Creating music video...')
    output_file = create_music_video(
        args.audio_file,
        video_files,
        beat_times,
        args.cut_intensity,
        default_duration=args.duration,
        output_file=args.output,
        start_time=args.start_time,
        end_time=args.end_time,
        direction=args.direction,
        speed_factor=args.speed_factor
    )

    print('Music video created successfully: ' + output_file)


if __name__ == '__main__':
    main()

