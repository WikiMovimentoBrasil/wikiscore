<?php

//Carrega traduções
$acceptedLanguages = str_replace('.json', '', array_diff(scandir('translations'), array('..', '.')));
foreach (array_keys($acceptedLanguages, 'qqq', true) as $acptCode) unset($acceptedLanguages[$acptCode]);
$userLang = $_GET["lang"] ?? "";
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
if (in_array($userLang, $acceptedLanguages)) {
    $lang = $userLang;
} elseif (in_array($browserLang, $acceptedLanguages)) {
    $lang = $browserLang;
} else {
    $lang = 'en';
}

$translationFile = './translations/' . $lang . '.json';
$trans = file_exists($translationFile) ? json_decode(file_get_contents($translationFile), true) : [];
$orig = json_decode(file_get_contents('./translations/en.json'), true);

//Função para exibição de traduções
function §($item, ...$args) {
    global $trans;
    global $orig;

    $translatedString = $trans[$item] ?? $orig[$item] ?? "<i>$item</i>";

    // Replace placeholders ($1, $2, $3, etc.) with corresponding arguments
    for ($i = 1; $i <= count($args); $i++) {
        $placeholder = '$' . $i;
        $translatedString = str_replace($placeholder, $args[$i - 1], $translatedString);
    }

    return $translatedString;
}