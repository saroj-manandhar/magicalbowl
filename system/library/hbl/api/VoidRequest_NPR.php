<?php

namespace hbl\api;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use hbl\ActionRequest_NPR;
use hbl\SecurityData_NPR;

/**
 * Class VoidRequest
 *
 * Handles Void API requests (Normal + JOSE encrypted)
 */
class VoidRequest extends ActionRequest_NPR
{
    /**
     * Execute normal JSON Void request
     *
     * @param string $officeId
     * @param string $orderNo
     * @param string $productDescription
     * @param float $amount
     * @param string $currency
     * @param string $approvalCode
     * @param string $actionBy
     * @return string
     * @throws GuzzleException
     */
    public function Execute(
        string $officeId,
        string $orderNo,
        string $productDescription,
        float $amount,
        string $currency = 'THB',
        string $approvalCode = '140331',
        string $actionBy = 'System'
    ): string {
        $request = [
            "officeId" => $officeId,
            "orderNo" => $orderNo,
            "productDescription" => $productDescription,
            "issuerApprovalCode" => $approvalCode,
            "actionBy" => $actionBy,
            "voidAmount" => [
                "amountText" => str_pad($amount * 100, 12, "0", STR_PAD_LEFT),
                "currencyCode" => $currency,
                "decimalPlaces" => 2,
                "amount" => $amount
            ],
        ];

        $stringRequest = json_encode($request);

        $response = $this->client->post('api/1.0/Void', [
            'headers' => [
                'Accept' => 'application/json',
                'apiKey' => SecurityData_NPR::$AccessToken,
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'body' => $stringRequest
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * Execute JOSE (JWS + JWE) encrypted Void request
     *
     * @param string $officeId
     * @param string $orderNo
     * @param string $productDescription
     * @param float $amount
     * @param string $currency
     * @param string $approvalCode
     * @param string $actionBy
     * @return string
     * @throws GuzzleException
     * @throws \Exception
     */
    public function ExecuteJose(
        string $officeId,
        string $orderNo,
        string $productDescription,
        float $amount,
        string $currency = 'THB',
        string $approvalCode = '140331',
        string $actionBy = 'System'
    ): string {
        $now = Carbon::now();

        $request = [
            "officeId" => $officeId,
            "orderNo" => $orderNo,
            "productDescription" => $productDescription,
            "issuerApprovalCode" => $approvalCode,
            "actionBy" => $actionBy,
            "voidAmount" => [
                "amountText" => str_pad($amount * 100, 12, "0", STR_PAD_LEFT),
                "currencyCode" => $currency,
                "decimalPlaces" => 2,
                "amount" => $amount
            ],
        ];

        $payload = [
            "request" => $request,
            "iss" => SecurityData_NPR::$AccessToken,
            "aud" => "PacoAudience",
            "CompanyApiKey" => SecurityData_NPR::$AccessToken,
            "iat" => $now->unix(),
            "nbf" => $now->unix(),
            "exp" => $now->copy()->addHour()->unix()
        ];

        $stringPayload = json_encode($payload);

        $signingKey = $this->GetPrivateKey(SecurityData_NPR::$MerchantSigningPrivateKey);
        $encryptingKey = $this->GetPublicKey(SecurityData_NPR::$PacoEncryptionPublicKey);

        $body = $this->EncryptPayload($stringPayload, $signingKey, $encryptingKey);

        $response = $this->client->post('/api/1.0/Void', [
            'headers' => [
                'Accept' => 'application/jose',
                'CompanyApiKey' => SecurityData_NPR::$AccessToken,
                'Content-Type' => 'application/jose; charset=utf-8'
            ],
            'body' => $body
        ]);

        $token = $response->getBody()->getContents();

        $decryptingKey = $this->GetPrivateKey(SecurityData_NPR::$MerchantDecryptionPrivateKey);
        $signatureVerificationKey = $this->GetPublicKey(SecurityData_NPR::$PacoSigningPublicKey);

        return $this->DecryptToken($token, $decryptingKey, $signatureVerificationKey);
    }
}
