<html>
<head>
<title>PHPMailer - SMTP basic test with authentication</title>
</head>
<body>

<?php

//error_reporting(E_ALL);
error_reporting(E_STRICT);

date_default_timezone_set('America/Toronto');

require_once(' ../class.phpmailer.php');
//include("class.smtp.php"); // optional, gets called from within class.phpmailer.php if not already loaded

$mail             = new PHPMailer();

$body             = file_get_contents('contents.html');
$body             = eregi_replace("[\]",'', $body);

$mail->useSmtp(); // telling the class to use SMTP
$mail->host       = "mail.yourdomain.com"; // SMTP server
$mail->smtp_debug  = 2;                     // enables SMTP debug information (for testing)
                                           // 1 = errors and messages
                                           // 2 = messages only
$mail->smtp_auth   = TRUE;                  // enable SMTP authentication
$mail->host       = "mail.yourdomain.com"; // sets the SMTP server
$mail->port       = 26;                    // set the SMTP port for the GMAIL server
$mail->username   = "yourname@yourdomain"; // SMTP account username
$mail->Password   = "yourpassword";        // SMTP account password

$mail->setFrom('name@yourdomain.com', 'First Last');

$mail->addReplyTo("name@yourdomain.com","First Last");

$mail->subject    = "PHPMailer Test Subject via smtp, basic with authentication";

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
