<?php
declare(strict_types=1);
require dirname(__DIR__) . '/laezer-private/app/bootstrap.php';
require dirname(__DIR__) . '/laezer-private/app/auth.php';
require dirname(__DIR__) . '/laezer-private/app/store.php';
require dirname(__DIR__) . '/laezer-private/app/orders.php';
require dirname(__DIR__) . '/laezer-private/app/payments.php';
require dirname(__DIR__) . '/laezer-private/app/http.php';
\Laezer\handle();
