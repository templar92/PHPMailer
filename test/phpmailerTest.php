<?php
/**
* PHPMailer - PHP email transport unit tests
* Before running these tests you need to install PHPUnit 3.3 or later through pear, like this:
*   pear install "channel://pear.phpunit.de/PHPUnit"
* Then run the tests like this:
*   phpunit phpmailerTest
* @package PHPMailer
* @author Andy Prevost
* @author Marcus Bointon
* @copyright 2004 - 2009 Andy Prevost
* @version $Id: phpmailerTest.php 444 2009-05-05 11:22:26Z coolbru $
* @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
*/

require 'PHPUnit/Framework.php';

$INCLUDE_DIR = " ../";

require $INCLUDE_DIR . 'class.phpmailer.php';
error_reporting(E_ALL);

/**
* PHPMailer - PHP email transport unit test class
* Performs authentication tests
*/
class phpmailerTest extends PHPUnit_Framework_TestCase {
    /**
     * Holds the default phpmailer instance.
     * @private
     * @type object
     */
    var $Mail = FALSE;

    /**
     * Holds the SMTP mail host.
     * @public
     * @type string
     */
    var $Host = "";
    
    /**
     * Holds the change log.
     * @private
     * @type string array
     */
    var $ChangeLog = array();
    
     /**
     * Holds the note log.
     * @private
     * @type string array
     */
    var $NoteLog = array();   

    /**
     * Run before each test is started.
     */
    function setUp() {
        global $INCLUDE_DIR;

	@include ' ./testbootstrap.php'; //Overrides go in here

        $this->Mail = new PHPMailer();

        $this->Mail->priority = 3;
        $this->Mail->encoding = "8bit";
        $this->Mail->charset = "iso-8859-1";
        if (array_key_exists('mail_from', $_REQUEST)) {
	        $this->Mail->from = $_REQUEST['mail_from'];
	    } else {
	        $this->Mail->from = 'unit_test@phpmailer.sf.net';
	    }
        $this->Mail->from_name = "Unit Tester";
        $this->Mail->sender = "";
        $this->Mail->subject = "Unit Test";
        $this->Mail->body = "";
        $this->Mail->alt_body = "";
        $this->Mail->word_wrap = 0;
        if (array_key_exists('mail_host', $_REQUEST)) {
	        $this->Mail->host = $_REQUEST['mail_host'];
	    } else {
	        $this->Mail->host = 'mail.example.com';
	    }
        $this->Mail->port = 25;
        $this->Mail->helo = "localhost.localdomain";
        $this->Mail->smtp_auth = FALSE;
        $this->Mail->username = "";
        $this->Mail->Password = "";
        $this->Mail->plugin_dir = $INCLUDE_DIR;
		$this->Mail->addReplyTo("no_reply@phpmailer.sf.net", "Reply Guy");
        $this->Mail->sender = "unit_test@phpmailer.sf.net";

        if (strlen($this->Mail->host) > 0) {
            $this->Mail->mailer = "smtp";
        } else {
            $this->Mail->mailer = "mail";
            $this->sender = "unit_test@phpmailer.sf.net";
        }
        
        if (array_key_exists('mail_to', $_REQUEST)) {
	        $this->SetAddress($_REQUEST['mail_to'], 'Test User', 'to');
	    }
        if (array_key_exists('mail_cc', $_REQUEST) and strlen($_REQUEST['mail_cc']) > 0) {
	        $this->SetAddress($_REQUEST['mail_cc'], 'Carbon User', 'cc');
	    }
    }     

    /**
     * Run after each test is completed.
     */
    function tearDown() {
        // Clean global variables
        $this->Mail = NULL;
        $this->ChangeLog = array();
        $this->NoteLog = array();
    }


    /**
     * Build the body of the message in the appropriate format.
     * @private
     * @returns void
     */
    function BuildBody() {
        $this->CheckChanges();
        
        // Determine line endings for message        
        if ($this->Mail->content_type == "text/html" || strlen($this->Mail->alt_body) > 0)
        {
            $eol = "<br/>";
            $bullet = "<li>";
            $bullet_start = "<ul>";
            $bullet_end = "</ul>";
        }
        else
        {
            $eol = "\n";
            $bullet = " - ";
            $bullet_start = "";
            $bullet_end = "";
        }
        
        $ReportBody = "";
        
        $ReportBody .= "---------------------" . $eol;
        $ReportBody .= "Unit Test Information" . $eol;
        $ReportBody .= "---------------------" . $eol;
        $ReportBody .= "phpmailer version: " . PHPMailer::VERSION . $eol;
        $ReportBody .= "Content Type: " . $this->Mail->content_type . $eol;
        
        if (strlen($this->Mail->host) > 0)
            $ReportBody .= "Host: " . $this->Mail->host . $eol;
        
        // If attachments then create an attachment list
        $attachments = $this->Mail->getAttachments();
        if (count($attachments) > 0)
        {
            $ReportBody .= "Attachments:" . $eol;
            $ReportBody .= $bullet_start;
            foreach ($attachments as $attachment) {
                $ReportBody .= $bullet . "Name: " . $attachment[1] . ", ";
                $ReportBody .= "Encoding: " . $attachment[3] . ", ";
                $ReportBody .= "Type: " . $attachment[4] . $eol;
            }
            $ReportBody .= $bullet_end . $eol;
        }
        
        // If there are changes then list them
        if (count($this->ChangeLog) > 0)
        {
            $ReportBody .= "Changes" . $eol;
            $ReportBody .= "-------" . $eol;

            $ReportBody .= $bullet_start;
            for($i = 0; $i < count($this->ChangeLog); $i++)
            {
                $ReportBody .= $bullet . $this->ChangeLog[$i][0] . " was changed to [" . 
                               $this->ChangeLog[$i][1] . "]" . $eol;
            }
            $ReportBody .= $bullet_end . $eol . $eol;
        }
        
        // If there are notes then list them
        if (count($this->NoteLog) > 0)
        {
            $ReportBody .= "Notes" . $eol;
            $ReportBody .= "-----" . $eol;

            $ReportBody .= $bullet_start;
            for($i = 0; $i < count($this->NoteLog); $i++)
            {
                $ReportBody .= $bullet . $this->NoteLog[$i] . $eol;
            }
            $ReportBody .= $bullet_end;
        }
        
        // Re-attach the original body
        $this->Mail->body .= $eol . $eol . $ReportBody;
    }
    
    /**
     * Check which default settings have been changed for the report.
     * @private
     * @returns void
     */
    function CheckChanges() {
        if ($this->Mail->priority != 3)
            $this->AddChange("Priority", $this->Mail->priority);
        if ($this->Mail->encoding != "8bit")
            $this->AddChange("Encoding", $this->Mail->encoding);
        if ($this->Mail->charset != "iso-8859-1")
            $this->AddChange("CharSet", $this->Mail->charset);
        if ($this->Mail->sender != "")
            $this->AddChange("Sender", $this->Mail->sender);
        if ($this->Mail->word_wrap != 0)
            $this->AddChange("WordWrap", $this->Mail->word_wrap);
        if ($this->Mail->mailer != "mail")
            $this->AddChange("Mailer", $this->Mail->mailer);
        if ($this->Mail->port != 25)
            $this->AddChange("Port", $this->Mail->port);
        if ($this->Mail->helo != "localhost.localdomain")
            $this->AddChange("Helo", $this->Mail->helo);
        if ($this->Mail->smtp_auth)
            $this->AddChange("SMTPAuth", "TRUE");
    }
    
    /**
     * Adds a change entry.
     * @private
     * @returns void
     */
    function AddChange($sName, $sNewValue) {
        $cur = count($this->ChangeLog);
        $this->ChangeLog[$cur][0] = $sName;
        $this->ChangeLog[$cur][1] = $sNewValue;
    }
    
    /**
     * Adds a simple note to the message.
     * @public
     * @returns void
     */
    function AddNote($sValue) {
        $this->NoteLog[] = $sValue;
    }

    /**
     * Adds all of the addresses
     * @public
     * @returns void
     */
    function SetAddress($sAddress, $sName = "", $sType = "to") {
        switch($sType)
        {
            case "to":
                return $this->Mail->addAddress($sAddress, $sName);
            case "cc":
                return $this->Mail->addCc($sAddress, $sName);
            case "bcc":
                return $this->Mail->addBcc($sAddress, $sName);
        }
    }

    /////////////////////////////////////////////////
    // UNIT TESTS
    /////////////////////////////////////////////////

    /**
     * Try a plain message.
     */
    function test_WordWrap() {

        $this->Mail->word_wrap = 40;
        $my_body = "Here is the main body of this message.  It should " .
                   "be quite a few lines.  It should be wrapped at the " .
                   "40 characters.  Make sure that it is. ";
        $nBodyLen = strlen($my_body);
        $my_body .= "\n\nThis is the above body length: " . $nBodyLen;

        $this->Mail->body = $my_body;
        $this->Mail->subject .= ": Wordwrap";

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    /**
     * Try a plain message.
     */
    function test_Low_Priority() {
    
        $this->Mail->priority = 5;
        $this->Mail->body = "Here is the main body.  There should be " .
                            "a reply to address in this message. ";
        $this->Mail->subject .= ": Low Priority";
        $this->Mail->addReplyTo("nobody@nobody.com", "Nobody (Unit Test)");

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    /**
     * Simple plain file attachment test.
     */
    function test_Multiple_Plain_FileAttachment() {

        $this->Mail->body = "Here is the text body";
        $this->Mail->subject .= ": Plain + Multiple FileAttachments";

        if (!$this->Mail->addAttachment("test.png"))
        {
            $this->assertTrue(FALSE, $this->Mail->error_info);
            return;
        }

        if (!$this->Mail->addAttachment(__FILE__, "test.txt"))
        {
            $this->assertTrue(FALSE, $this->Mail->error_info);
            return;
        }

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    /**
     * Simple plain string attachment test.
     */
    function test_Plain_StringAttachment() {

        $this->Mail->body = "Here is the text body";
        $this->Mail->subject .= ": Plain + StringAttachment";
        
        $sAttachment = "These characters are the content of the " .
                       "string attachment.\nThis might be taken from a " .
                       "database or some other such thing. ";
        
        $this->Mail->addStringAttachment($sAttachment, "string_attach.txt");

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    /**
     * Plain quoted-printable message.
     */
    function test_Quoted_Printable() {

        $this->Mail->body = "Here is the main body";
        $this->Mail->subject .= ": Plain + Quoted-printable";
        $this->Mail->encoding = "quoted-printable";

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);

	//Check that a quoted printable encode and decode results in the same as went in
	$t = substr(file_get_contents(__FILE__), 0, 1024); //Just pick a chunk of this file as test content
	$this->assertEquals($t, quoted_printable_decode($this->Mail->encodeQp($t)), 'QP encoding round-trip failed');
        //$this->assertEquals($t, quoted_printable_decode($this->Mail->encodeQpPhp($t)), 'Native PHP QP encoding round-trip failed'); //TODO the PHP qp encoder is quite broken

    }

    /**
     * Try a plain message.
     */
    function test_Html() {
    
        $this->Mail->useHtml(TRUE);
        $this->Mail->subject .= ": HTML only";
        
        $this->Mail->body = "This is a <b>test message</b> written in HTML. </br>" .
                            "Go to <a href=\"http://phpmailer.sourceforge.net/\">" .
                            "http://phpmailer.sourceforge.net/</a> for new versions of " .
                            "phpmailer.  <p/> Thank you!";

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    /**
     * Simple HTML and attachment test
     */
    function test_HTML_Attachment() {

        $this->Mail->body = "This is the <b>HTML</b> part of the email. ";
        $this->Mail->subject .= ": HTML + Attachment";
        $this->Mail->useHtml(TRUE);
        
        if (!$this->Mail->addAttachment(__FILE__, "test_attach.txt"))
        {
            $this->assertTrue(FALSE, $this->Mail->error_info);
            return;
        }

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    /**
     * An embedded attachment test.
     */
    function test_Embedded_Image() {

        $this->Mail->body = "Embedded Image: <img alt=\"phpmailer\" src=\"cid:my-attach\">" .
                     "Here is an image!</a>";
        $this->Mail->subject .= ": Embedded Image";
        $this->Mail->useHtml(TRUE);
        
        if (!$this->Mail->addEmbeddedImage("test.png", "my-attach", "test.png",
                                          "base64", "image/png"))
        {
            $this->assertTrue(FALSE, $this->Mail->error_info);
            return;
        }

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
	//For code coverage
	$this->Mail->addEmbeddedImage('thisfiledoesntexist', 'xyz'); //Non-existent file
	$this->Mail->addEmbeddedImage(__FILE__, '123'); //Missing name

    }

    /**
     * An embedded attachment test.
     */
    function test_Multi_Embedded_Image() {

        $this->Mail->body = "Embedded Image: <img alt=\"phpmailer\" src=\"cid:my-attach\">" .
                     "Here is an image!</a>";
        $this->Mail->subject .= ": Embedded Image + Attachment";
        $this->Mail->useHtml(TRUE);
        
        if (!$this->Mail->addEmbeddedImage("test.png", "my-attach", "test.png",
                                          "base64", "image/png"))
        {
            $this->assertTrue(FALSE, $this->Mail->error_info);
            return;
        }

        if (!$this->Mail->addAttachment(__FILE__, "test.txt"))
        {
            $this->assertTrue(FALSE, $this->Mail->error_info);
            return;
        }
        
        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    /**
     * Simple multipart/alternative test.
     */
    function test_AltBody() {

        $this->Mail->body = "This is the <b>HTML</b> part of the email. ";
        $this->Mail->alt_body = "Here is the text body of this message.  " .
                   "It should be quite a few lines.  It should be wrapped at the " .
                   "40 characters.  Make sure that it is. ";
        $this->Mail->word_wrap = 40;
        $this->AddNote("This is a mulipart alternative email");
        $this->Mail->subject .= ": AltBody + Word Wrap";

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    /**
     * Simple HTML and attachment test
     */
    function test_AltBody_Attachment() {

        $this->Mail->body = "This is the <b>HTML</b> part of the email. ";
        $this->Mail->alt_body = "This is the text part of the email. ";
        $this->Mail->subject .= ": AltBody + Attachment";
        $this->Mail->useHtml(TRUE);
        
        if (!$this->Mail->addAttachment(__FILE__, "test_attach.txt"))
        {
            $this->assertTrue(FALSE, $this->Mail->error_info);
            return;
        }

        $this->BuildBody();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
        if (is_writable(' . ')) {
            file_put_contents('message.txt', $this->Mail->createHeader() . $this->Mail->createBody());
        } else {
            $this->assertTrue(FALSE, 'Could not write local file - check permissions');
        }
    }    

    function test_MultipleSend() {
        $this->Mail->body = "Sending two messages without keepalive";
        $this->BuildBody();
        $subject = $this->Mail->subject;

        $this->Mail->subject = $subject . ": SMTP 1";
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
        
        $this->Mail->subject = $subject . ": SMTP 2";
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    function test_SendmailSend() {
        $this->Mail->body = "Sending via sendmail";
        $this->BuildBody();
        $subject = $this->Mail->subject;

        $this->Mail->subject = $subject . ": sendmail";
	$this->Mail->useSendmail();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    function test_MailSend() {
        $this->Mail->body = "Sending via mail()";
        $this->BuildBody();
        $subject = $this->Mail->subject;

        $this->Mail->subject = $subject . ": mail()";
	$this->Mail->useMail();
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }

    function test_SmtpKeepAlive() {
        $this->Mail->body = "This was done using the SMTP keep-alive. ";
        $this->BuildBody();
        $subject = $this->Mail->subject;

        $this->Mail->smtp_keep_alive = TRUE;
        $this->Mail->subject = $subject . ": SMTP keep-alive 1";
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
        
        $this->Mail->subject = $subject . ": SMTP keep-alive 2";
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
        $this->Mail->smtpClose();
    }
    
    /**
     * Tests this denial of service attack: 
     *    http://www.cybsec.com/vuln/PHPMailer-DOS.pdf
     */
    function test_DenialOfServiceAttack() {
        $this->Mail->body = "This should no longer cause a denial of service. ";
        $this->BuildBody();
       
        $this->Mail->subject = str_repeat("A", 998);
        $this->assertTrue($this->Mail->send(), $this->Mail->error_info);
    }
    
	function test_Error() {
		$this->Mail->subject .= ": This should be sent"; 
		$this->BuildBody();
		$this->Mail->clearAllRecipients(); // no addresses should cause an error
		$this->assertTrue($this->Mail->isError() == FALSE, "Error found");
		$this->assertTrue($this->Mail->send() == FALSE, "Send succeeded");
		$this->assertTrue($this->Mail->isError(), "No error found");
		$this->assertEquals('You must provide at least one recipient email address. ', $this->Mail->error_info);
		$this->Mail->addAddress($_REQUEST['mail_to']);
		$this->assertTrue($this->Mail->send(), "Send failed");
	}
	
	function test_Addressing() {
		$this->assertFalse($this->Mail->addAddress('a@example..com'), 'Invalid address accepted');
		$this->assertTrue($this->Mail->addAddress('a@example.com'), 'Addressing failed');
		$this->assertFalse($this->Mail->addAddress('a@example.com'), 'Duplicate addressing failed');
		$this->assertTrue($this->Mail->addCc('b@example.com'), 'CC addressing failed');
		$this->assertFalse($this->Mail->addCc('b@example.com'), 'CC duplicate addressing failed');
		$this->assertFalse($this->Mail->addCc('a@example.com'), 'CC duplicate addressing failed (2)');
		$this->assertTrue($this->Mail->addBcc('c@example.com'), 'BCC addressing failed');
		$this->assertFalse($this->Mail->addBcc('c@example.com'), 'BCC duplicate addressing failed');
		$this->assertFalse($this->Mail->addBcc('a@example.com'), 'BCC duplicate addressing failed (2)');
		$this->assertTrue($this->Mail->addReplyTo('a@example.com'), 'Replyto Addressing failed');
		$this->assertFalse($this->Mail->addReplyTo('a@example..com'), 'Invalid Replyto address accepted');
		$this->Mail->clearAddresses();
		$this->Mail->clearCcs();
		$this->Mail->clearBccs();
		$this->Mail->clearReplyTos();
	}

	/**
	* Test language files for missing and excess translations
	* All languages are compared with English
	*/
	function test_Translations() {
		$this->Mail->setLanguage('en');
		$definedStrings = $this->Mail->getTranslations();
		foreach (new DirectoryIterator(' ../language') as $fileInfo) {
			if ($fileInfo->isDot()) continue;
			$matches = array();
			//Only look at language files, ignore anything else in there
			if (preg_match('/^phpmailer\.lang-([a-z_]{2,})\.php$/', $fileInfo->getFilename(), $matches)) {
				$lang = $matches[1]; //Extract language code
				$PHPMAILER_LANG = array(); //Language strings get put in here
				include $fileInfo->getPathname(); //Get language strings
				$missing = array_diff(array_keys($definedStrings), array_keys($PHPMAILER_LANG));
				$extra = array_diff(array_keys($PHPMAILER_LANG), array_keys($definedStrings));
				$this->assertTrue(empty($missing), "Missing translations in $lang: " . implode(', ', $missing));
				$this->assertTrue(empty($extra), "Extra translations in $lang: " . implode(', ', $extra));
			}
		}
	}

	/**
	* Encoding tests
	*/
	function test_Encodings() {
	    $this->Mail->Charset = 'iso-8859-1';
	    $this->assertEquals('=A1Hola!_Se=F1or!', $this->Mail->encodeQ('¡Hola! Señor!', 'text'), 'Q Encoding (text) failed');
	    $this->assertEquals('=A1Hola!_Se=F1or!', $this->Mail->encodeQ('¡Hola! Señor!', 'comment'), 'Q Encoding (comment) failed');
	    $this->assertEquals('=A1Hola!_Se=F1or!', $this->Mail->encodeQ('¡Hola! Señor!', 'phrase'), 'Q Encoding (phrase) failed');
	}
	
	/**
	* Signing tests
	*/
	function test_Signing() {
	    $this->Mail->sign('certfile.txt', 'keyfile.txt', 'password'); //TODO this is not really testing signing, but at least helps coverage
	}

	/**
	* Miscellaneous calls to improve test coverage and some small tests
	*/
	function test_Miscellaneous() {
	    $this->assertEquals('application/pdf', PHPMailer::getMimeTypes('pdf') , 'MIME TYPE lookup failed');
	    $this->Mail->addCustomHeader('SomeHeader: Some Value');
	    $this->Mail->clearCustomHeaders();
	    $this->Mail->clearAttachments();
	    $this->Mail->useHtml(FALSE);
	    $this->Mail->useSmtp();
	    $this->Mail->useMail();
	    $this->Mail->IsSendMail();
   	    $this->Mail->useQmail();
	    $this->Mail->setLanguage('fr');
	    $this->Mail->sender = '';
	    $this->Mail->createHeader();
	    $this->assertFalse($this->Mail->set('x', 'y'), 'Invalid property set succeeded');
	    $this->assertTrue($this->Mail->set('Timeout', 11), 'Valid property set failed');
	    $this->Mail->getFile(__FILE__);
	}
}  
 
/**
* This is a sample form for setting appropriate test values through a browser
* These values can also be set using a file called testbootstrap.php (not in svn) in the same folder as this script
* which is probably more useful if you run these tests a lot
<html>
<body>
<h3>phpmailer Unit Test</h3>
By entering a SMTP hostname it will automatically perform tests with SMTP.

<form name="phpmailer_unit" action=__FILE__ method="get">
<input type="hidden" name="submitted" value="1"/>
From Address: <input type="text" size="50" name="mail_from" value="<?php echo get("mail_from"); ?>"/>
<br/>
To Address: <input type="text" size="50" name="mail_to" value="<?php echo get("mail_to"); ?>"/>
<br/>
Cc Address: <input type="text" size="50" name="mail_cc" value="<?php echo get("mail_cc"); ?>"/>
<br/>
SMTP Hostname: <input type="text" size="50" name="mail_host" value="<?php echo get("mail_host"); ?>"/>
<p/>
<input type="submit" value="Run Test"/>

</form>
</body>
</html>
 */

?>