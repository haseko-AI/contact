<?php
// sendmail_sendgrid.php - SendGrid API経由メール送信
// 設置場所: /virtual/aidirector/config/sendmail_sendgrid.php

header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$allowed_origins = [
    'https://haseko-ai.github.io',
    'https://aitech-jp.com',
    'https://www.aitech-jp.com',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://aitech-jp.com');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POSTリクエストのみ受け付けます']);
    exit;
}

$config_file = '/virtual/aidirector/config/mail_config.php';
if (!file_exists($config_file)) {
    echo json_encode(['success' => false, 'message' => 'mail_config.phpが見つかりません']);
    exit;
}
require_once $config_file;

$apiKey = $mail_config['sendgrid_api_key'] ?? '';
if (empty($apiKey)) {
    echo json_encode(['success' => false, 'message' => 'SendGrid APIキーが設定されていません']);
    exit;
}

$to          = isset($_POST['to'])          ? trim($_POST['to'])          : '';
$fromName    = isset($_POST['fromName'])    ? trim($_POST['fromName'])    : 'AI Director';
$subject     = isset($_POST['subject'])     ? trim($_POST['subject'])     : '（件名なし）';
$bodyContent = isset($_POST['bodyContent']) ? $_POST['bodyContent']       : '';
$title       = isset($_POST['title'])       ? trim($_POST['title'])       : $subject;
$subtitle    = isset($_POST['subtitle'])    ? trim($_POST['subtitle'])    : '自動送信システム';

// 管理者CC
$admin_cc = 'ishida.from@gmail.com';
$cc  = isset($_POST['cc'])  ? trim($_POST['cc'])  : '';
$bcc = isset($_POST['bcc']) ? trim($_POST['bcc']) : '';
if (empty($cc)) {
    $cc = $admin_cc;
} elseif (strpos($cc, $admin_cc) === false) {
    $cc .= ',' . $admin_cc;
}

if (empty($to)) {
    echo json_encode(['success' => false, 'message' => '宛先が指定されていません']);
    exit;
}

// デザイン
$bgColor1   = '#1a3a5c';
$bgColor2   = '#0d2137';
$titleColor = '#ffffff';
$lineColor  = '#2d7dd2';

$titleEsc    = htmlspecialchars($title,    ENT_QUOTES, 'UTF-8');
$subtitleEsc = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');

$htmlBody = '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:\'Hiragino Kaku Gothic ProN\',Meiryo,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:linear-gradient(135deg,' . $bgColor1 . ',' . $bgColor2 . ');">
  <tr><td style="padding:14px 26px 0;">
    <p style="margin:0 0 14px;font-size:20px;font-weight:bold;color:' . $titleColor . ';">' . $titleEsc . '</p>
  </td></tr>
  <tr><td height="1" style="background:' . $lineColor . ';font-size:0;">&nbsp;</td></tr>
  <tr><td style="padding:5px 26px 10px;">
    <p style="margin:0;font-size:11px;color:rgba(255,255,255,0.6);">' . $subtitleEsc . '</p>
  </td></tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;">
  <tr><td style="padding:28px;color:#1a2540;font-size:14px;line-height:1.9;">' . $bodyContent . '</td></tr>
  <tr><td style="background:#f4f6fb;padding:14px 26px;">
    <p style="margin:0;font-size:12px;font-weight:bold;color:#1a4fa8;text-align:right;">AI Director / AI tech JAPAN</p>
    <p style="margin:0;font-size:11px;color:#8a9ab8;text-align:right;">このメールは自動送信システムより送信されています。返信はできません。</p>
  </td></tr>
</table>
</body></html>';

$fromAddress = 'info@aitech-jp.com';
$data = [
    'personalizations' => [[
        'to' => array_map(fn($e) => ['email' => trim($e)], explode(',', $to)),
    ]],
    'from'    => ['email' => $fromAddress, 'name' => $fromName],
    'subject' => $subject,
    'content' => [['type' => 'text/html', 'value' => $htmlBody]],
];

if (!empty($cc)) {
    $ccList = array_filter(array_map('trim', explode(',', $cc)));
    if ($ccList) $data['personalizations'][0]['cc'] = array_map(fn($e) => ['email' => $e], $ccList);
}
if (!empty($bcc)) {
    $bccList = array_filter(array_map('trim', explode(',', $bcc)));
    if ($bccList) $data['personalizations'][0]['bcc'] = array_map(fn($e) => ['email' => $e], $bccList);
}

$ch = curl_init('https://api.sendgrid.com/v3/mail/send');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'cURLエラー: ' . $curlError]);
    exit;
}
if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode(['success' => true, 'message' => '送信成功']);
} else {
    $errData = json_decode($response, true);
    $errMsg  = $errData['errors'][0]['message'] ?? $response;
    echo json_encode(['success' => false, 'message' => '送信失敗: ' . $errMsg]);
}
