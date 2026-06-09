<?php
/**
 * JWT Helper
 * JSON Web Token encode/decode
 * 
 * Kompatibel dengan AuthMiddleware.php di website PHP utama.
 * Menggunakan algoritma HS256 (HMAC-SHA256).
 */

class JWT {

    /**
     * Encode payload menjadi JWT token
     * 
     * @param array $payload Data yang akan di-encode (user_id, email, role, dll)
     * @return string JWT token
     */
    public static function encode($payload) {
        // Header
        $header = self::base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]));

        // Payload dengan waktu
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        // Signature
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true)
        );

        return "$header.$payloadEncoded.$signature";
    }

    /**
     * Decode JWT token menjadi payload
     * 
     * @param string $token JWT token
     * @return array|null Payload data atau null jika invalid
     */
    public static function decode($token) {
        if (empty($token)) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
        );

        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        // Decode payload
        $data = json_decode(self::base64UrlDecode($payload), true);

        // Check expiry
        if (isset($data['exp']) && $data['exp'] < time()) {
            return null;
        }

        return $data;
    }

    /**
     * Base64 URL-safe encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
