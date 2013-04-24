<html>
<head>
<title>POP before SMTP Test</title>
</head>
<body>

<?php
require_once(' ../class.phpmailer.php');
require_once(' ../class.pop3.php'); // required for POP before SMTP

$pop = new Pop3();
$pop->Authorise('pop3.yourdomain.com', 110, 30, 'username', 'password', 1);

$mail = new PHPMailer(TRUE); // the TRUE param means it will throw exceptions on errors, which we need to catch

$mail->useSmtp();

try {
  $mail->smtp_debug = 2;
  $mail->host     = 'pop3.yourdomain.com';
  $mail->addReplyTo('name@yourdomain.com', 'First Last');
  $mail->addAddress('whoto@otherdomain.com', 'John Doe');
  $mail->setFrom('name@yourdomain.com', 'First Last');
  $mail->addReplyTo('name@yourdomain.com', 'First Last');
  $mail->subject = 'PHPMailer Test Subject via mail(), advanced';
  $mail->alt_body = 'To view the message, please use an HTML compatible email viewer!'; // optional - MsgHTML will create an alternate automatically
  $mail->msgHtml(file_get_contents('contents.html'));
  $mail->addAttachment('images/phpmailer.gif');      // attachment
  $mail->addAttachment('images/phpmailer_mini.gif'); // attachment
  $mail->send();
  echo "Message Sent OK</p>\n";
} catch (MailerException $e) {
  echo $e->errorMessage(); //Pretty error messages from PHPMailer
} catch (Exception $e) {
  echo $e->getMessage(); //Boring error messages from anything else!
}
?>

</body>
</html>
