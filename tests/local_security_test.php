<?php
require_once __DIR__ . '/../helpers/ApiResponse.php';

function securityAssert($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

securityAssert(ApiResponse::codeFor(['status'=>'error','message'=>'Akses ditolak.']) === 403, 'Forbidden harus 403.');
securityAssert(ApiResponse::codeFor(['status'=>'error','message'=>'Pesanan tidak ditemukan.']) === 404, 'Not found harus 404.');
securityAssert(ApiResponse::codeFor(['status'=>'error','message'=>'Status sudah diproses.']) === 409, 'Conflict harus 409.');
securityAssert(ApiResponse::codeFor(['status'=>'error','message'=>'Item wajib diisi.']) === 422, 'Validation harus 422.');

echo "LOCAL_SECURITY_OK\n";
