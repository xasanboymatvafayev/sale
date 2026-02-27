<?php

// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat


class Fragment
{
    private string $apiKey;
    private string $phoneNumber;
    private array $mnemonics;
    private ?string $jwtToken = null;

    public function __construct(string $apiKey, string $phoneNumber, array $mnemonics)
    {
        $this->apiKey = $apiKey;
        $this->phoneNumber = $phoneNumber;
        $this->mnemonics = $mnemonics;
    }

    /**
     * Authenticate user and get JWT token
     */
    public function auth(): ?string
    {
        $url = 'https://api.fragment-api.com/v1/auth/authenticate/';
        $payload = [
            'api_key'      => $this->apiKey,
            'phone_number' => $this->phoneNumber,
            'mnemonics'    => $this->mnemonics
        ];

        $response = $this->curlRequest($url, "POST", $payload);

        if (!empty($response['jwt_token'])) {
            $this->jwtToken = $response['jwt_token'];
            return $this->jwtToken;
        }

        return null;
    }

    /**
     * Get wallet balance
     */
    public function walletBalance(): ?array
    {
        if (!$this->jwtToken) {
            throw new Exception("Auth required. Run auth() first.");
        }

        $url = 'https://api.fragment-api.com/v1/misc/wallet/';
        return $this->curlRequest($url, "GET", null, true);
    }

    /**
     * Buy Telegram Stars
     */
    public function buyStars(string $username, int $quantity, bool $showSender = false): ?array
    {
        if (!$this->jwtToken) {
            throw new Exception("Auth required. Run auth() first.");
        }

        $url = 'https://api.fragment-api.com/v1/order/stars/';
        $payload = [
            "username"    => $username,
            "quantity"    => $quantity,
            "show_sender" => $showSender
        ];

        return $this->curlRequest($url, "POST", $payload, true);
    }

    /**
     * Helper function for cURL requests
     */
    private function curlRequest(string $url, string $method, ?array $data = null, bool $auth = false): ?array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if ($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        if ($method === "GET") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        if ($auth && $this->jwtToken) {
            $headers[] = 'Authorization: JWT ' . $this->jwtToken;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        return json_decode($result, true);
    }
}

// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
