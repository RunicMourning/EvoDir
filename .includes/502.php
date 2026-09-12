<?php
$error_code    = '502';
$error_title   = 'Bad Gateway';
$error_message = 'The server received an invalid response from an upstream service while attempting to fulfill the request.';
$error_detail  = 'The upstream service may be down or unreachable. Try again in a moment.';
$error_icon    = 'bi-hdd-network-fill';
require __DIR__ . '/error-page.php';
