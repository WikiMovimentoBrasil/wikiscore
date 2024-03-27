<?php

// Get accepted languages from the 'translations' directory
$acceptedLanguages = array_diff(
    preg_replace('/\.json$/', '', scandir('translations')),
    ['..', '.', 'qqq']
);
$acceptedLanguages[] = "qqx";

// Get user language from the query parameters
$userLang = $_GET["lang"] ?? "";

// Check if user's language is in accepted languages, otherwise, try to determine from HTTP_ACCEPT_LANGUAGE
if (in_array($userLang, $acceptedLanguages)) {
    $lang = $userLang;
} elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    // Parse the HTTP_ACCEPT_LANGUAGE header
    preg_match_all(
        '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'],
        $langParse
    );

    // Create an associative array of languages and their corresponding quality values
    if (count($langParse[1])) {
        $languagesWithQuality = array_combine($langParse[1], $langParse[4]);

        // Set default quality value to 1 for languages without a specified value
        foreach ($languagesWithQuality as $language => $quality) {
            if ($quality === '') { 
                $languagesWithQuality[$language] = 1; 
            }
        }

        // Sort languages by quality in descending order
        arsort($languagesWithQuality, SORT_NUMERIC);
    }

    // Find the first accepted language from the sorted list
    foreach (array_keys($languagesWithQuality) as $acceptedLang) {
        $acceptedLang = strtolower($acceptedLang);
        if (in_array($acceptedLang, $acceptedLanguages)) {
            $lang = $acceptedLang;
            break;
        }
    }
}

// Set a default language if none is determined
if (!isset($lang)) {
    $lang = 'en';
}

// Load translation files
if ($lang != 'qqx') {
    $translationFile = './translations/' . $lang . '.json';
    $trans = file_exists($translationFile) ? json_decode(file_get_contents($translationFile), true) : [];
    $original = json_decode(file_get_contents('./translations/en.json'), true);
}

// Main function
function §($item, ...$args) {
    global $trans;
    global $original;
    global $lang;

    if ($lang == 'qqx') {
        return $item;
    }
    $translatedString = $trans[$item] ?? $original[$item] ?? "<i>$item</i>";

    // Replace placeholders ($1, $2, $3, etc.) with corresponding arguments
    for ($i = 1; $i <= count($args); $i++) {
        $placeholder = '$' . $i;
        $translatedString = str_replace($placeholder, $args[$i - 1], $translatedString);
    }

    return $translatedString;
}