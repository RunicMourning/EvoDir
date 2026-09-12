<?php
$error_code    = '500';
$error_title   = 'Internal Server Error';
$error_message = 'The server encountered an unexpected condition that prevented it from fulfilling the request.';
$error_detail  = 'Check the Apache error log for details. The issue is server-side.';
$error_icon    = 'bi-exclamation-octagon-fill';
require __DIR__ . '/error-page.php';
