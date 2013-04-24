<?php
class Smtp {

    const DEFAULT_PORT = 25;
    const COMMAND_OK   = 250;

    public $do_debug;       // the level of debug to perform
    public $do_verp = FALSE;
    public $version = '1.0.0';

    private $smtp_conn; // the socket to the server
    private $errors;     // error if any on the last call
    private $helo_rply; // the reply the server sent to us for HELO
    private $cmd_reply;

    public function __construct($debug=0) {
        $this->smtp_conn = 0;
        $this->errors = array();
        $this->helo_rply = NULL;
        $this->do_debug = $debug;
    }

    public function connect($host, $port=self::DEFAULT_PORT, $tval=30) {
        // set the error val to NULL so there is no confusion
        $this->errors = NULL;
        // make sure we are __not__ connected
        if ($this->connected()) {
            // already connected, generate error
            $this->errors = array('error' => 'Already connected to a server');
            return FALSE;
        }
        // connect to the smtp server
        $this->smtp_conn = @fsockopen($host, $port, $errno, $errstr, $tval);
        // verify we connected properly
        if (empty($this->smtp_conn)) {
            $this->errors = array(
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
            echo 'SMTP -> FROM SERVER:' . $announce . Mailer::CRLF . '<br />';
        }
        return TRUE;
    }

    private function sendCommand($command, $success_code=self::COMMAND_OK, $check_connection=TRUE) {        
        if ($check_connection && !$this->connected()) {
            $this->errors[] = array('error' => 'Trying command: ' . $command . ', but not connected');
            return FALSE;
        }
        fputs($this->smtp_conn, $command . Mailer::CRLF);
        $rply = $this->getLines();
        $code = substr($rply, 0, 3);
        if ($this->do_debug >= 2) {
            echo 'SMTP -> FROM SERVER:' . $rply . Mailer::CRLF . '<br />';
        }
        $success_code = is_array($success_code) ? $success_code : array($success_code);
        $ok = TRUE;
        foreach ($success_code as $sc) {
            if ($code != $sc) {
                $ok = FALSE;
                break;
            }
        }
        if (!$ok) {
            $this->errors[] = array(
                'error'     => 'COMMAND: "' . $command . '" failed',
                'smtp_code' => $code,
                'smtp_msg'  => substr($rply, 4)
            );
            if ($this->do_debug >= 1) {
                echo 'SMTP -> ERROR: ' . $this->error['error'] . ': ' . $rply . Mailer::CRLF . '<br />';
            }
            return FALSE;
        }
        $this->cmd_reply = $rply;
        return TRUE;
    }    

    public function startTls() {
        $result = $this->sendCommand('STARTTLS', 220);
        if ($result) {
            return stream_socket_enable_crypto($this->smtp_conn, TRUE, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
        return $result;        
    }

    public function authenticate($username, $password) {
        // Start authentication
        if ($this->sendCommand('AUTH LOGIN', 334, FALSE)) {
            if ($this->sendCommand(base64_encode($username), 334, FALSE)) {
                return $this->sendCommand(base64_encode($password), 235, FALSE);
            }
        }
        return FALSE;
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
        $this->errors = NULL; // so there is no confusion
        $this->helo_rply = NULL;
        if (!empty($this->smtp_conn)) {
            // close the connection and cleanup
            fclose($this->smtp_conn);
            $this->smtp_conn = 0;
        }
    }

    public function data($msg_data) {
        if ($this->sendCommand('DATA', 354)) {
            $msg_data        = str_replace("\r\n", "\n", $msg_data);
            $msg_data        = str_replace("\r", "\n", $msg_data);
            $lines           = explode("\n", $msg_data);
            $field           = substr($lines[0], 0, strpos($lines[0], ':'));
            $in_headers      = (!empty($field) && !strstr($field, ' '));
            $max_line_length = 998; // used below; set here for ease in change
            while (list(, $line) = @each($lines)) {
                $lines_out = NULL;
                if ($line == '' && $in_headers) {
                    $in_headers = FALSE;
                }
                // ok we need to break this line up into several smaller lines
                while (strlen($line) > $max_line_length) {
                    $pos = strrpos(substr($line, 0, $max_line_length), ' ');
                    // Patch to fix DOS attack
                    if (!$pos) {
                        $pos = $max_line_length - 1;
                        $lines_out[] = substr($line, 0, $pos);
                        $line = substr($line, $pos);
                    } else {
                        $lines_out[] = substr($line, 0, $pos);
                        $line = substr($line, $pos + 1);
                    }
                    /* if processing headers add a LWSP-char to the front of new line
                     * rfc 822 on long msg headers
                     */
                    if ($in_headers) {
                        $line = '    ' . $line;
                    }
                }
                $lines_out[] = $line;
                // send the lines to the server
                while (list(, $line_out) = @each($lines_out)) {
                    if (strlen($line_out) > 0) {
                        if (substr($line_out, 0, 1) == ' . ') {
                            $line_out = ' . ' . $line_out;
                        }
                    }
                    fputs($this->smtp_conn, $line_out . Mailer::CRLF);
                }
            }
            // message data has been sent
            return $this->sendCommand(Mailer::CRLF . ' . ');
        }
        return FALSE;        
    }

    public function hello($host='localhost') {
        $this->errors = NULL; // so no confusion is caused
        if (!$this->connected()) {
            $this->errors = array('error' => 'Called hello() without being connected');
            return FALSE;
        }
        // Send extended hello first (RFC 2821)
        if (!$this->sendHello('EHLO', $host)) {
            if (!$this->sendHello('HELO', $host)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    private function sendHello($hello, $host='localhost') {
        $result = $this->sendCommand("$hello $host", self::COMMAND_OK, FALSE);
        if ($result) {
            $this->helo_rply = $this->cmd_reply;
        }
        return $result;
    }

    public function mail($from) {
        return $this->sendCommand('MAIL FROM:<' . $from . '>' . ($this->do_verp ? 'XVERP' : ''));
    }

    public function quit($close_on_error=TRUE) {
        $result = $this->sendCommand('quit', 221);
        if ($close_on_error && !$result) {
            $this->close();
        }
        return $rval;
    }

    public function recipient($to) {
        return $this->sendCommand('RCPT TO:<' . $to . '>', array(250, 251));
    }

    public function reset() {
        return $this->sendCommand('RSET');
    }

    public function sendAndMail($from) { return $this->sendCommand('SAML FROM:' . $from); }

    public function turn() { return $this->sendCommand('TURN'); }

    public function getErrors() { return $this->errors; }

    private function getLines() {
        $data = '';
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
            if (substr($str, 3, 1) == ' ') { break; }
        }
        return $data;
    }

}

?>
