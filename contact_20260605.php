<?php
// contact.php - お問い合わせフォーム受信・SMTP送信
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

// config読み込み
$config_path = dirname(__FILE__) . '/../../config/contact_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    define('CONTACT_TO',   'info@aitech-jp.com');
    define('CONTACT_CC',   '');
    define('CONTACT_FROM', 'noreply@aitech-jp.com');
    define('CONTACT_PASS', '');
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

// SMTP設定
$smtp_host = 's323.xrea.com';
$smtp_port = 465;
$smtp_user = CONTACT_FROM;
$smtp_pass = CONTACT_PASS;
$from      = CONTACT_FROM;
$eol       = "\r\n";

// 件名・宛先
$subject  = '【' . $urgency . '】お問い合わせ：' . $name . ' 様';
$to       = CONTACT_TO;
$cc       = CONTACT_CC;
$to_list  = array_map('trim', explode(',', $to));
$cc_list  = !empty($cc) ? array_map('trim', explode(',', $cc)) : [];

// 緊急度カラー
$urgency_colors = [
    '緊急'         => '#c8102e',
    'なるべく早く'  => '#d97706',
    '時間のある時に'=> '#2d8a4e',
];
$urgency_color = $urgency_colors[$urgency] ?? '#1a4a2e';

// HTMLメール本文
$bg1 = '#1a4a2e';
$bg2 = '#2d6a4f';
$html_body = <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:'Hiragino Kaku Gothic ProN',Meiryo,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,{$bg1},{$bg2});">
  <tr>
    <td style="padding:18px 26px 6px;">
      <p style="margin:0;font-size:18px;font-weight:bold;color:#ffffff;">📨 お問い合わせが届きました</p>
    </td>
  </tr>
  <tr><td height="1" style="background:#52b788;font-size:0;">&nbsp;</td></tr>
  <tr><td style="padding:4px 26px 10px;font-size:10px;color:rgba(255,255,255,0.4);">{$time}</td></tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;">
  <tr><td style="padding:24px 28px;font-size:14px;line-height:1.9;color:#1a2540;">
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
    </table>
  </td></tr>
  <tr><td style="background:#f4f6fb;padding:12px 26px;border-top:none;border-bottom:2px solid #52b788;font-size:11px;color:#8a9ab8;text-align:right;">
    送信元IP: {$ip}　✦ AI Director / AI tech JAPAN ✦
  </td></tr>
</table>
</body>
</html>
HTML;

$plain_text = "【{$urgency}】\nお名前：{$name}\n返信先：{$contact}\n\nお問い合わせ内容：\n{$message}\n\n送信元IP：{$ip}\n送信日時：{$time}";

// ヘッダー構築
$boundary = '----=_Part_' . md5(uniqid(rand(), true));
$headers  = "From: =?UTF-8?B?" . base64_encode('AI Director') . "?= <{$from}>" . $eol;
$headers .= "To: {$to}" . $eol;
if (!empty($cc)) $headers .= "Cc: {$cc}" . $eol;
$headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=" . $eol;
$headers .= "MIME-Version: 1.0" . $eol;
$headers .= "Content-Language: ja" . $eol;
$headers .= "X-Mailer: AI-Director-Contact" . $eol;
$headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"" . $eol;

$message_body  = "--{$boundary}" . $eol;
$message_body .= "Content-Type: text/plain; charset=UTF-8" . $eol;
$message_body .= "Content-Transfer-Encoding: base64" . $eol . $eol;
$message_body .= chunk_split(base64_encode($plain_text)) . $eol;
$message_body .= "--{$boundary}" . $eol;
$message_body .= "Content-Type: text/html; charset=UTF-8" . $eol;
$message_body .= "Content-Transfer-Encoding: base64" . $eol . $eol;
$message_body .= chunk_split(base64_encode($html_body)) . $eol;
$message_body .= "--{$boundary}--" . $eol;

// SMTP送信関数（sendmail_html.phpと同じ方式）
function smtp_send($host, $port, $user, $pass, $from, $to_list, $cc_list, $headers, $message) {
    $socket = fsockopen("ssl://{$host}", $port, $errno, $errstr, 30);
    if (!$socket) throw new Exception("接続失敗: {$errstr} ({$errno})");

    function smtp_read($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }
    function smtp_cmd($socket, $cmd) {
        fputs($socket, $cmd . "\r\n");
        return smtp_read($socket);
    }

    smtp_read($socket);
    smtp_cmd($socket, "EHLO aitech-jp.com");
    smtp_cmd($socket, "AUTH LOGIN");
    smtp_cmd($socket, base64_encode($user));
    $resp = smtp_cmd($socket, base64_encode($pass));
    if (strpos($resp, '235') === false) throw new Exception("SMTP認証失敗: {$resp}");

    smtp_cmd($socket, "MAIL FROM:<{$from}>");
    foreach (array_merge($to_list, $cc_list) as $addr) {
        if ($addr) smtp_cmd($socket, "RCPT TO:<{$addr}>");
    }
    smtp_cmd($socket, "DATA");
    fputs($socket, $headers . "\r\n" . $message . "\r\n.\r\n");
    $resp = smtp_read($socket);
    smtp_cmd($socket, "QUIT");
    fclose($socket);

    if (strpos($resp, '250') === false) throw new Exception("送信失敗: {$resp}");
    return true;
}

try {
    smtp_send($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $from, $to_list, $cc_list, $headers, $message_body);
    echo json_encode(['success' => true, 'message' => '送信しました']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
