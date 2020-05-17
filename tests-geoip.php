<?php
/**
 * A sef of simple tests for GeoipTiny Resolver
 *
 * Create sample database: all world with en,de,ru locales:
 * php geosqlfactory.php --language=en,de,ru --db=world-ip-test.sqlite
 *
 * Test arrays lurked from https://lite.ip2location.com/ip-address-ranges-by-country
 */

// include tool
include_once 'geoiptiny.php';

// setup with sqlite - the db must be exists!
$ipResolver = new \vikseriq\GeoipSqlite\GeoipTiny('world-ip-test.sqlite');

// test arrays
$testMap = [
    'countryCodes' => [
        '77.88.8.8' => 'RU',
        '199.16.173.181' => 'US',
        '202.171.152.236' => 'JP',
        '203.159.70.33' => 'TH',
        '31.128.127.1' => 'UA',
        '127.0.0.1' => null,
    ],
    'locales' => [
        'ru' => [
            '77.88.8.8' => 'Россия',
        ],
        'en' => [
            '37.151.100.1' => 'Kazakhstan'
        ],
        'de' => [
            '37.151.100.1' => 'Kasachstan',
            '31.128.127.1' => 'Ukraine',
        ]
    ]
];

// country detection
foreach ($testMap['countryCodes'] as $targetIp => $correctCountry) {
    $result = $ipResolver->getCountry($targetIp);
    if ($correctCountry === $result) {
        echo "PASS\t" . $result . "\n";
    } else {
        echo "FAIL\t" . $targetIp . " resolved as " . $result . "\n";
    }
}

// localization test
foreach ($testMap['locales'] as $locale => $lines) {
    foreach ($lines as $targetIp => $name) {
        $resultRow = $ipResolver->resolve($targetIp, $locale);
        $result = $resultRow['country_name'];
        if ($result === $name) {
            echo "PASS\t" . $result . "\n";
        } else {
            echo "FAIL\t" . $targetIp . " resolved as " . $result . "\n";
        }
    }
}