<?php

namespace hbl;

/**
 * Class SecurityData_NPR
 *
 * Stores configuration and security data for HBL/Paco payment integration.
 */
class SecurityData_NPR
{
    public static string $MerchantId = "9104338611";
    /**
     * JWE Key Id.
     *
     * @var string
     */
    public static string $EncryptionKeyId = "19f84b5655f04e25a99b09f1ee2fac78";

    /**
     * Access Token.
     *
     * @var string
		
     */
    
    public static string $AccessToken = "a5e4ff10f45e48ed8a24773476dbdc0c";



    /**
     * Token Type - Used in JWS and JWE header.
     *
     * @var string
     */
    public static string $TokenType = "JWT";

    /**
     * JWS (JSON Web Signature) Signature Algorithm - This parameter identifies the cryptographic algorithm used to
     * secure the JWS.
     *
     * @var string
     */
    public static string $JWSAlgorithm = "PS256";

    /**
     * JWE (JSON Web Encryption) Key Encryption Algorithm - This parameter identifies the cryptographic algorithm
     * used to secure the JWE.
     *
     * @var string
     */
    public static string $JWEAlgorithm = "RSA-OAEP";

    /**
     * JWE (JSON Web Encryption) Content Encryption Algorithm - This parameter identifies the content encryption
     * algorithm used on the plaintext to produce the encrypted ciphertext.
     *
     * @var string
     */
    public static string $JWEEncryptionAlgorithm = "A128CBC-HS256";

    /**
     * Merchant Signing Private Key is used to cryptographically sign and create the request JWS.
     *
     * @var string
     */
    public static string $MerchantSigningPrivateKey = "MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC672YDrFIdHCNX
Jp9oA912aKj/4bdYkxaRtdnWgfTlLneb11qCiSizYtPVv6UYUZYymTXmPODriXEB
yO0oNv05Wa98jZ17pc4NQPD9QtNjMtwmpCRzv4UUjMyE87xQcOC8YoEA/EAsxd1N
n2s+1iIXueBDR+tO1xQrcZDvlS2fA84kIRFbZneSVtwZTYwu7hlN3PQ0krPN01bL
uaYBxC7F4KSHy/W7QhlNmkwZN1VFS/BNnn1ps1QUqtVTysEqt/hr0wcz+vT1gcMI
SKWjSlBYI+6IzF3VSpEmjI4OQi0ppJa4/rc5DAzXGl95oReAzZQs78QoRJoF/rCl
3zJEDS3ZAgMBAAECggEAILNtVJop+Sapdf7vJsp6TNLtMWoCYU/FxHKb617rgMX6
rXvkPO6SfKL+rKcsUc8/55UOrTqcHAf8iVPlTMIl1Qj/3lmFoZI1M/NW1O8CPJmy
kl3ndIod1ST2SBG9MRM19S6EI7B853griP8oyyK5bw4YkZx8qNuODzV8JbUieGzs
8Owu5Pdqqg04f6SGEugOv4joy7yb1N6EsdgdFIFie4ofQBY+6Z9BOKI3w0G4C7ff
tY2txW6lYTKzrwKqAL462mNYOc6sI2QB34i6YvdUWKUXFOHdgavMzv8NFLferV+C
eW83h68VWicdkU8DvrWuvgXmFU7/BTcVZnFsYusKaQKBgQDdzm+TFDzdW3sIcjxm
ZXeodPQ9muem3dNzcknlCoBvCzZIBv/ljAMQEKjR4JMJY61Rm/2OPvYHhGlZXlc4
6JWjuZtkCgpOoZc6p73OAguoh2h6oZOYEqEcmk8JW9XlpMn2RQcrE3KKMnj+UN52
UI4UiMyV9MIhADkiGNPVJ6hVfQKBgQDXwMXq/5mHETO6Folu872/YQ+9ByobBzFC
Aiov3TqYD9zMQlRgINFEKzyymsU/mfFXnqacJGwF1lu/J9Sqpfcnbldr2XoUZrE3
p2o1hbfr9I/TkI+dlpkwPtkte160hxJX0L43ay1BMG1EnL1Kp82sa0KhLxNOwjjD
zpM7sqn4jQKBgQDWawudCQFVk2u6XIRbEFe+N2EsdKTfwKz6e09H5QEHV7Vfp7SU
uOb1DsXELe62Mu+HZt6UNfUsiyo0RGjZEK4nmfPHn5UbMka5YxKvJXcTseKkObIu
XkP1HI6vI2IBH25FbbFiSOh/BA/G+XI0uea/nwb3J6bKtCaG+a0975phGQKBgQCa
8D0xvcyrSpczE+wuWavyO+npfbOnJUsidBuHs//YI8wUg63EOs0Nf19fg/YS7qJ6
odxUVXOd3YqVhC0dP4J2Nq9hLBSXggfSR8/mD9k9Aawn6rC4IuZv1zJvjyE706RA
nA9+DOG65uQRWd42NrtlDsISrpPXA3NwanEhdfTKIQKBgBqT8E9AQgDb/O9/FWtp
K47dmRdhgcisc5cyWfQ20TAfn0ooz4DH0Vzb5CXRMmZYmrv1wSwqXxAuiNIzXk6m
MraXoyUT3Y4SZuCIqEDy+XFpXMgjJWXW7mGnf6jaKCgFD5AUD0LJzGC9wmuyeRJ/
xoOAH/JC5GtOVcz70Tt1Ne4F";

    /**
     * PACO Encryption Public Key is used to cryptographically encrypt and create the request JWE.
     *
     * @var string
     */
    public static string $PacoEncryptionPublicKey = "MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA6ZLups2K0iYEMxQqgASX8gY6tWhNVCp08YuDgjCsOVrGVgUHD0dh0TWFNJ7Lq2Jp0SOsGgi54+hrjwPOL2CCZxw8pKUlL57UksoD9oWUrK/KkSvEAwPU4cZqzxIXyhBcZb8O96iN4WQJILkRTg+DXLkML6qisO496fPGIs+vCoc87toucy5O9fRfaYSjcqjreyi8JDkvVJM/BeNtOEM2a0b/lcWa67RH+tN97H25k+Qez7QthLru6oBfWBgD6iIwhV+ICqLWHmp6fQ+DHQk/o+OO3yFiY9OAvMiy8MOTinvkBlFwYgYNznG3/w0Xh8U5vtudUXPDNUO6ddf4y99+6LlWDiKgJn/Th93YUg+gFH4LUJHyPrSY2JuC+Q8kksp2xyiZDTHGzi96kturwrqCui6TytCHcU4UB0VRMR+M7VRl3S2YPhcxv5U8Fh2PITqydZE5vv1Va06qhegjOlSZnEUl2xKPm5k/u+UHvUP/oq04fQLTlYqyA3JYDCe4z5Ea2SOgjeVl+qTatWYzmkUXyCONLZ4UaRrgbYCp0nCPHoTFgRQdChu8ezDbnYY9IW7cT/s2fEi5N7X1XrQttiEP4rbn0y0qVYYjN86+elfhtYGHidZTUSUS5RSTHqOkj59p5LIGwFF9iTXzCjfUqq8clnfOk76qSLY1+Kj+SMMe6Z8CAwEAAQ==";

    /**
     * PACO Signing Public Key is used to cryptographically verify the response JWS signature.
     *
     * @var string
     */
    public static string $PacoSigningPublicKey = "MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAr0XW6QacR8GilY4nZrJZW40wnFeYu7h9aXUSqxCP6djurCWZmLqnrsYWP7/HR8WOulYPHTVpfqJesTOdVqPgY6p10H811oRbJG9jvsG8j8kn/Bk8b2wZ9qelNqdNJMDbR5WUyaytaDWW6QdI4+clqjFfwCOw76noDSe+R4pDSzgMiyCk5R4m2ECT1fv/4Axz2bvLN+DRTg5DPPIMLWpA87lgjxeaDlGyJqZCbkJozW7JX0AJVc0X7YR9kzbiTi3LVOInSKY+VHT8yCARIdvXtKc6+IWSbVQqgpNIBB8GN0OvU8xedjPNCMGZnnMtgd7XLTf/okyadbdNLAqQLTbDs/5HnIVx8FyfgiOS/zsim5ivi3ljVAW3T3ePGjkY0q1DMzr5iJ4m/WTL2d1TArlfHyQhkSpFpQPOO+pJyVQqttHJo99vMirQogdSx4lIu//aod0yJyJLpjCeiqb2Fz3Qk0AZ4S78QKeeGsxTRchTP6Wsb6okaZd+cFi6z8qbP0z/Y3xRZO7vOLB/whkqS+pMVKBQ42YzgQPRzbXXmgCkf1nCqgrD9bnIB5ovdRGfDXW86GKY8XwGVjb4BoMvql+HsbonKHAO+eGfQulpB5YfQGQU3ZXdMdfCLAk8FuqemH4k7S7diLzVvRCuisHsEx6qJ4ewxzNCvW7OGVinTR9NSQUCAwEAAQ==";


    /**
     * Merchant Decryption Private Key used to cryptographically decrypt the response JWE.
     * @var string
     */
    public static string $MerchantDecryptionPrivateKey = "MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCe2pJ/MzVG8eH+
lVkrReqRaBSGF5Y2+33DGcGBvLJ037Ovj0GZeo9omSODxAk3Muc74dm8vYvNhhg7
3DwvjBVlomykQfTtahQVOPjaieQGj9rwD6Mg0weCIUrTVNsc8ngrRtnt3zY92ofr
3nufpbnYl433kZMoADaFSbWYs/fMPznAhPhVSI97fFRlpUvrAq9yEolT2IzWLKRA
Vt6NvQG6lqC9cnw7o98OYFt+OmhJ7Omwfqy++w5MsnnzqTgC1/4W/T0Wg8Jkd5uz
lbq6oiE41zMyoswYzs2EdrB7K+VRfLwQOvwPf8bHzM4Z3BGA4d5hYeKAfoyR9b6m
HcGhAQ5VAgMBAAECggEAIzQF7hlvj5rP/eaj/aJ/Ypzhm3vDpsih7KgbCYDDPYJL
pDbHj1cpS8FQLQW3Exv9QXiCE8Efp7q1SSK71b+iCEVv9RDG0gxFihR3lZqkRU4A
811LxdzuV4jAN8ggzK/xMIoBhqUGNLvmjj9ePxlvb4/afsgsh9tQOcaFb2NGoWTx
vHC4RbJUfHipcfFxMK7Nl4FyiEzztDrj6usB1TJ5XuYZ3xvoDVvQ/dqYI8s63PfM
SaVgqni5/mEMGKrkggbNO0IXb6a+b36aClKi6Cvrycu8H8CRC7NbRMaOQdVSDMlF
RXCxq6GHXYTMx0nMwMLr/WYUsf45Vwd7ST2WV0i5SwKBgQDec8MUs5Y+Qdi5sDKT
lrV2RmbMBzLTifUJ6fKJxyN6yasAvI3pE80Q3v/VtKTudgdVq3O8IBluPU0jal1B
brrDq41OBuHKIZlD2Ir0SyaizhJQ8VlpWRqHNee583NcqrV+QFOpP1th+/5uyoJj
MmRQAjP0zW7rXQTqXeBKVhsdIwKBgQC2z3WdeGwLrI/a7fGBf+Iwknoy/yb6yUI4
Zguw/Fr+22zeCWAL3eOUGjb8Ucu4EFOm31GifkzrQx7Sv0mHEvenDSfYrgfwKpiV
1QT9U/8qTtJaGToSYCkxRGZZyZuTSYllwlTsfCFYKnMEZjFphIfRt6en/CRzHhmM
yOYf7bbKJwKBgDoOXEt60ytMZBOSOKDsJE/J7+ovtsQerST5OaNbpZbWKxr2GtNJ
p6tPh9VuX90cUK27IWlntzteJFOp3szE6VlH3IkQorzuJ+HdEebP9jVnMsCNPJiR
+KpxFxkgwGre4p8girURI/hem/iuQXlCHYwEBytMsjYbAL1p4q+D6W+hAoGADEMm
NIXbWX0duSW0yWb2mSN6JumOh8vwMTBHIHwM7oNxbgNa+fDMTybjAVHLRHFz6wGX
zDqEllNOQfyqxfCzw/TR82rZBXcV/Rbo2sVDnGblHT4L8yeYG8Hmy6cGVH7eRIEg
iSxaYDuYs3bXYaiOI7cZ+96h40bll9fx97pORIkCgYBwteXqGi8XJYv7PAqxgxHp
bc1859sXF/ToDz3xXGFWzKux958U29CVngol3u/7t+9qsSKP0Rxesv45wPllqa0j
YYWSGVUXRoYLu1Dt4LWnTmhW7eL/z4vuZ/2SVMbrcs2ID3qn6FQ0slBFX9wXMkXU
/MjrtxQDo/bpc3uOBSyZ9g==";
}
