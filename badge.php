<?php

// API params
$params = [
    "action"            => "query",
    "format"            => "php",
    "meta"              => "messagegroupstats",
    "formatversion"     => "2",
    "mgsgroup"          => "wikiscore",
    "mgssuppressempty"  => "1",
];

// Decode API data
$data = file_get_contents("https://translatewiki.net/w/api.php?" . http_build_query($params));
$data = unserialize($data);

// Count the number of elements in "messagegroupstats"
$numElements = count($data['query']['messagegroupstats']);

// Extract "translated" values and calculate sum
$translatedValues = array_column($data['query']['messagegroupstats'], 'translated');
$sumTranslated = array_sum($translatedValues);

// Extract the first "total" value
$firstTotal = $data['query']['messagegroupstats'][0]['total'];

// Calculate percentage
$percentage = round( ( $sumTranslated / ( $firstTotal * $numElements ) ) * 100 );

// Pick color
switch (true) {
    case $percentage == 100:
        $color = 'green';
        break;

    case $percentage > 90:
        $color = 'blue';
        break;

    case $percentage > 75:
        $color = 'orange';
        break;
    
    default:
        $color = 'red';
        break;
}

// Prepare result array
$result = [
    'label'     => 'translated',
    'message'   => $percentage . '%',
    'color'     => $color,
];

// Convert result to JSON and output
echo json_encode($result, JSON_PRETTY_PRINT);

?>
