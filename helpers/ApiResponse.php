<?php

class ApiResponse {
    public static function codeFor($response, $default = 200) {
        if (($response['status'] ?? 'error') === 'success') return $default;
        $message = strtolower((string)($response['message'] ?? ''));
        if (str_contains($message, 'akses ditolak')) return 403;
        if (str_contains($message, 'tidak ditemukan')) return 404;
        if (str_contains($message, 'sudah diproses') || str_contains($message, 'status')) return 409;
        if (str_contains($message, 'wajib') || str_contains($message, 'tidak valid') || str_contains($message, 'stok')) return 422;
        return 400;
    }

    public static function normalize($response) {
        if (($response['status'] ?? 'error') === 'error' && empty($response['code'])) {
            $http = self::codeFor($response);
            $response['code'] = match ($http) {
                403 => 'SUP_FORBIDDEN',
                404 => 'SUP_NOT_FOUND',
                409 => 'SUP_STATE_CONFLICT',
                422 => 'SUP_VALIDATION_FAILED',
                default => 'SUP_REQUEST_FAILED',
            };
        }
        return $response;
    }
}
