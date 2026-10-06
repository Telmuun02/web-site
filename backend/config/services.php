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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     | Cloudinary — номын нүүр зургийн хадгалалт.
     |
     | cloud_name нь зургийн хүргэлтийн URL бүрд ил харагддаг тул нууц биш.
     | api_key / api_secret нь зөвхөн БАЙРШУУЛАХ үед хэрэгтэй — зураг харуулахад
     | огт шаардлагагүй. Тиймээс production дээр secret байхгүй байсан ч
     | каталог хэвийн ажиллана.
     */
    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),

        // Бүх номд харагдах ганц нүүр зургийн Cloudinary дахь public_id.
        'default_cover' => env('CLOUDINARY_DEFAULT_COVER', 'folio/book-cover-default'),
    ],

    'linkedin-openid' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI'),
    ],

    /*
     | Google — "Google-ээр нэвтрэх".
     |
     | FE нь Google-ээс access_token авч /api/auth/google руу илгээнэ.
     | client_secret хэрэггүй — client_id нь токен МАНАЙ апп-д олгогдсон
     | эсэхийг (aud) шалгахад л хэрэгтэй.
     | default_company_id — шинэ Google хэрэглэгчийг оноох компани.
     */
    'google' => [
        'client_id'          => env('GOOGLE_CLIENT_ID'),
        'client_secret'      => env('GOOGLE_CLIENT_SECRET'),
        'redirect'           => env('GOOGLE_REDIRECT_URI'),
        'default_company_id' => env('GOOGLE_DEFAULT_COMPANY_ID'),
    ],

    /*
     | Anthropic (Claude) — чат туслах.
     |
     | api_key нь Console-оос (console.anthropic.com) авсан нууц түлхүүр —
     | зөвхөн server талд, хэзээ ч frontend руу явахгүй.
     | model-ийг env-д гаргасан нь код өөрчлөхгүйгээр солих боломж.
     */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],
    'voyage' => [
        'api_key' => env('VOYAGE_API_KEY'),
        'model'   => env('VOYAGE_MODEL', 'voyage-embedding-1'),
    ],
    'elastic' => ['host' => env('ELASTIC_HOST')],

    /*
     | Chimege — монгол хэлний speech-to-text (console.chimege.com).
     |
     | token нь зөвхөн server талд — frontend руу хэзээ ч явахгүй.
     */
    'chimege' => [
        'url'   => env('CHIMEGE_URL', 'https://api.chimege.com/v1.2'),
        'token' => env('CHIMEGE_TOKEN'),
    ],
];

    