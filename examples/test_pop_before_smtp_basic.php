<html>
<head>
<title>POP before SMTP Test</title>
</head>
<body>

<?php
require_once(' ../class.phpmailer.php');
require_once(' ../class.pop3.php'); // required for POP before SMTP

$pop = new POP3();
$pop->Authorise('pop3.yourdomain.com', 110, 30, 'username', 'password', 1);

$mail = new PHPMailer();

$body             = file_get_contents('contents.html');
$body             = eregi_replace("[\]",'', $body);

$mail->isSmtp();
$mail->smtp_debug = 2;
$mail->host     = 'pop3.yourdomain.com';

$mail->setFrom('name@yourdomain.com', 'First Last');

$mail->addReplyTo("name@yourdomain.com","First Last");

$mail->subject    = "PHPMailer Test Subject via POP before SMTP, basic";

$mail->alt_body    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test

$mail->msgHtml($body);

$address = "whoto@otherdomain.com";
$mail->addAddress($address, "John Doe");

$mail->addAttachment("images/phpmailer.gif");      // attachment
$mail->addAttachment("images/phpmailer_mini.gif"); // attachment


if (!$mail->send()) {
  echo "Mailer Error: " . $mail->error_info;
} else {
  echo "Message sent!";
}

?>

</body>
</html>
