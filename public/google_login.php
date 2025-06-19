<?php
session_start();

$client_id = '549970718824-bdej62fad0ai1es9sm8v26gtg78kidi5.apps.googleusercontent.com';
$redirect_uri = 'http://localhost/TW-FII-UAIC/public/google_callback.php';
$scope = 'openid email profile https://www.googleapis.com/auth/drive';

$params = [
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => $scope,
    'access_type' => 'offline',
    'prompt' => 'consent',
];

$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header('Location: ' . $auth_url);
exit;
