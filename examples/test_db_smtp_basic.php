<html>
<head>
<title>PHPMailer - MySQL Database - SMTP basic test with authentication</title>
</head>
<body>

<?php

//error_reporting(E_ALL);
error_reporting(E_STRICT);

date_default_timezone_set('America/Toronto');

require_once(' ../class.phpmailer.php');
//include("class.smtp.php"); // optional, gets called from within class.phpmailer.php if not already loaded

$mail                = new PHPMailer();

$body                = file_get_contents('contents.html');
$body                = eregi_replace("[\]",'', $body);

$mail->useSmtp(); // telling the class to use SMTP
$mail->host          = "smtp1.site.com;smtp2.site.com";
$mail->smtp_auth      = TRUE;                  // enable SMTP authentication
$mail->smtp_keep_alive = TRUE;                  // SMTP connection will not close after each email sent
$mail->host          = "mail.yourdomain.com"; // sets the SMTP server
$mail->port          = 26;                    // set the SMTP port for the GMAIL server
$mail->username      = "yourname@yourdomain"; // SMTP account username
$mail->Password      = "yourpassword";        // SMTP account password
$mail->setFrom('list@mydomain.com', 'List manager');
$mail->addReplyTo('list@mydomain.com', 'List manager');

$mail->subject       = "PHPMailer Test Subject via smtp, basic with authentication";

@MYSQL_CONNECT("localhost","root","password");
@mysql_select_db("my_company");
$query  = "SELECT full_name, email, photo FROM employee WHERE id=$id";
$result = @MYSQL_QUERY($query);

while ($row = mysql_fetch_array ($result)) {
  $mail->alt_body    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
  $mail->msgHtml($body);
  $mail->addAddress($row["email"], $row["full_name"]);
  $mail->addStringAttachment($row["photo"], "YourPhoto.jpg");

  if (!$mail->send()) {
    echo "Mailer Error (" . str_replace("@", "&#64;", $row["email"]) . ') ' . $mail->error_info . '<br />';
  } else {
    echo "Message sent to :" . $row["full_name"] . ' (' . str_replace("@", "&#64;", $row["email"]) . ')<br />';
  }
  // Clear all addresses and attachments for next loop
  $mail->clearAddresses();
  $mail->clearAttachments();
}
?>

</body>
</html>
