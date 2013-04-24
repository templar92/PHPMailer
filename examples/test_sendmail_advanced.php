<html>
<head>
<title>PHPMailer - Sendmail advanced test</title>
</head>
<body>

<?php

require_once(' ../class.phpmailer.php');

$mail = new PHPMailer(TRUE); // the TRUE param means it will throw exceptions on errors, which we need to catch
$mail->useSendmail(); // telling the class to use SendMail transport

try {
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
