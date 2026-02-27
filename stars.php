<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat

header("Content-Type: application/json; charset=utf-8");

$jwtToken = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxMjMsInVzZXJuYW1lIjoiYWxpIiwicm9sZSI6InVzZXIiLCJpYXQiOiIyMDI0LTAxLTAxVDEwOjAwOjAwWiIsImV4cCI6IjIwMjQtMDEtMDJUMTA6MDA6MDBaIn0.signature"; //JWT tokenni qoying

$username  = isset($_GET['username']) ? trim($_GET['username']) : null;
$starsSoni = isset($_GET['starssoni']) ? (int)$_GET['starssoni'] : null;

if (!$username || !$starsSoni) {
    http_response_code(400); 
    echo json_encode(["error" => "username va starssoni parametrlari majburiy"]);
    exit;
}

$username = ltrim($username, '@');

$url = "https://api.fragment-api.com/v1/order/stars/";

$payload = [
    "username"    => $username,
    "quantity"    => $starsSoni,
    "show_sender" => false
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: application/json",
    "Content-Type: application/json",
    "Authorization: JWT " . $jwtToken
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(["error" => "CURL Error: " . curl_error($ch)]);
    exit;
}
curl_close($ch);

$result = json_decode($response, true);

if ($result === null) {
    http_response_code(500);
    echo json_encode(["error" => "API'dan noto‘g‘ri javob keldi", "raw" => $response]);
} else {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat

?>