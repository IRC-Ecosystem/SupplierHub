<?php
/**
 * Response Helper
 * Standar format JSON response untuk REST API
 * 
 * Mengikuti pola types/api.ts di referensi:
 *   SuccessBody<T>  → { data: T }
 *   ErrorBody       → { error: { code, message } }
 *   PaginatedBody<T> → { data: T[], meta: { page, limit, total } }
 */

class Response {

    /**
     * Success response
     * Output: { "status": "success", "message": "...", "data": {...} }
     */
    public static function success($data = null, $message = 'Berhasil.', $code = 200) {
        http_response_code($code);
        $response = [
            'status'  => 'success',
            'message' => $message,
        ];
        if ($data !== null) {
            $response['data'] = $data;
        }
        echo json_encode($response);
        exit;
    }

    /**
     * Error response
     * Output: { "status": "error", "message": "...", "errors": {...} }
     */
    public static function error($message = 'Terjadi kesalahan.', $code = 400, $errors = null) {
        http_response_code($code);
        $response = [
            'status'  => 'error',
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        echo json_encode($response);
        exit;
    }

    /**
     * Paginated response
     * Output: { "status": "success", "data": [...], "meta": { page, limit, total } }
     */
    public static function paginated($data, $page, $limit, $total, $message = 'Data berhasil diambil.') {
        http_response_code(200);
        echo json_encode([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
            'meta'    => [
                'page'  => (int) $page,
                'limit' => (int) $limit,
                'total' => (int) $total,
            ]
        ]);
        exit;
    }

    /**
     * No Content response (untuk DELETE)
     */
    public static function noContent() {
        http_response_code(204);
        exit;
    }
}
