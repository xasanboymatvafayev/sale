<?php

// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat

$username = isset($_GET['username']) ? trim($_GET['username']) : null;
$months   = isset($_GET['months']) ? (int)$_GET['months'] : null;
$showSender = isset($_GET['show_sender']) ? filter_var($_GET['show_sender'], FILTER_VALIDATE_BOOL) : false;

if (!$username || !$months) {
    http_response_code(400);
    echo json_encode(["error" => "username va months parametrlari majburiy"]);
    exit;
}

$username = ltrim($username, '@');

if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $username)) {
    http_response_code(400);
    echo json_encode(["error" => "username formati noto‘g‘ri"]);
    exit;
}

$validMonths = [3, 6, 12];
if (!in_array($months, $validMonths)) {
    http_response_code(400);
    echo json_encode(["error" => "months faqat 3, 6 yoki 12 bo‘lishi kerak"]);
    exit;
}

$jwtToken = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxMjMsInVzZXJuYW1lIjoiYWxpIiwicm9sZSI6InVzZXIiLCJpYXQiOiIyMDI0LTAxLTAxVDEwOjAwOjAwWiIsImV4cCI6IjIwMjQtMDEtMDJUMTA6MDA6MDBaIn0.signature";

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.fragment-api.com/v1/order/premium/",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode([
    'username'    => $username,
    'months'      => $months,
    'show_sender' => $showSender
  ]),
  CURLOPT_HTTPHEADER => [
    "Accept: application/json",
    "Authorization: JWT " . $jwtToken, 
    "Content-Type: application/json"
  ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  header("Content-Type: application/json; charset=utf-8");
  echo $response;
}

// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat

?>