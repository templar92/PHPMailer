<?php
/**
* Simple example script using PHPMailer with exceptions enabled
* @package phpmailer
* @version $Id$
*/

require ' ../class.phpmailer.php';

try {
	$mail = new PHPMailer(TRUE); //New instance, with exceptions enabled

	$body             = file_get_contents('contents.html');
	$body             = preg_replace('/\\\\/','', $body); //Strip backslashes

	$mail->useSmtp();                           // tell the class to use SMTP
	$mail->smtp_auth   = TRUE;                  // enable SMTP authentication
	$mail->port       = 25;                    // set the SMTP server port
	$mail->host       = "mail.yourdomain.com"; // SMTP server
	$mail->username   = "name@domain.com";     // SMTP server username
	$mail->Password   = "password";            // SMTP server password

	$mail->useSendmail();  // tell the class to use Sendmail

	$mail->addReplyTo("name@domain.com","First Last");

	$mail->from       = "name@domain.com";
	$mail->from_name   = "First Last";

	$to = "someone@example...com";

	$mail->addAddress($to);

	$mail->subject  = "First PHPMailer Message";

	$mail->alt_body    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
	$mail->word_wrap   = 80; // set word wrap

	$mail->msgHtml($body);

	$mail->useHtml(TRUE); // send as HTML

	$mail->send();
	echo 'Message has been sent. ';
} catch (MailerException $e) {
	echo $e->errorMessage();
}
?>