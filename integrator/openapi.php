<?php
require_once __DIR__.'/../middleware/AuthMiddleware.php';
AuthMiddleware::requireAuth('integrator');
header('Content-Type: application/yaml; charset=utf-8');
header('Cache-Control: private, no-store');
readfile(__DIR__.'/openapi.yaml');
