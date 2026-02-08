<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver that will be used to hash
    | passwords for your application. By default, the bcrypt algorithm is
    | used; however, you remain free to modify this option if you wish.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    | Mid-Master Professional: Use argon2id for stronger security
    | Argon2id is resistant to GPU/ASIC attacks and side-channel attacks
    |
    */

    'driver' => 'argon2id',

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Bcrypt algorithm. This will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12), // Increased from default 10 for better security
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Argon algorithm. These will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

    'argon' => [
        'memory' => 65536, // 64MB - Good balance for production
        'threads' => 2,
        'time' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon2id Options (RECOMMENDED FOR MID-MASTER SECURITY)
    |--------------------------------------------------------------------------
    |
    | Argon2id combines the best of Argon2i (side-channel resistant) and
    | Argon2d (GPU-resistant). Perfect for authentication systems.
    |
    | Memory: 64MB (65536 KB) - Prevents brute force attacks
    | Threads: 2 - Parallel processing
    | Time: 4 iterations - Slows down attackers without affecting UX
    |
    */

    'argon2id' => [
        'memory' => 65536, // 64MB
        'threads' => 2,
        'time' => 4,
    ],

];
