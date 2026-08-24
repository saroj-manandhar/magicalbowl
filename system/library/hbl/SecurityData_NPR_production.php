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
    //public static string $AccessToken = "5fb21f4f4df842a7b4ad1b44674dcf20";
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
    public static string $JWEEncrptionAlgorithm = "A128CBC-HS256";

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
    public static string $PacoEncryptionPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAntqSfzM1RvHh/pVZK0Xq
kWgUhheWNvt9wxnBgbyydN+zr49BmXqPaJkjg8QJNzLnO+HZvL2LzYYYO9w8L4wV
ZaJspEH07WoUFTj42onkBo/a8A+jINMHgiFK01TbHPJ4K0bZ7d82PdqH6957n6W5
2JeN95GTKAA2hUm1mLP3zD85wIT4VUiPe3xUZaVL6wKvchKJU9iM1iykQFbejb0B
upagvXJ8O6PfDmBbfjpoSezpsH6svvsOTLJ586k4Atf+Fv09FoPCZHebs5W6uqIh
ONczMqLMGM7NhHaweyvlUXy8EDr8D3/Gx8zOGdwRgOHeYWHigH6MkfW+ph3BoQEO
VQIDAQAB";

    /**
     * PACO Signing Public Key is used to cryptographically verify the response JWS signature.
     *
     * @var string
     */
    public static string $PacoSigningPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuu9mA6xSHRwjVyafaAPd
dmio/+G3WJMWkbXZ1oH05S53m9dagokos2LT1b+lGFGWMpk15jzg64lxAcjtKDb9
OVmvfI2de6XODUDw/ULTYzLcJqQkc7+FFIzMhPO8UHDgvGKBAPxALMXdTZ9rPtYi
F7ngQ0frTtcUK3GQ75UtnwPOJCERW2Z3klbcGU2MLu4ZTdz0NJKzzdNWy7mmAcQu
xeCkh8v1u0IZTZpMGTdVRUvwTZ59abNUFKrVU8rBKrf4a9MHM/r09YHDCEilo0pQ
WCPuiMxd1UqRJoyODkItKaSWuP63OQwM1xpfeaEXgM2ULO/EKESaBf6wpd8yRA0t
2QIDAQAB";


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
