<html>
<head>
<title>PHPMailer - Mail() basic test</title>
</head>
<body>

<?php

require_once(' ../class.phpmailer.php');

$mail             = new PHPMailer(); // defaults to using php "mail()"

$body             = file_get_contents('contents.html');
$body             = eregi_replace("[\]",'', $body);

$mail->addReplyTo("name@yourdomain.com","First Last");

$mail->setFrom('name@yourdomain.com', 'First Last');

$mail->addReplyTo("name@yourdomain.com","First Last");

$address = "whoto@otherdomain.com";
$mail->addAddress($address, "John Doe");

$mail->subject    = "PHPMailer Test Subject via mail(), basic";

$mail->alt_body    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test

$mail->msgHtml($body);

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
