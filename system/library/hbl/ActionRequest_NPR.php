<?php

namespace hbl;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Checker\NotBeforeChecker;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128CBCHS256;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Encryption\JWETokenSupport;
use Jose\Component\Encryption\Serializer\CompactSerializer as JWECompactSerializer;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer as JWSCompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Jose\Easy\ContentEncryptionAlgorithmChecker;
use Psr\Http\Message\RequestInterface;

abstract class ActionRequest_NPR
{
    
    private const PaymentEndpoint = "https://core.paco.2c2p.com";
    // protected const PaymentEndpoint = "https://core.demo-paco.2c2p.com";

    protected Client $client;

    private JWSCompactSerializer $jwsCompactSerializer;
    private JWSBuilder $jwsBuilder;
    private JWSLoader $jwsLoader;
    private ClaimCheckerManager $claimCheckerManager;

    private JWECompactSerializer $jweCompactSerializer;
    private JWEBuilder $jweBuilder;
    private JWELoader $jweLoader;

    public function __construct()
    {
        $handler = HandlerStack::create();
        $handler->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withoutHeader('User-Agent');
        }));

        $this->client = new Client([
            'base_uri' => self::PaymentEndpoint,
            'handler' => $handler
        ]);

        $this->jwsCompactSerializer = new JWSCompactSerializer();

        $this->jwsBuilder = new JWSBuilder(
            new AlgorithmManager([new PS256()])
        );

        $this->jwsLoader = new JWSLoader(
            new \Jose\Component\Signature\Serializer\JWSSerializerManager([new JWSCompactSerializer()]),
            new JWSVerifier(new AlgorithmManager([new PS256()])),
            new HeaderCheckerManager(
                [new AlgorithmChecker([SecurityData_NPR::$JWSAlgorithm], true)],
                [new JWSTokenSupport()]
            )
        );

        $this->claimCheckerManager = new ClaimCheckerManager([
            new NotBeforeChecker(),
            new ExpirationTimeChecker(),
            new AudienceChecker(SecurityData_NPR::$AccessToken),
            new IssuerChecker(["PacoIssuer"]),
        ]);

        $this->jweCompactSerializer = new JWECompactSerializer();

        $this->jweBuilder = new JWEBuilder(
            new AlgorithmManager([new RSAOAEP()]),
            new AlgorithmManager([new A128CBCHS256()]),
            new CompressionMethodManager([])
        );

        $this->jweLoader = new JWELoader(
            new \Jose\Component\Encryption\Serializer\JWESerializerManager([new JWECompactSerializer()]),
            new JWEDecrypter(
                new AlgorithmManager([new RSAOAEP()]),
                new AlgorithmManager([new A128CBCHS256()]),
                new CompressionMethodManager([])
            ),
            new HeaderCheckerManager(
                [
                    new AlgorithmChecker([SecurityData_NPR::$JWEAlgorithm], true),
                    new ContentEncryptionAlgorithmChecker([SecurityData_NPR::$JWEEncryptionAlgorithm], true)
                ],
                [new JWETokenSupport()]
            )
        );
    }

    protected function GetPrivateKey(string $key, ?string $password = null, array $additional_values = []): JWK
    {
        // Check if the key is PKCS#8 format (contains the RSA encryption OID)
        if (strpos($key, 'BgkqhkiG9w0BAQEFA') !== false) {
            $privateKey = "-----BEGIN PRIVATE KEY-----\n" . $key . "\n-----END PRIVATE KEY-----";
        } else {
            $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" . $key . "\n-----END RSA PRIVATE KEY-----";
        }
        return JWKFactory::createFromKey($privateKey, $password, $additional_values);
    }

    protected function GetPublicKey(string $key, ?string $password = null, array $additional_values = []): JWK
    {
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" . $key . "\n-----END PUBLIC KEY-----";
        return JWKFactory::createFromKey($publicKey, $password, $additional_values);
    }

    protected function EncryptPayload(string $payload, JWK $signingKey, JWK $encryptingKey): string
    {
        $jws = $this->jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($signingKey, [
                "alg" => SecurityData_NPR::$JWSAlgorithm,
                "typ" => SecurityData_NPR::$TokenType,
            ])
            ->build();

        $jwe = $this->jweBuilder
            ->create()
            ->withPayload($this->jwsCompactSerializer->serialize($jws))
            ->withSharedProtectedHeader([
                "alg" => SecurityData_NPR::$JWEAlgorithm,
                "enc" => SecurityData_NPR::$JWEEncryptionAlgorithm,
                "kid" => SecurityData_NPR::$EncryptionKeyId,
                "typ" => SecurityData_NPR::$TokenType,
            ])
            ->addRecipient($encryptingKey)
            ->build();

        return $this->jweCompactSerializer->serialize($jwe, 0);
    }

    protected function DecryptToken(string $token, JWK $decryptingKey, JWK $signatureVerificationKey): string
    {
        $recipient = null;
        $jwe = $this->jweLoader->loadAndDecryptWithKey($token, $decryptingKey, $recipient);

        $signature = null;
        $jws = $this->jwsLoader->loadAndVerifyWithKey($jwe->getPayload(), $signatureVerificationKey, $signature);

        $token = $jws->getPayload();
        $claims = json_decode($token, true);
        $this->claimCheckerManager->check($claims);

        return $token;
    }

    public function DecryptResponse(string $token): string
    {
        $decryptingKey = $this->GetPrivateKey(SecurityData_NPR::$MerchantDecryptionPrivateKey);
        $signatureVerificationKey = $this->GetPublicKey(SecurityData_NPR::$PacoSigningPublicKey);
        return $this->DecryptToken($token, $decryptingKey, $signatureVerificationKey);
    }

    protected function Guid(): string
    {
        if (function_exists('com_create_guid')) {
            return trim(com_create_guid(), '{}');
        }

        $charId = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);

        return strtolower(
            substr($charId, 0, 8) . $hyphen .
            substr($charId, 8, 4) . $hyphen .
            substr($charId, 12, 4) . $hyphen .
            substr($charId, 16, 4) . $hyphen .
            substr($charId, 20, 12)
        );
    }
}