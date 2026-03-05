<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base API URL
    |--------------------------------------------------------------------------
    |
    | The base URL of your aipeexi instance (e.g., https://media.yourdomain.com/v1)
    | This is where the TinyPeexi service requests will be sent.
    |
    */
    'api_url' => env('TINYPEEXI_API_URL', 'http://localhost:8080'),

    /*
    |--------------------------------------------------------------------------
    | Base Delivery URL
    |--------------------------------------------------------------------------
    |
    | Used for generating the public delivery URLs for variants and original assets.
    |
    */
    'delivery_url' => env('TINYPEEXI_DELIVERY_URL', env('TINYPEEXI_API_URL', 'http://localhost:8080')),

    /*
    |--------------------------------------------------------------------------
    | Tenant Slug
    |--------------------------------------------------------------------------
    |
    | Your tenant/workspace slug on the aipeexi instance. Used to properly route
    | requests and generate clean public URLs like /a/{tenant_slug}/{sha}/w400.jpg
    |
    */
    'tenant_slug' => env('TINYPEEXI_TENANT_SLUG', 'dev'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | The Secret API Key required for authenticating requests to upload
    | or manipulate assets.
    |
    */
    'api_key' => env('TINYPEEXI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Advanced Configuration
    |--------------------------------------------------------------------------
    |
    | HTTP connection timeouts, retries, and default upload constraints.
    |
    */
    'advanced' => [
        // Timeout in seconds for API requests
        'timeout' => env('TINYPEEXI_TIMEOUT', 10),

        // Number of times to retry failed requests
        'retries' => env('TINYPEEXI_RETRIES', 3),

        // Delay between retries in milliseconds
        'retry_delay' => env('TINYPEEXI_RETRY_DELAY', 500),

        // Default upload constraints and behaviors
        'upload' => [
            // The maximum size (in bytes) a single uploaded file can be
            'max_filesize_bytes' => env('TINYPEEXI_MAX_UPLOAD_SIZE', 26214400), // 25MB

            // Provide an array of allowed MIME types, or leave empty to allow any
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'],

            // Strip EXIF metadata on original upload (if supported by api)
            'strip_metadata' => env('TINYPEEXI_STRIP_METADATA', true),

            // Immediately trigger an optimization routine on upload
            'auto_optimize' => env('TINYPEEXI_AUTO_OPTIMIZE', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Transformation Presets
    |--------------------------------------------------------------------------
    |
    | Define reusable presets here so you don't have to specify all options
    | every time you call TinyPeexi::variant().
    |
    */
    'defaults' => [
        // E-commerce defaults (e.g., centered, square canvas, white bg)
        'ecommerce' => [
            'canvas' => 1024,
            'pad' => 40,
            //'background' => 'white', // Can be 'white', 'transparent', or hex 'ffffff'
            'format' => 'webp',      // Target format: 'jpeg', 'png', 'webp', 'avif'
            'quality' => 85,         // Compression quality (1-100)
        ],

        // Example of defining your own global transformation presets
        'thumbnail' => [
            'width' => 200,
            'height' => 200,
            'fit' => 'cover',        // 'cover', 'contain', 'fill', 'inside', 'outside'
            'crop' => 'center',      // 'center', 'top', 'bottom', 'left', 'right', 'face', 'entropy', 'attention'
            'format' => 'webp',
            'quality' => 80
        ],

        // Example: Watermarked
        'watermarked' => [
            'watermark' => [
                'type' => 'image',       // 'image' or 'text'
                // 'asset_sha' => '...', // Un-comment to apply a default watermark globally
                'position' => 'bottomright',
                'opacity' => 0.5,
                'scale' => 0.15
            ]
        ],

        // Define global effect defaults across all variant generation if desired
        'effects' => [
            'blur' => env('TINYPEEXI_DEFAULT_BLUR', null),          // 0.3 - 1000.0
            'sharpen' => env('TINYPEEXI_DEFAULT_SHARPEN', null),    // 0.5 - 10.0
            'brightness' => env('TINYPEEXI_DEFAULT_BRIGHTNESS', null), // 0.1 - 10.0
            'contrast' => env('TINYPEEXI_DEFAULT_CONTRAST', null),     // 0.1 - 10.0
            'saturation' => env('TINYPEEXI_DEFAULT_SATURATION', null), // 0.0 - 2.0
            'grayscale' => env('TINYPEEXI_DEFAULT_GRAYSCALE', false),
        ],

        // Global orientation defaults
        'orientation' => [
            'rotate' => null,       // 0, 90, 180, 270, -90
            'flip' => null,         // 'h' (horizontal), 'v' (vertical), 'both'
            'auto_orient' => true   // Auto-fix EXIF orientation
        ]
    ]
];
