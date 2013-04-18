<?php
class Smtp {

    public $do_debug;       // the level of debug to perform
    public $do_verp = FALSE;
    public $version         = '5.2.1';

  /////////////////////////////////////////////////
  // PROPERTIES, PRIVATE AND PROTECTED
  /////////////////////////////////////////////////

    private $smtp_conn; // the socket to the server
    private $error;     // error if any on the last call
    private $helo_rply; // the reply the server sent to us for HELO

  /**
   * Initialize the class so that the data is in a known state.
   * @access public
   * @return void
   */
    public function __construct() {
        $this->smtp_conn = 0;
        $this->error = NULL;
        $this->helo_rply = NULL;
        $this->do_debug = 0;
    }

  /////////////////////////////////////////////////
  // CONNECTION FUNCTIONS
  /////////////////////////////////////////////////

    public function connect($host, $port=25, $tval=30) {
        // set the error val to NULL so there is no confusion
        $this->error = NULL;
        // make sure we are __not__ connected
        if ($this->connected()) {
            // already connected, generate error
            $this->error = array('error' => 'Already connected to a server');
            return FALSE;
        }
        // connect to the smtp server
        $this->smtp_conn = @fsockopen($host, $port, $errno, $errstr, $tval);
        // verify we connected properly
        if (empty($this->smtp_conn)) {
            $this->error = array(
                'error'  => 'Failed to connect to server',
                'errno'  => $errno,
                'errstr' => $errstr
            );
            if ($this->do_debug >= 1) {
                echo 'SMTP -> ERROR: ' . $this->error['error'] . ": $errstr ($errno)" . Mailer::CRLF . '<br />';
            }
            return FALSE;
        }
        // SMTP server can take longer to respond, give longer timeout for first read
        // Windows does not have support for this timeout function
        if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
            socket_set_timeout($this->smtp_conn, $tval, 0);
        }
        // get any announcement
        $announce = $this->getLines();
        if ($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $announce . Mailer::CRLF . '<br />';
        }
        return TRUE;
    }

    public function startTls() {
        $this->error = NULL; # to avoid confusion        
        if (!$this->connected()) {
            $this->error = array('error' => 'Called startTls() without being connected');
            return FALSE;
        }        
        fputs($this->smtp_conn, 'STARTTLS' . Mailer::CRLF);        
        $rply = $this->getLines();
        $code = substr($rply, 0, 3);        
        if ($this->do_debug >= 2) {
            echo 'SMTP -> FROM SERVER:' . $rply . Mailer::CRLF . '<br />';
        }        
        if ($code != 220) {
            $this->error = array(
                'error'     => 'STARTTLS not accepted from server',
                'smtp_code' => $code,
                'smtp_msg'  => substr($rply, 4));
            if ($this->do_debug >= 1) {
                echo 'SMTP -> ERROR: ' . $this->error['error'] . ': ' . $rply . Mailer::CRLF . '<br />';
            }
            return FALSE;
        }
        return stream_socket_enable_crypto($this->smtp_conn, TRUE, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    }

    public function authenticate($username, $password) {
        // Start authentication
        fputs($this->smtp_conn, 'AUTH LOGIN' . Mailer::CRLF);        
        $rply = $this->getLines();
        $code = substr($rply,0,3);        
        if ($code != 334) {
            $this->error = array(
                'error'     => 'AUTH not accepted from server',
                'smtp_code' => $code,
                'smtp_msg'  => substr($rply, 4)
            );
            if ($this->do_debug >= 1) {
                echo 'SMTP -> ERROR: ' . $this->error['error'] . ': ' . $rply . Mailer::CRLF . '<br />';
            }
            return FALSE;
        }        
        // Send encoded username
        fputs($this->smtp_conn, base64_encode($username) . Mailer::CRLF);        
        $rply = $this->getLines();
        $code = substr($rply, 0, 3);        
        if ($code != 334) {
            $this->error = array(
                'error' => 'Username not accepted from server',
                'smtp_code' => $code,
                'smtp_msg' => substr($rply, 4));
            if ($this->do_debug >= 1) {
                echo 'SMTP -> ERROR: ' . $this->error['error'] . ': ' . $rply . Mailer::CRLF . '<br />';
            }
            return FALSE;
        }        
        // Send encoded password
        fputs($this->smtp_conn, base64_encode($password) . Mailer::CRLF);        
        $rply = $this->getLines();
        $code = substr($rply, 0, 3);        
        if ($code != 235) {
            $this->error = array(
                'error'     => 'Password not accepted from server',
                'smtp_code' => $code,
                'smtp_msg'  => substr($rply, 4)
            );
            if ($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error['error'] . ": " . $rply . Mailer::CRLF . '<br />';
            }
            return FALSE;
        }        
        return TRUE;
    }

    public function connected() {
        if (!empty($this->smtp_conn)) {
            $sock_status = socket_get_status($this->smtp_conn);
            if ($sock_status['eof']) {
                // the socket is valid but we are not connected
                if ($this->do_debug >= 1) {
                    echo 'SMTP -> NOTICE:' . Mailer::CRLF . 'EOF caught while checking if connected';
                }
                $this->close();
                return FALSE;
            }
            return TRUE; // everything looks good
        }
        return FALSE;
    }

    public function close() {
        $this->error = NULL; // so there is no confusion
        $this->helo_rply = NULL;
        if (!empty($this->smtp_conn)) {
            // close the connection and cleanup
            fclose($this->smtp_conn);
            $this->smtp_conn = 0;
        }
    }

  /////////////////////////////////////////////////
  // SMTP COMMANDS
  /////////////////////////////////////////////////

  /**
   * Issues a data command and sends the msg_data to the server
   * finializing the mail transaction. $msg_data is the message
   * that is to be send with the headers. Each header needs to be
   * on a single line followed by a <CRLF> with the message headers
   * and the message body being seperated by and additional <CRLF>.
   *
   * Implements rfc 821: DATA <CRLF>
   *
   * SMTP CODE INTERMEDIATE: 354
   *     [data]
   *     <CRLF>.<CRLF>
   *     SMTP CODE SUCCESS: 250
   *     SMTP CODE FAILURE: 552,554,451,452
   * SMTP CODE FAILURE: 451,554
   * SMTP CODE ERROR  : 500,501,503,421
   * @access public
   * @return bool
   */
  public function Data($msg_data) {
    $this->error = NULL; // so no confusion is caused

    if (!$this->connected()) {
      $this->error = array(
              'error' => "Called Data() without being connected");
      return FALSE;
    }

    fputs($this->smtp_conn,"DATA" . Mailer::CRLF);

    $rply = $this->getLines();
    $code = substr($rply,0,3);

    if ($this->do_debug >= 2) {
      echo "SMTP -> FROM SERVER:" . $rply . Mailer::CRLF . '<br />';
    }

    if ($code != 354) {
      $this->error =
        array('error' => "DATA command not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if ($this->do_debug >= 1) {
        echo "SMTP -> ERROR: " . $this->error['error'] . ": " . $rply . Mailer::CRLF . '<br />';
      }
      return FALSE;
    }

    /* the server is ready to accept data!
     * according to rfc 821 we should not send more than 1000
     * including the CRLF
     * characters on a single line so we will break the data up
     * into lines by \r and/or \n then if needed we will break
     * each of those into smaller lines to fit within the limit.
     * in addition we will be looking for lines that start with
     * a period ' . ' and append and additional period ' . ' to that
     * line. NOTE: this does not count towards limit.
     */

    // normalize the line breaks so we know the explode works
    $msg_data = str_replace("\r\n","\n", $msg_data);
    $msg_data = str_replace("\r","\n", $msg_data);
    $lines = explode("\n", $msg_data);

    /* we need to find a good way to determine is headers are
     * in the msg_data or if it is a straight msg body
     * currently I am assuming rfc 822 definitions of msg headers
     * and if the first field of the first line (':' sperated)
     * does not contain a space then it _should_ be a header
     * and we can process all lines before a blank "" line as
     * headers.
     */

    $field = substr($lines[0],0,strpos($lines[0],":"));
    $in_headers = FALSE;
    if (!empty($field) && !strstr($field," ")) {
      $in_headers = TRUE;
    }

    $max_line_length = 998; // used below; set here for ease in change

    while (list(, $line) = @each($lines)) {
      $lines_out = NULL;
      if ($line == "" && $in_headers) {
        $in_headers = FALSE;
      }
      // ok we need to break this line up into several smaller lines
      while (strlen($line) > $max_line_length) {
        $pos = strrpos(substr($line,0, $max_line_length)," ");

        // Patch to fix DOS attack
        if (!$pos) {
          $pos = $max_line_length - 1;
          $lines_out[] = substr($line,0, $pos);
          $line = substr($line, $pos);
        } else {
          $lines_out[] = substr($line,0, $pos);
          $line = substr($line, $pos + 1);
        }

        /* if processing headers add a LWSP-char to the front of new line
         * rfc 822 on long msg headers
         */
        if ($in_headers) {
          $line = "\t" . $line;
        }
      }
      $lines_out[] = $line;

      // send the lines to the server
      while (list(, $line_out) = @each($lines_out)) {
        if (strlen($line_out) > 0)
        {
          if (substr($line_out, 0, 1) == " . ") {
            $line_out = " . " . $line_out;
          }
        }
        fputs($this->smtp_conn, $line_out . Mailer::CRLF);
      }
    }

    // message data has been sent
    fputs($this->smtp_conn, Mailer::CRLF . " . " . Mailer::CRLF);

    $rply = $this->getLines();
    $code = substr($rply,0,3);

    if ($this->do_debug >= 2) {
      echo "SMTP -> FROM SERVER:" . $rply . Mailer::CRLF . '<br />';
    }

    if ($code != 250) {
      $this->error =
        array('error' => "DATA not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if ($this->do_debug >= 1) {
        echo "SMTP -> ERROR: " . $this->error['error'] . ": " . $rply . Mailer::CRLF . '<br />';
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Sends the HELO command to the smtp server.
   * This makes sure that we and the server are in
   * the same known state.
   *
   * Implements from rfc 821: HELO <SP> <domain> <CRLF>
   *
   * SMTP CODE SUCCESS: 250
   * SMTP CODE ERROR  : 500, 501, 504, 421
   * @access public
   * @return bool
   */
  public function Hello($host = '') {
    $this->error = NULL; // so no confusion is caused

    if (!$this->connected()) {
      $this->error = array(
            'error' => "Called Hello() without being connected");
      return FALSE;
    }

    // if hostname for HELO was not specified send default
    if (empty($host)) {
      // determine appropriate default to send to server
      $host = "localhost";
    }

    // Send extended hello first (RFC 2821)
    if (!$this->sendHello("EHLO", $host)) {
      if (!$this->sendHello("HELO", $host)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Sends a HELO/EHLO command.
   * @access private
   * @return bool
   */
  private function SendHello($hello, $host) {
    fputs($this->smtp_conn, $hello . " " . $host . Mailer::CRLF);

    $rply = $this->getLines();
    $code = substr($rply,0,3);

    if ($this->do_debug >= 2) {
      echo "SMTP -> FROM SERVER: " . $rply . Mailer::CRLF . '<br />';
    }

    if ($code != 250) {
      $this->error =
        array('error' => $hello . " not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if ($this->do_debug >= 1) {
        echo "SMTP -> ERROR: " . $this->error['error'] . ": " . $rply . Mailer::CRLF . '<br />';
      }
      return FALSE;
    }

    $this->helo_rply = $rply;

    return TRUE;
  }

  /**
   * Starts a mail transaction from the email address specified in
   * $from. Returns TRUE if successful or FALSE otherwise. If True
   * the mail transaction is started and then one or more Recipient
   * commands may be called followed by a Data command.
   *
   * Implements rfc 821: MAIL <SP> FROM:<reverse-path> <CRLF>
   *
   * SMTP CODE SUCCESS: 250
   * SMTP CODE SUCCESS: 552,451,452
   * SMTP CODE SUCCESS: 500,501,421
   * @access public
   * @return bool
   */
  public function Mail($from) {
    $this->error = NULL; // so no confusion is caused

    if (!$this->connected()) {
      $this->error = array(
              'error' => "Called Mail() without being connected");
      return FALSE;
    }

    $useVerp = ($this->do_verp ? "XVERP" : "");
    fputs($this->smtp_conn,"MAIL FROM:<" . $from . ">" . $useVerp . Mailer::CRLF);

    $rply = $this->getLines();
    $code = substr($rply,0,3);

    if ($this->do_debug >= 2) {
      echo "SMTP -> FROM SERVER:" . $rply . Mailer::CRLF . '<br />';
    }

    if ($code != 250) {
      $this->error =
        array('error' => "MAIL not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if ($this->do_debug >= 1) {
        echo "SMTP -> ERROR: " . $this->error['error'] . ": " . $rply . Mailer::CRLF . '<br />';
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Sends the quit command to the server and then closes the socket
   * if there is no error or the $close_on_error argument is TRUE.
   *
   * Implements from rfc 821: QUIT <CRLF>
   *
   * SMTP CODE SUCCESS: 221
   * SMTP CODE ERROR  : 500
   * @access public
   * @return bool
   */
  public function Quit($close_on_error = TRUE) {
    $this->error = NULL; // so there is no confusion

    if (!$this->connected()) {
      $this->error = array(
              'error' => "Called Quit() without being connected");
      return FALSE;
    }

    // send the quit command to the server
    fputs($this->smtp_conn,"quit" . Mailer::CRLF);

    // get any good-bye messages
    $byemsg = $this->getLines();

    if ($this->do_debug >= 2) {
      echo "SMTP -> FROM SERVER:" . $byemsg . Mailer::CRLF . '<br />';
    }

    $rval = TRUE;
    $e = NULL;

    $code = substr($byemsg,0,3);
    if ($code != 221) {
      // use e as a tmp var cause Close will overwrite $this->error
      $e = array('error' => "SMTP server rejected quit command",
                 "smtp_code" => $code,
                 "smtp_rply" => substr($byemsg,4));
      $rval = FALSE;
      if ($this->do_debug >= 1) {
        echo "SMTP -> ERROR: " . $e['error'] . ": " . $byemsg . Mailer::CRLF . '<br />';
      }
    }

    if (empty($e) || $close_on_error) {
      $this->close();
    }

    return $rval;
  }

  /**
   * Sends the command RCPT to the SMTP server with the TO: argument of $to.
   * Returns TRUE if the recipient was accepted FALSE if it was rejected.
   *
   * Implements from rfc 821: RCPT <SP> TO:<forward-path> <CRLF>
   *
   * SMTP CODE SUCCESS: 250,251
   * SMTP CODE FAILURE: 550,551,552,553,450,451,452
   * SMTP CODE ERROR  : 500,501,503,421
   * @access public
   * @return bool
   */
  public function Recipient($to) {
    $this->error = NULL; // so no confusion is caused

    if (!$this->connected()) {
      $this->error = array(
              'error' => "Called Recipient() without being connected");
      return FALSE;
    }

    fputs($this->smtp_conn,"RCPT TO:<" . $to . ">" . Mailer::CRLF);

    $rply = $this->getLines();
    $code = substr($rply,0,3);

    if ($this->do_debug >= 2) {
      echo "SMTP -> FROM SERVER:" . $rply . Mailer::CRLF . '<br />';
    }

    if ($code != 250 && $code != 251) {
      $this->error =
        array('error' => "RCPT not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if ($this->do_debug >= 1) {
        echo "SMTP -> ERROR: " . $this->error['error'] . ": " . $rply . Mailer::CRLF . '<br />';
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Sends the RSET command to abort and transaction that is
   * currently in progress. Returns TRUE if successful FALSE
   * otherwise.
   *
   * Implements rfc 821: RSET <CRLF>
   *
   * SMTP CODE SUCCESS: 250
   * SMTP CODE ERROR  : 500,501,504,421
   * @access public
   * @return bool
   */
  public function Reset() {
    $this->error = NULL; // so no confusion is caused

    if (!$this->connected()) {
      $this->error = array(
              'error' => "Called Reset() without being connected");
      return FALSE;
    }

    fputs($this->smtp_conn,"RSET" . Mailer::CRLF);

    $rply = $this->getLines();
    $code = substr($rply,0,3);

    if ($this->do_debug >= 2) {
      echo "SMTP -> FROM SERVER:" . $rply . Mailer::CRLF . '<br />';
    }

    if ($code != 250) {
      $this->error =
        array('error' => "RSET failed",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if ($this->do_debug >= 1) {
        echo "SMTP -> ERROR: " . $this->error['error'] . ": " . $rply . Mailer::CRLF . '<br />';
      }
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Starts a mail transaction from the email address specified in
   * $from. Returns TRUE if successful or FALSE otherwise. If True
   * the mail transaction is started and then one or more Recipient
   * commands may be called followed by a Data command. This command
   * will send the message to the users terminal if they are logged
   * in and send them an email.
   *
   * Implements rfc 821: SAML <SP> FROM:<reverse-path> <CRLF>
   *
   * SMTP CODE SUCCESS: 250
   * SMTP CODE SUCCESS: 552,451,452
   * SMTP CODE SUCCESS: 500,501,502,421
   * @access public
   * @return bool
   */
  public function SendAndMail($from) {
    $this->error = NULL; // so no confusion is caused

    if (!$this->connected()) {
      $this->error = array(
          'error' => "Called SendAndMail() without being connected");
      return FALSE;
    }

    fputs($this->smtp_conn,"SAML FROM:" . $from . Mailer::CRLF);

    $rply = $this->getLines();
    $code = substr($rply,0,3);

    if ($this->do_debug >= 2) {
      echo "SMTP -> FROM SERVER:" . $rply . Mailer::CRLF . '<br />';
    }

    if ($code != 250) {
      $this->error =
        array('error' => "SAML not accepted from server",
              "smtp_code" => $code,
              "smtp_msg" => substr($rply,4));
      if ($this->do_debug >= 1) {
        echo "SMTP -> ERROR: " . $this->error['error'] . ": " . $rply . Mailer::CRLF . '<br />';
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * This is an optional command for SMTP that this class does not
   * support. This method is here to make the RFC821 Definition
   * complete for this class and __may__ be implimented in the future
   *
   * Implements from rfc 821: TURN <CRLF>
   *
   * SMTP CODE SUCCESS: 250
   * SMTP CODE FAILURE: 502
   * SMTP CODE ERROR  : 500, 503
   * @access public
   * @return bool
   */
  public function Turn() {
    $this->error = array('error' => "This method, TURN, of the SMTP " .
                                    "is not implemented");
    if ($this->do_debug >= 1) {
      echo "SMTP -> NOTICE: " . $this->error['error'] . Mailer::CRLF . '<br />';
    }
    return FALSE;
  }

  /**
  * Get the current error
  * @access public
  * @return array
  */
  public function getError() {
    return $this->error;
  }

  /////////////////////////////////////////////////
  // INTERNAL FUNCTIONS
  /////////////////////////////////////////////////

  /**
   * Read in as many lines as possible
   * either before eof or socket timeout occurs on the operation.
   * With SMTP we can tell if we have more lines to read if the
   * 4th character is '-' symbol. If it is a space then we don't
   * need to read anything else.
   * @access private
   * @return string
   */
  private function get_lines() {
    $data = "";
    while (!feof($this->smtp_conn)) {
      $str = @fgets($this->smtp_conn,515);
      if ($this->do_debug >= 4) {
        echo "SMTP -> get_lines(): \$data was \"$data\"" . Mailer::CRLF . '<br />';
        echo "SMTP -> get_lines(): \$str is \"$str\"" . Mailer::CRLF . '<br />';
      }
      $data .= $str;
      if ($this->do_debug >= 4) {
        echo "SMTP -> get_lines(): \$data is \"$data\"" . Mailer::CRLF . '<br />';
      }
      // if 4th character is a space, we are done reading, break the loop
      if (substr($str,3,1) == " ") { break; }
    }
    return $data;
  }

}

?>
