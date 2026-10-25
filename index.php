<?php
declare(strict_types=1);
define('LAEZER_PRIVATE_DIR', dirname(__DIR__) . '/laezer-private');
require __DIR__ . '/_app/bootstrap.php';
require __DIR__ . '/_app/auth.php';
require __DIR__ . '/_app/store.php';
require __DIR__ . '/_app/orders.php';
require __DIR__ . '/_app/payments.php';
require __DIR__ . '/_app/http.php';
\Laezer\handle();
