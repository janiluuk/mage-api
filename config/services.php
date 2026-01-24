<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URL')
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL')
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URL')
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'uvr5' => [
        'docker_image' => env('UVR5_DOCKER_IMAGE', 'upseem/uvr5-cli-no-ui:latest'),
        'base_url' => env('UVR5_BASE_URL'),
        'use_docker' => env('UVR5_USE_DOCKER', true),
        'python_path' => env('UVR5_PYTHON_PATH', 'python3'),
    ],

    'ffmpeg' => [
        'binaries' => [
            'ffmpeg' => env('FFMPEG_BINARY', '/usr/bin/ffmpeg'),
            'ffprobe' => env('FFPROBE_BINARY', '/usr/bin/ffprobe'),
        ],
        'audio_processing' => [
            // Compressor filter parameters
            'compressor' => [
                'threshold' => env('FFMPEG_COMPRESSOR_THRESHOLD', '-20dB'),
                'ratio' => env('FFMPEG_COMPRESSOR_RATIO', '2'),
                'attack' => env('FFMPEG_COMPRESSOR_ATTACK', '5'),
                'release' => env('FFMPEG_COMPRESSOR_RELEASE', '50'),
            ],
            // High-pass filter parameters
            'highpass' => [
                'frequency' => env('FFMPEG_HIGHPASS_FREQ', '120'),
            ],
            // Echo filter parameters
            'echo' => [
                'in_gain' => env('FFMPEG_ECHO_IN_GAIN', '0.8'),
                'out_gain' => env('FFMPEG_ECHO_OUT_GAIN', '0.9'),
                'delay' => env('FFMPEG_ECHO_DELAY', '1000'),
                'decay' => env('FFMPEG_ECHO_DECAY', '0.3'),
            ],
            // Limiter filter parameters
            'limiter' => [
                'limit' => env('FFMPEG_LIMITER_LIMIT', '0.95'),
            ],
            // Output format parameters
            'output' => [
                'channels' => env('FFMPEG_OUTPUT_CHANNELS', '2'),
                'bitrate' => env('FFMPEG_OUTPUT_BITRATE', '128k'),
                'format' => env('FFMPEG_OUTPUT_FORMAT', 'adts'),
            ],
        ],
    ],

];
