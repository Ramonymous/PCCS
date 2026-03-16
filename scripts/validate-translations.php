#!/usr/bin/env php
<?php
/**
 * Translation Validator
 * 
 * This script checks that all translations exist in both languages
 * and identifies missing translations.
 * 
 * Usage: php scripts/validate-translations.php
 */

$basePath = __DIR__ . '/..';
$enFile = $basePath . '/resources/lang/en.json';
$idFile = $basePath . '/resources/lang/id.json';

echo "🔍 Validating Translations...\n\n";

// Load translation files
if (!file_exists($enFile)) {
    echo "❌ English translation file not found: $enFile\n";
    exit(1);
}

if (!file_exists($idFile)) {
    echo "❌ Indonesian translation file not found: $idFile\n";
    exit(1);
}

$enTranslations = json_decode(file_get_contents($enFile), true);
$idTranslations = json_decode(file_get_contents($idFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ JSON Error: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "✅ Loaded " . count($enTranslations) . " English translations\n";
echo "✅ Loaded " . count($idTranslations) . " Indonesian translations\n\n";

// Find missing translations
$missingInEnglish = array_diff_key($idTranslations, $enTranslations);
$missingInIndonesian = array_diff_key($enTranslations, $idTranslations);

// Report
if (empty($missingInEnglish) && empty($missingInIndonesian)) {
    echo "✨ Perfect! All translations are in sync.\n\n";
} else {
    if (!empty($missingInEnglish)) {
        echo "⚠️  Missing in English (en.json):\n";
        foreach (array_keys($missingInEnglish) as $key) {
            echo "   - \"$key\"\n";
        }
        echo "\n";
    }
    
    if (!empty($missingInIndonesian)) {
        echo "⚠️  Missing in Indonesian (id.json):\n";
        foreach (array_keys($missingInIndonesian) as $key) {
            echo "   - \"$key\"\n";
        }
        echo "\n";
    }
}

// Check for untranslated (same as key)
$untranslated = [];
foreach ($idTranslations as $key => $value) {
    if (isset($enTranslations[$key]) && $enTranslations[$key] === $value) {
        $untranslated[] = $key;
    }
}

if (!empty($untranslated)) {
    echo "⚠️  Potentially untranslated (same in both languages):\n";
    foreach ($untranslated as $key) {
        echo "   - \"$key\" = \"{$enTranslations[$key]}\"\n";
    }
    echo "\n";
}

// Summary
echo "📊 Summary:\n";
echo "   Total keys: " . count(array_unique(array_merge(array_keys($enTranslations), array_keys($idTranslations)))) . "\n";
echo "   English: " . count($enTranslations) . "\n";
echo "   Indonesian: " . count($idTranslations) . "\n";
echo "   Missing in EN: " . count($missingInEnglish) . "\n";
echo "   Missing in ID: " . count($missingInIndonesian) . "\n";
echo "   Potentially untranslated: " . count($untranslated) . "\n";

if (empty($missingInEnglish) && empty($missingInIndonesian)) {
    echo "\n✅ Validation passed!\n";
    exit(0);
} else {
    echo "\n⚠️  Please add missing translations.\n";
    exit(1);
}
