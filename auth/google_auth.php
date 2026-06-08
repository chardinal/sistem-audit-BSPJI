<?php
// ============================================================
// Google OAuth Authorization — Jalankan SEKALI saat setup awal
// URL: http://localhost:8080/auth/google_auth.php
// ============================================================

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/google_config.php';

$client = new Google\Client();
$client->setAuthConfig(GOOGLE_CREDENTIALS_PATH);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
foreach (GOOGLE_SCOPES as $scope) {
    $client->addScope($scope);
}
$client->setAccessType('offline');  // agar dapat refresh_token
$client->setPrompt('consent');      // paksa minta refresh_token baru

if (!isset($_GET['code'])) {
    // Langkah 1: redirect ke consent screen Google
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}

// Langkah 2: tukar authorization code dengan token
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    http_response_code(400);
    echo '<h2>Error Otorisasi</h2><p>' . htmlspecialchars($token['error_description'] ?? $token['error']) . '</p>';
    exit;
}

$client->setAccessToken($token);

// Simpan token (termasuk refresh_token) ke file
file_put_contents(GOOGLE_TOKEN_PATH, json_encode($client->getAccessToken(), JSON_PRETTY_PRINT));

?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Google Auth — AMS</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f0fdf4; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .card { background: #fff; border-radius: 12px; padding: 40px 48px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,.08); max-width: 420px; }
    .icon { font-size: 48px; margin-bottom: 16px; }
    h1 { color: #065f46; font-size: 22px; margin: 0 0 12px; }
    p  { color: #4b5563; font-size: 14px; line-height: 1.6; margin: 0 0 20px; }
    a  { display: inline-block; background: #1a6b3c; color: #fff; text-decoration: none; padding: 10px 24px; border-radius: 8px; font-size: 14px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon" style="color: #10b981; font-weight: bold; font-size: 48px;">&#10003;</div>
    <h1>Otorisasi Berhasil!</h1>
    <p>Token Google telah tersimpan di <code>config/google_token.json</code>.<br>
       Sistem siap mengirim email notifikasi dan membuat event Google Calendar.</p>
    <a href="/admin/index.php">Kembali ke Dashboard</a>
  </div>
</body>
</html>
