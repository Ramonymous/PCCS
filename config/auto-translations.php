<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Language Files Path
    |--------------------------------------------------------------------------
    |
    | The base path where your language files are stored. By default, it's
    | the 'lang' directory. You can change this to match your application's
    | structure.
    |
    */

    'lang_path' => lang_path(),

    /*
    |--------------------------------------------------------------------------
    | Default Translation Driver
    |--------------------------------------------------------------------------
    |
    | The default translation driver to use when none is specified. You can
    | set this to any of the drivers defined in the 'drivers' array below.
    |
    */

    // Default translation driver. You can change this to 'google', 'deepl', or 'chatgpt'.
    // Note: API keys are configured below in the drivers section.
    'default_driver' => 'google',

    /*
    |--------------------------------------------------------------------------
    | Source Language Code
    |--------------------------------------------------------------------------
    |
    | The default source language code of your application. This will be used
    | as the source language for translations unless specified otherwise.
    |
    */

    // The base language of your app (texts you author by hand)
    'source_language' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Available Translation Drivers
    |--------------------------------------------------------------------------
    |
    | Configure as many translation drivers as you wish. Each driver should
    | have a unique name and its own configuration settings.
    |
    */

    'drivers' => [

        'chatgpt' => [
            'api_key' => null, // set in .env and copy here if you prefer: CHATGPT_API_KEY
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'http_timeout' => 60,
        ],

        'google' => [
            'api_key' => null, // set your Google Cloud Translation API key
        ],

        'deepl' => [
            'api_key' => null, // set your DeepL API key
            'api_url' => 'https://api-free.deepl.com/v2/translate',
        ],
        // Example custom driver registration (disabled by default):
        // 'my_custom_driver' => [
        //     'class' => \App\Drivers\MyCustomDriver::class,
        //     'api_key' => env('MY_CUSTOM_API_KEY'),
        //     // ...
        // ],
    ],
];
