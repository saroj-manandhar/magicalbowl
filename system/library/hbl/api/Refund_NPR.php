<?php

namespace hbl\api;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use hbl\ActionRequest_NPR;
use hbl\SecurityData_NPR;

/**
 * Class Refund
 *
 * Handles Refund API requests (Normal + JOSE encrypted)
 */
class Refund extends ActionRequest_NPR
{
    /**
     * Execute normal JSON Refund request
     *
     * @param string $officeId
     * @param string $orderNo
     * @param float $amount
     * @param string $currency
     * @param string $actionBy
     * @param string $actionEmail
     * @return string
     * @throws GuzzleException
     */
    public function Execute(
        string $officeId,
        string $orderNo,
        float $amount,
        string $currency = 'THB',
        string $actionBy = 'System|c88ef0dc-14ea-4556-922b-7f62a6a3ec9e',
        string $actionEmail = 'babulal.cho@2c2pexternal.com'
    ): string {
        $request = [
            "refundAmount" => [
                "AmountText"   => str_pad($amount * 100, 12, "0", STR_PAD_LEFT),
                "CurrencyCode" => $currency,
                "DecimalPlaces"=> 2,
                "Amount"       => $amount
            ],
            "refundItems" => [],
            "localMakerChecker" => [
                "maker" => [
                    "username" => $actionBy,
                    "email"    => $actionEmail
                ]
            ],
            "officeId" => $officeId,
            "orderNo"  => $orderNo,
        ];

        $stringRequest = json_encode($request);

        $response = $this->client->post('api/1.0/Refund/refund', [
            'headers' => [
                'Accept'       => 'application/json',
                'apiKey'       => SecurityData_NPR::$AccessToken,
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'body' => $stringRequest
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * Execute JOSE (JWS + JWE) encrypted Refund request
     *
     * @param string $officeId
     * @param string $orderNo
     * @param float $amount
     * @param string $currency
     * @param string $actionBy
     * @param string $actionEmail
     * @return string
     * @throws GuzzleException
     * @throws \Exception
     */
    public function ExecuteJose(
        string $officeId,
        string $orderNo,
        float $amount,
        string $currency = 'THB',
        string $actionBy = 'System|c88ef0dc-14ea-4556-922b-7f62a6a3ec9e',
        string $actionEmail = 'babulal.cho@2c2pexternal.com'
    ): string {
        $now = Carbon::now();

        $request = [
            "refundAmount" => [
                "AmountText"   => str_pad($amount * 100, 12, "0", STR_PAD_LEFT),
                "CurrencyCode" => $currency,
                "DecimalPlaces"=> 2,
                "Amount"       => $amount
            ],
            "refundItems" => [],
            "localMakerChecker" => [
                "maker" => [
                    "username" => $actionBy,
                    "email"    => $actionEmail
                ]
            ],
            "officeId" => $officeId,
            "orderNo"  => $orderNo,
        ];

        $payload = [
            "request"       => $request,
            "iss"           => SecurityData_NPR::$AccessToken,
            "aud"           => "PacoAudience",
            "CompanyApiKey" => SecurityData_NPR::$AccessToken,
            "iat"           => $now->unix(),
            "nbf"           => $now->unix(),
            "exp"           => $now->copy()->addHour()->unix()
        ];

        $stringPayload   = json_encode($payload);
        $signingKey      = $this->GetPrivateKey(SecurityData_NPR::$MerchantSigningPrivateKey);
        $encryptingKey   = $this->GetPublicKey(SecurityData_NPR::$PacoEncryptionPublicKey);

        $body = $this->EncryptPayload($stringPayload, $signingKey, $encryptingKey);

        $response = $this->client->post('api/1.0/Refund/refund', [
            'headers' => [
                'Accept'        => 'application/jose',
                'CompanyApiKey' => SecurityData_NPR::$AccessToken,
                'Content-Type'  => 'application/jose; charset=utf-8'
            ],
            'body' => $body
        ]);

        $token = $response->getBody()->getContents();

        $decryptingKey           = $this->GetPrivateKey(SecurityData_NPR::$MerchantDecryptionPrivateKey);
        $signatureVerificationKey = $this->GetPublicKey(SecurityData_NPR::$PacoSigningPublicKey);

        return $this->DecryptToken($token, $decryptingKey, $signatureVerificationKey);
    }
}
