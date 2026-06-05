<?php
// contact.php - お問い合わせフォーム受信
// sendmail_proxy.php経由で送信（SendGrid/XREA切替対応）
// 設置場所: public_html/aitech-jp.com/contact.php

header('Content-Type: application/json; charset=UTF-8');

// CORS設定
$allowed_origins = [
    'https://haseko-ai.github.io',
    'https://aitech-jp.com',
    'https://www.aitech-jp.com',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    header('Access-Control-Allow-Origin: https://haseko-ai.github.io');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POSTリクエストのみ受け付けます']);
    exit;
}

// 入力受け取り
$data    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$name    = htmlspecialchars(trim($data['name']    ?? ''), ENT_QUOTES, 'UTF-8');
$contact = htmlspecialchars(trim($data['contact'] ?? ''), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(trim($data['message'] ?? ''), ENT_QUOTES, 'UTF-8');
$urgency = htmlspecialchars(trim($data['urgency'] ?? '緊急'), ENT_QUOTES, 'UTF-8');
$ip      = $_SERVER['REMOTE_ADDR'] ?? '';
$time    = date('Y年m月d日 H:i:s');

if (!$name || !$contact || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '必須項目が不足しています']);
    exit;
}

// 緊急度カラー
$urgency_colors = [
    '緊急'          => '#c8102e',
    'なるべく早く'   => '#d97706',
    '時間のある時に' => '#2d8a4e',
];
$urgency_color = $urgency_colors[$urgency] ?? '#1a4a2e';

// sendmail_sendgrid.phpのラッパーに入れる中身だけ渡す
$html_body = <<<HTML
<table width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:12px;color:#888;width:120px;">緊急度</td>
    <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:15px;font-weight:700;color:{$urgency_color};">{$urgency}</td>
  </tr>
  <tr>
    <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:12px;color:#888;">お名前</td>
    <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:15px;font-weight:600;">{$name}</td>
  </tr>
  <tr>
    <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:12px;color:#888;">返信先</td>
    <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:14px;">{$contact}</td>
  </tr>
  <tr>
    <td style="padding:12px 0;font-size:12px;color:#888;vertical-align:top;">お問い合わせ内容</td>
    <td style="padding:12px 0;font-size:14px;line-height:1.8;white-space:pre-wrap;">{$message}</td>
  </tr>
  <tr>
    <td colspan="2" style="padding:8px 0;font-size:11px;color:#8a9ab8;text-align:right;">
      送信元IP: {$ip}　送信日時: {$time}
    </td>
  </tr>
</table>
HTML;

$subject = '【' . $urgency . '】お問い合わせ：' . $name . ' 様';

// sendmail_proxy.phpに丸投げ（SendGrid/XREA自動切替）
$proxy_url = 'https://aitech-jp.com/sendmail_proxy.php';

$post_data = [
    'to'          => 'info@aitech-jp.com',
    'fromName'    => 'AI Director お問い合わせフォーム',
    'subject'     => $subject,
    'title'       => '📨 お問い合わせが届きました',
    'subtitle'    => $time,
    'bodyContent' => $html_body,
];

$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($post_data),
        'timeout' => 30,
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);

try {
    $result = file_get_contents($proxy_url, false, $context);
    if ($result === false) throw new Exception('sendmail_proxy.phpへの接続失敗');
    $decoded = json_decode($result, true);
    if ($decoded && isset($decoded['success'])) {
        echo json_encode($decoded);
    } else {
        echo json_encode(['success' => true, 'message' => '送信しました']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
