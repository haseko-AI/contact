<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$name    = htmlspecialchars(trim($data['name']    ?? ''), ENT_QUOTES, 'UTF-8');
$contact = htmlspecialchars(trim($data['contact'] ?? ''), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(trim($data['message'] ?? ''), ENT_QUOTES, 'UTF-8');
$urgency = htmlspecialchars(trim($data['urgency'] ?? '緊急'), ENT_QUOTES, 'UTF-8');
$ip      = $_SERVER['REMOTE_ADDR'] ?? '';
$time    = date('Y年m月d日 H:i:s');

if (!$name || !$contact || !$message) {
    http_response_code(400);
    echo json_encode(['error' => '必須項目が不足しています']);
    exit;
}

// ── 送信先設定 ──
$to      = 'noreply@aitech-jp.com'; // ← ここを受け取りアドレスに変更
$from    = 'noreply@aitech-jp.com';
$subject = '【' . $urgency . '】お問い合わせ：' . $name . ' 様';

// ── HTMLメール本文 ──
$bg1     = '#1a4a2e';
$bg2     = '#2d6a4f';
$html = <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
  <!-- ヘッダー -->
  <tr><td style="background:linear-gradient(135deg,{$bg1},{$bg2});padding:24px 28px;">
    <div style="color:#ffffff;font-size:18px;font-weight:700;">📨 お問い合わせが届きました</div>
    <div style="color:rgba(255,255,255,0.7);font-size:12px;margin-top:4px;">{$time}</div>
  </td></tr>
  <!-- 本文 -->
  <tr><td style="padding:24px 28px;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:12px;color:#888;width:100px;">緊急度</td>
        <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:15px;font-weight:700;color:#c8102e;">{$urgency}</td>
      </tr>
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:12px;color:#888;width:100px;">お名前</td>
        <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:15px;font-weight:600;">{$name}</td>
      </tr>
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:12px;color:#888;">返信先</td>
        <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:14px;">{$contact}</td>
      </tr>
      <tr>
        <td style="padding:12px 0;font-size:12px;color:#888;vertical-align:top;">お問い合わせ内容</td>
        <td style="padding:12px 0;font-size:14px;line-height:1.7;white-space:pre-wrap;">{$message}</td>
      </tr>
    </table>
  </td></tr>
  <!-- フッター -->
  <tr><td style="background:#f8f8f8;padding:14px 28px;font-size:11px;color:#aaa;text-align:center;">
    送信元IP: {$ip}　✦ Powered by Claude with AI Director ✦
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

// ── メール送信 ──
$boundary = md5(uniqid());
$headers  = implode("\r\n", [
    "From: AI Director <{$from}>",
    "Reply-To: {$from}",
    "MIME-Version: 1.0",
    "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
    "X-Mailer: PHP/" . phpversion(),
]);

$plain = "お名前：{$name}\n返信先：{$contact}\n\n{$message}\n\n送信元IP：{$ip}\n送信日時：{$time}";

$body  = "--{$boundary}\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
$body .= $plain . "\r\n";
$body .= "--{$boundary}\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$body .= $html . "\r\n";
$body .= "--{$boundary}--";

$subject_encoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

if (mail($to, $subject_encoded, $body, $headers)) {
    echo json_encode(['success' => true, 'message' => '送信しました']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'メール送信に失敗しました']);
}