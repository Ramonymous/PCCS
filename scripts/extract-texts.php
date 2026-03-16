<?php
/**
 * Text Extraction Helper
 * 
 * This script helps identify hardcoded text in Blade views that should be translated.
 * Run it to get a list of potential texts that need translation.
 * 
 * Usage: php scripts/extract-texts.php
 */

$viewsPath = __DIR__ . '/../resources/views';
$texts = [];

function extractTexts($file) {
    $content = file_get_contents($file);
    
    // Find title attributes in x-menu-item, x-button, etc
    preg_match_all('/title=["\']([^"\']+)["\']/', $content, $matches);
    foreach ($matches[1] as $text) {
        if (!preg_match('/^(__|route|{{)/', $text)) {
            $texts[$text] = $text;
        }
    }
    
    // Find text between tags
    preg_match_all('/>([A-Z][a-zA-Z\s]+)</u', $content, $matches);
    foreach ($matches[1] as $text) {
        $text = trim($text);
        if (strlen($text) > 2 && !preg_match('/^(__|route|{{|\$)/', $text)) {
            $texts[$text] = $text;
        }
    }
    
    return $texts;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsPath)
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $foundTexts = extractTexts($file->getPathname());
        $texts = array_merge($texts, $foundTexts);
    }
}

// Output as JSON for easy copying
echo "Texts found that might need translation:\n\n";
echo json_encode(array_values(array_unique($texts)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\nTotal: " . count($texts) . " texts\n";
