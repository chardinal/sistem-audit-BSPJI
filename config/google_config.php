<?php
// ============================================================
// Google API Configuration — AMS
// ============================================================

define('GOOGLE_CREDENTIALS_PATH', __DIR__ . '/google_credentials.json');
define('GOOGLE_TOKEN_PATH',       __DIR__ . '/google_token.json');
define('GOOGLE_REDIRECT_URI',     'http://localhost:8080/auth/google_auth.php');

// Scope yang dibutuhkan AMS
define('GOOGLE_SCOPES', [
    Google\Service\Gmail::GMAIL_SEND,
    Google\Service\Calendar::CALENDAR,
]);
