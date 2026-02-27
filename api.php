<?php

$apiKey = "878f989d-8523-469b-8fe6-cff253ad6d7b";
$phoneNumber = "998771799353"; // + belgisisiz!
$mnemonics = [
    "null","null","null","null","null","null","null","null",
    "null","null","null","null","null","null","null","null",
    "null","null","null","null","null","null","null","null"
];

$url = "https://api.fragment-api.com/v1/auth/authenticate/";

$payload = [
    "api_key"      => $apiKey,
    "phone_number" => $phoneNumber,
    "mnemonics"    => $mnemonics
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "CURL Error: " . curl_error($ch);
}

curl_close($ch);

echo "API Response: \n" . $response . "\n";
