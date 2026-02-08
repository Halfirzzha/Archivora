<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // MinIO private storage untuk Archivora (S3-compatible)
        // For document archival with immutable storage requirements
        'minio' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET', 'archivora'),
            'endpoint' => env('AWS_ENDPOINT', 'http://minio:9000'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private', // WAJIB private
            'throw' => true,
            'report' => true,
        ],

        /*
        |----------------------------------------------------------------------
        | MinIO Avatar Archive (OPTIONAL - WORM Immutable Storage)
        |----------------------------------------------------------------------
        | 
        | For production-grade immutable avatar archiving, configure a separate
        | MinIO bucket with Object Lock (WORM - Write Once Read Many).
        | 
        | BUCKET CONFIGURATION (MinIO CLI or Console):
        | 1. Create bucket with versioning:
        |    mc mb myminio/avatars-archive
        |    mc version enable myminio/avatars-archive
        | 
        | 2. Enable Object Lock at bucket creation (CANNOT be added later):
        |    mc mb --with-lock myminio/avatars-archive-worm
        | 
        | 3. Set default retention policy (Compliance mode - cannot override):
        |    mc retention set --default COMPLIANCE "365d" myminio/avatars-archive-worm
        |    
        |    OR for "practically permanent" retention (100 years):
        |    mc retention set --default COMPLIANCE "36500d" myminio/avatars-archive-worm
        | 
        | 4. Create read-only IAM policy for app credentials:
        |    {
        |      "Version": "2012-10-17",
        |      "Statement": [
        |        {
        |          "Effect": "Allow",
        |          "Action": ["s3:PutObject", "s3:GetObject"],
        |          "Resource": "arn:aws:s3:::avatars-archive-worm/*"
        |        },
        |        {
        |          "Effect": "Deny",
        |          "Action": ["s3:DeleteObject", "s3:PutObjectRetention"],
        |          "Resource": "*"
        |        }
        |      ]
        |    }
        | 
        | 5. Assign policy to app user:
        |    mc admin policy attach myminio archivora-app-policy --user=archivora-app
        | 
        | APPLICATION USAGE:
        | - Local disk ('public'): Deletable cache for current avatar display
        | - Remote disk ('minio-avatar-archive'): Immutable archive, never deleted
        | - On upload: Write to both disks, store only latest path in DB
        | - On replace: Delete old from local, keep all versions in remote
        | 
        | SECURITY NOTES:
        | - Compliance Mode retention CANNOT be shortened by anyone (not even admin)
        | - Governance Mode allows privileged users to override (less secure)
        | - Object versioning provides recovery from accidental overwrites
        | - Separate read-only credentials prevent deletion from application layer
        |----------------------------------------------------------------------
        */
        // Uncomment and configure when implementing immutable avatar archiving:
        // 'minio-avatar-archive' => [
        //     'driver' => 's3',
        //     'key' => env('AWS_ARCHIVE_ACCESS_KEY_ID'), // Different read-only IAM user
        //     'secret' => env('AWS_ARCHIVE_SECRET_ACCESS_KEY'),
        //     'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        //     'bucket' => env('AWS_AVATAR_ARCHIVE_BUCKET', 'avatars-archive-worm'),
        //     'endpoint' => env('AWS_ENDPOINT', 'http://minio:9000'),
        //     'use_path_style_endpoint' => true,
        //     'visibility' => 'private',
        //     'throw' => true,
        //     'report' => true,
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
