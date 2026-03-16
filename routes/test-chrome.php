<?php

// Add this to routes/web.php temporarily for testing Chrome installation

Route::get('/test-chrome-installation', function () {
    if (!auth()->check() || !auth()->user()->hasRole('admin')) {
        abort(403, 'Admin only');
    }
    
    $results = [
        'timestamp' => now()->toDateTimeString(),
        'server_os' => PHP_OS,
        'php_version' => PHP_VERSION,
    ];
    
    // Check system Chrome/Chromium
    $systemPaths = [
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/snap/bin/chromium',
        '/usr/bin/chrome',
        '/opt/google/chrome/chrome',
    ];
    
    $results['system_chrome'] = [];
    foreach ($systemPaths as $path) {
        if (is_file($path)) {
            $results['system_chrome'][$path] = [
                'exists' => true,
                'executable' => is_executable($path),
                'size' => filesize($path),
            ];
        }
    }
    
    // Check Puppeteer cache
    $cacheDirs = [
        getenv('PUPPETEER_CACHE_DIR'),
        '/root/.cache/puppeteer',
        '/var/www/.cache/puppeteer',
        base_path('node_modules/puppeteer/.local-chromium'),
    ];
    
    $results['puppeteer_cache'] = [];
    foreach (array_filter($cacheDirs) as $dir) {
        if (is_dir($dir)) {
            $results['puppeteer_cache'][$dir] = [
                'exists' => true,
                'readable' => is_readable($dir),
                'contents' => glob($dir . '/chrome/*/chrome-linux64/chrome'),
            ];
        }
    }
    
    // Check environment
    $results['environment'] = [
        'BROWSERSHOT_CHROME_PATH' => env('BROWSERSHOT_CHROME_PATH'),
        'PUPPETEER_CACHE_DIR' => getenv('PUPPETEER_CACHE_DIR'),
        'USER' => getenv('USER'),
        'HOME' => getenv('HOME'),
    ];
    
    // Test Browsershot
    try {
        $html = '<h1>Test PDF</h1><p>Generated at: ' . now()->toDateTimeString() . '</p>';
        
        $chromePath = env('BROWSERSHOT_CHROME_PATH');
        if (!$chromePath) {
            foreach ($systemPaths as $p) {
                if (is_file($p) && is_executable($p)) {
                    $chromePath = $p;
                    break;
                }
            }
        }
        
        if ($chromePath) {
            $browsershot = \Spatie\Browsershot\Browsershot::html($html)
                ->setChromePath($chromePath)
                ->noSandbox()
                ->setOption('args', ['--disable-web-security', '--disable-dev-shm-usage'])
                ->timeout(30);
            
            $pdf = $browsershot->pdf();
            
            $results['browsershot_test'] = [
                'success' => true,
                'chrome_path' => $chromePath,
                'pdf_size' => strlen($pdf),
                'message' => 'PDF generation successful!',
            ];
        } else {
            $results['browsershot_test'] = [
                'success' => false,
                'error' => 'No Chrome binary found',
            ];
        }
        
    } catch (\Exception $e) {
        $results['browsershot_test'] = [
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => substr($e->getTraceAsString(), 0, 500),
        ];
    }
    
    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
})->name('test.chrome');
