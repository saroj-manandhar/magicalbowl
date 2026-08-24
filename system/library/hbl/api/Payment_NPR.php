<?php

namespace hbl\api;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use hbl\ActionRequest_NPR;
use hbl\SecurityData_NPR;

class Payment extends ActionRequest_NPR
{
    public function ExecuteFormJose(
        string $mid,
        string $api_key,
        string $curr,
        float $amt,
        bool $threeD,
        string $success_url,
        string $failed_url,
        string $cancel_url,
        string $backend_url
    ): string {
        $now = Carbon::now('UTC');
        $orderNo = (string)$now->getPreciseTimestamp(3);

        // Format amount: multiply by 100 and pad to 12 digits
        $amountText = str_pad((string)(($amt == null ? 0 : $amt) * 100), 12, "0", STR_PAD_LEFT);

        // Build request structure matching the working example
        $request = [
            "apiRequest" => [
                "requestMessageID" => $this->Guid(),
                "requestDateTime" => $now->format('Y-m-d\TH:i:s.v\Z'),
                "language" => "en-US",
            ],
            "officeId" => $mid,
            "orderNo" => $orderNo,
            "productDescription" => "Order " . $orderNo,
            "paymentType" => "CC",
            "paymentCategory" => "ECOM",
            "storeCardDetails" => [
                "storeCardFlag" => "N",
                "storedCardUniqueID" => $this->Guid()
            ],
            "installmentPaymentDetails" => [
                "ippFlag" => "N",
                "installmentPeriod" => 0,
                "interestType" => null
            ],
            "mcpFlag" => "N",
            "request3dsFlag" => $threeD ? "Y" : "N",
            "transactionAmount" => [
                "amountText" => $amountText,
                "currencyCode" => $curr,
                "decimalPlaces" => 2,
                "amount" => $amt
            ],
            "notificationURLs" => [
                "confirmationURL" => $success_url,
                "failedURL" => $failed_url,
                "cancellationURL" => $cancel_url,
                "backendURL" => $backend_url
            ],
            "deviceDetails" => [
                "browserIp" => $_SERVER['REMOTE_ADDR'] ?? "127.0.0.1",
                "browser" => "OpenCart",
                "browserUserAgent" => $_SERVER['HTTP_USER_AGENT'] ?? "Mozilla/5.0",
                "mobileDeviceFlag" => "N"
            ]
        ];

        // JWT timestamps must be integers
        $iat = $now->unix();
        $nbf = $now->unix();
        $exp = $now->copy()->addHour()->unix();

        // Payload structure - ONLY these fields, matching the working example
        $payload = [
            "request" => $request,
            "iss" => $api_key,
            "aud" => "PacoAudience",
            "CompanyApiKey" => $api_key,
            "iat" => $iat,
            "nbf" => $nbf,
            "exp" => $exp,
        ];

        $stringPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        
        // Log the payload details dynamically based on config paths
        if (defined('DIR_LOGS')) {
            $log_dir = DIR_LOGS;
        } elseif (defined('DIR_STORAGE')) {
            $log_dir = DIR_STORAGE . 'logs/';
        } else {
            $log_dir = '';
        }

        if ($log_dir && is_dir($log_dir) && is_writable($log_dir)) {
            file_put_contents($log_dir . 'hbl_payload.json', $stringPayload);
        }

        $signingKey = $this->GetPrivateKey(SecurityData_NPR::$MerchantSigningPrivateKey);
        $encryptingKey = $this->GetPublicKey(SecurityData_NPR::$PacoEncryptionPublicKey);

        $body = $this->EncryptPayload($stringPayload, $signingKey, $encryptingKey);

        // // Log the request details for debugging
        // $log = new \Log('error.log');
        // $log->write('HBL API Request URL: https://core.demo-paco.2c2p.com/api/1.0/Payment/prePaymentUi');
        // $log->write('HBL API Headers: ' . json_encode([
        //     'Accept' => 'application/jose',
        //     'CompanyApiKey' => SecurityData_NPR::$AccessToken,
        //     'Content-Type' => 'application/jose; charset=utf-8'
        // ]));
        // $log->write('HBL API Body Length: ' . strlen($body));

        // Call the correct endpoint: prePaymentUi (not NonUi)
        $response = $this->client->post('api/1.0/Payment/prePaymentUi', [
            'headers' => [
                'Accept' => 'application/jose',
                'CompanyApiKey' => $api_key,
                'Content-Type' => 'application/jose; charset=utf-8'
            ],
            'body' => $body,
            'http_errors' => false
        ]);

        $statusCode = $response->getStatusCode();
        $token = (string)$response->getBody();

        // Handle errors
        if ($statusCode !== 200 && $statusCode !== 201) {
            $decryptingKey = $this->GetPrivateKey(SecurityData_NPR::$MerchantDecryptionPrivateKey);
            $signatureVerificationKey = $this->GetPublicKey(SecurityData_NPR::$PacoSigningPublicKey);
            
            try {
                $errorDetails = $this->DecryptToken($token, $decryptingKey, $signatureVerificationKey);
                throw new \Exception("Payment Gateway Error (Status: $statusCode): " . $errorDetails);
            } catch (\Exception $e) {
                // If we can't decrypt, show raw error
                throw new \Exception("Payment Gateway Error (Status: $statusCode): " . $e->getMessage() . " | Raw: " . substr($token, 0, 500));
            }
        }

        // Decrypt successful response
        $decryptingKey = $this->GetPrivateKey(SecurityData_NPR::$MerchantDecryptionPrivateKey);
        $signatureVerificationKey = $this->GetPublicKey(SecurityData_NPR::$PacoSigningPublicKey);

        return $this->DecryptToken($token, $decryptingKey, $signatureVerificationKey);
    }
}