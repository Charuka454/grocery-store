<?php
// reply_message.php
@include 'config.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if(!$admin_id){
  header('location:login.php');
  exit;
}

$to         = isset($_POST['to']) ? trim($_POST['to']) : '';
$subject    = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$body       = isset($_POST['body']) ? trim($_POST['body']) : '';
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;

if(!filter_var($to, FILTER_VALIDATE_EMAIL)){
  $_SESSION['flash_msg'] = 'Invalid recipient email.';
  header('location:admin_contacts.php');
  exit;
}
if($subject === '' || $body === ''){
  $_SESSION['flash_msg'] = 'Subject and message body are required.';
  header('location:admin_contacts.php');
  exit;
}

// Defaults (override in config.php if you want)
if(!defined('MAIL_FROM'))      define('MAIL_FROM', 'no-reply@yourdomain.com');
if(!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Support Team');

// SMTP defaults (optional)
if(!defined('SMTP_HOST'))     define('SMTP_HOST', '');
if(!defined('SMTP_PORT'))     define('SMTP_PORT', 587);
if(!defined('SMTP_USERNAME')) define('SMTP_USERNAME', '');
if(!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', '');
if(!defined('SMTP_SECURE'))   define('SMTP_SECURE', 'tls'); // 'ssl' or 'tls'
if(!defined('SMTP_AUTH'))     define('SMTP_AUTH', true);

$sent = false;
$err  = '';

// Try PHPMailer if present
try {
  $autoloads = [
    __DIR__ . '/vendor/autoload.php',      // project root
    __DIR__ . '/../vendor/autoload.php',   // parent
  ];
  foreach($autoloads as $a){
    if(file_exists($a)){ require_once $a; break; }
  }

  if(class_exists('PHPMailer\\PHPMailer\\PHPMailer')){
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    if(SMTP_HOST){
      $mail->isSMTP();
      $mail->Host       = SMTP_HOST;
      $mail->SMTPAuth   = (bool)SMTP_AUTH;
      if(SMTP_USERNAME) $mail->Username = SMTP_USERNAME;
      if(SMTP_PASSWORD) $mail->Password = SMTP_PASSWORD;
      if(SMTP_SECURE)   $mail->SMTPSecure = SMTP_SECURE;
      if(SMTP_PORT)     $mail->Port = SMTP_PORT;
    }

    $mail->CharSet = 'UTF-8';
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $safeBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    $mail->Body    = $safeBody;
    $mail->AltBody = $body;

    $mail->send();
    $sent = true;
  }
} catch(Throwable $e){
  $err = $e->getMessage();
}

// Fallback to PHP mail()
if(!$sent){
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/html; charset=UTF-8\r\n";
  $headers .= "From: ".MAIL_FROM_NAME." <".MAIL_FROM.">\r\n";
  $headers .= "Reply-To: ".MAIL_FROM."\r\n";

  $safeSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
  $safeBody    = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

  $sent = @mail($to, $safeSubject, $safeBody, $headers);
  if(!$sent && $err === '') $err = 'mail() failed or is disabled on this server.';
}

// Optional: mark as replied_at if you add such a column later
// if($sent && $message_id){
//   $stmt = $conn->prepare("UPDATE `message` SET replied_at = NOW() WHERE id = ?");
//   $stmt->execute([$message_id]);
// }

$_SESSION['flash_msg'] = $sent ? 'Reply sent successfully.' : ('Failed to send reply. ' . $err);
header('location:admin_contacts.php');
exit;
