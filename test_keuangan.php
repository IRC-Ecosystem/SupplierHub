<?php
$_SESSION['user_id'] = 1;
$_SESSION['name'] = 'Admin Supplier';
$_SESSION['role'] = 'supplier';
$_GET['p'] = 'supplier';
$_GET['page'] = 'keuangan';

ob_start();
try {
    require_once __DIR__ . '/index.php';
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString();
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
$output = ob_get_clean();

if (strpos($output, 'Fatal error') !== false || strpos($output, 'Exception') !== false || strpos($output, 'Error:') !== false) {
    echo "Error found in output:\n";
    echo strip_tags($output);
} else {
    echo "No obvious errors in output. Length: " . strlen($output) . " bytes\n";
}
