<?php

namespace hbl\api;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use hbl\ActionRequest_NPR;
use hbl\SecurityData_NPR;

class Inquiry extends ActionRequest_NPR
{
    public function Execute(string $officeId, string $orderNo): string
    {
        $now = Carbon::now();

        $request = [
            "apiRequest" => [
                "requestMessageID" => $this->Guid(),
                "requestDateTime" => $now->utc()->format('Y-m-d\TH:i:s.v\Z'),
                "language" => "en-US"
            ],
            "advSearchParams" => [
                "controllerInternalID" => null,
                "officeId" => [$officeId],
                "orderNo" => [$orderNo],
                "invoiceNo2C2P" => null,
                "fromDate" => "0001-01-01T00:00:00",
                "toDate" => "0001-01-01T00:00:00",
                "amountFrom" => null,
                "amountTo" => null
            ],
        ];

        $stringRequest = json_encode($request);

        $response = $this->client->post('/api/1.0/Inquiry/transactionList', [
            'headers' => [
                'Accept' => 'application/json',
                'apiKey' => SecurityData_NPR::$AccessToken,
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'body' => $stringRequest
        ]);

        return $response->getBody()->getContents();
    }

    public function ExecuteJose(string $officeId, string $orderNo): string
    {
        $now = Carbon::now();

        $request = [
            "apiRequest" => [
                "requestMessageID" => $this->Guid(),
                "requestDateTime" => $now->utc()->format('Y-m-d\TH:i:s.v\Z'),
                "language" => "en-US"
            ],
            "advSearchParams" => [
                "controllerInternalID" => null,
                "officeId" => [$officeId],
                "orderNo" => [$orderNo],
                "invoiceNo2C2P" => null,
                "fromDate" => "0001-01-01T00:00:00",
                "toDate" => "0001-01-01T00:00:00",
                "amountFrom" => null,
                "amountTo" => null
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

        $response = $this->client->post('/api/1.0/Inquiry/transactionList', [
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
