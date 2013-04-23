<?php
class Mailer {
    /////////////////////////////////////////////////
    // CONSTANTS
    /////////////////////////////////////////////////
    
    const STOP_MESSAGE  = 0; // message only, continue processing
    const STOP_CONTINUE = 1; // message?, likely ok to continue processing
    const STOP_CRITICAL = 2; // message, plus full stop, critical error reached
    
    const PRIORITY_HIGH   = 1;
    const PRIORITY_NORMAL = 3;
    const PRIORITY_LOW    = 5;
    
    public $priority          = 3;
    public $charset           = 'iso-8859-1';
    public $content_type       = 'text/plain';
    public $encoding          = '8bit';
    public $error_info         = '';
    public $from              = 'root@localhost';
    public $from_name          = 'Root User';
    public $sender            = '';
    public $subject           = '';
    public $body              = '';
    public $alt_body           = '';
    
    protected $mime_body       = '';
    protected $mime_header     = '';
    protected $sent_mime_message     = '';
    
    public $word_wrap          = 0;
    public $mailer            = 'mail';
    public $sendmail          = '/usr/sbin/sendmail';
    public $plugin_dir         = '';
    public $confirm_reading_to  = '';
    public $hostname          = '';
    public $message_id         = '';
    
    /////////////////////////////////////////////////
    // PROPERTIES FOR SMTP
    /////////////////////////////////////////////////
    
    public $host          = 'localhost';
    public $helo          = '';
    public $smtp_secure    = '';
    public $smtp_auth      = FALSE;
    public $username      = '';
    public $password      = '';
    public $timeout       = 10;
    public $smtp_debug     = FALSE;
    public $smtp_keep_alive = FALSE;
    public $single_to      = FALSE;
    public $single_to_array = array();
    public $line_ending              = "\n";
    public $dkim_selector   = 'phpmailer';
    public $dkim_identity   = '';
    public $dkim_passphrase   = '';
    public $dkim_domain     = '';
    public $dkim_private    = '';
    public $callback = '';
    public $version         = '5.2.1';
    public $xmailer         = '';
    
    /////////////////////////////////////////////////
    // PROPERTIES, PRIVATE AND PROTECTED
    /////////////////////////////////////////////////
    
    protected   $smtp           = NULL;
    protected   $to             = array();
    protected   $cc             = array();
    protected   $bcc            = array();
    protected   $reply_to       = array();
    protected   $all_recipients = array();
    protected   $attachment     = array();
    protected   $custom_header   = array();
    protected   $message_type   = '';
    protected   $boundary       = array();
    protected   $language       = array();
    protected   $error_count    = 0;
    protected   $sign_cert_file = '';
    protected   $sign_key_file  = '';
    protected   $sign_key_pass  = '';
    protected   $exceptions     = FALSE;
    


    /////////////////////////////////////////////////
    // METHODS, VARIABLES
    /////////////////////////////////////////////////
    
    public function __construct($exceptions=FALSE) {
        $this->exceptions = ($exceptions === TRUE);
    }

    public function isHtml($ishtml=TRUE) {
        $this->content_type = $ishtml ? 'text/html': 'text/plain';
    }

    public function isSmtp() { $this->mailer = 'smtp'; }

    public function isMail() { $this->mailer = 'mail'; }
    
    public function isSendmail() {
        if (!stristr(ini_get('sendmail_path'), 'sendmail')) {
            $this->sendmail = '/var/qmail/bin/sendmail';
        }
        $this->mailer = 'sendmail';
    }    

    public function isQmail() {
        if (stristr(ini_get('sendmail_path'), 'qmail')) {
            $this->sendmail = '/var/qmail/bin/sendmail';
        }
        $this->mailer = 'sendmail';
    }
    
    /////////////////////////////////////////////////
    // METHODS, RECIPIENTS
    /////////////////////////////////////////////////
    
    public function addAddress($address, $name='') {
        return $this->addAnAddress('to', $address, $name);
    }
    
    public function addCC($address, $name='') {
        return $this->addAnAddress('cc', $address, $name);
    }

    public function addBCC($address, $name='') {
        return $this->addAnAddress('bcc', $address, $name);
    }
    
    public function addReplyTo($address, $name='') {
        return $this->addAnAddress('Reply-To', $address, $name);
    }
    
    protected function addAnAddress($kind, $address, $name='') {
        if (!preg_match('/^(to|cc|bcc|Reply-To)$/', $kind)) {
            $this->setError($this->lang('Invalid recipient array') . ': ' . $kind);
            if ($this->exceptions) {
                throw new MailerException('Invalid recipient array: ' . $kind);
            }
            if ($this->smtp_debug) {
                echo $this->lang('Invalid recipient array') . ': ' . $kind;
            }
            return FALSE;
        }
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
        if (!self::validateAddress($address)) {
            $this->setError($this->lang('invalid_address') . ': ' . $address);
            if ($this->exceptions) {
                throw new MailerException($this->lang('invalid_address') . ': ' . $address);
            }
            if ($this->smtp_debug) {
                echo $this->lang('invalid_address') . ': ' . $address;
            }
            return FALSE;
        }
        if ($kind != 'Reply-To') {
            if (!isset($this->all_recipients[strtolower($address)])) {
                array_push($this->$kind, array($address, $name));
                $this->all_recipients[strtolower($address)] = TRUE;
                return TRUE;
            }
        } else {
            if (!array_key_exists(strtolower($address), $this->reply_to)) {
                $this->reply_to[strtolower($address)] = array($address, $name);
                return TRUE;
            }
        }
        return FALSE;
    }

    public function setFrom($address, $name='', $auto=1) {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
        if (!self::validateAddress($address)) {
            $this->setError($this->lang('invalid_address') . ': ' . $address);
            if ($this->exceptions) {
                throw new MailerException($this->lang('invalid_address') . ': ' . $address);
            }
            if ($this->smtp_debug) {
                echo $this->lang('invalid_address') . ': ' . $address;
            }
            return FALSE;
        }
        $this->from = $address;
        $this->from_name = $name;
        if ($auto) {
            if (empty($this->reply_to)) {
                $this->addAnAddress('Reply-To', $address, $name);
            }
            if (empty($this->sender)) {
                $this->sender = $address;
            }
        }
        return TRUE;
    }

    public static function validateAddress($address) {
        if (function_exists('filter_var')) { //Introduced in PHP 5.2
            return (filter_var($address, FILTER_VALIDATE_EMAIL) !== FALSE);
        } else {
            return preg_match('/^(?:[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+\.)*[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+@(?:(?:(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!\.)){0,61}[a-zA-Z0-9_-]?\.)+[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!$)){0,61}[a-zA-Z0-9_]?)|(?:\[(?:(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\]))$/', $address);
        }
    }

  /////////////////////////////////////////////////
  // METHODS, MAIL SENDING
  /////////////////////////////////////////////////

    public function send() {
        try {
            if (!$this->preSend()) return FALSE;
            return $this->postSend();
        } catch (MailerException $e) {
            $this->sent_mime_message = '';
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return FALSE;
        }
    }

    protected function preSend() {
        try {
            $mail_header = "";
            if ((count($this->to) + count($this->cc) + count($this->bcc)) < 1) {
                throw new MailerException($this->lang('provide_address'), self::STOP_CRITICAL);
            }            
            // Set whether the message is multipart/alternative
            if (!empty($this->alt_body)) {
                $this->content_type = 'multipart/alternative';
            }            
            $this->error_count = 0; // reset errors
            $this->setMessageType();
            //Refuse to send an empty message
            if (empty($this->body)) {
                throw new MailerException($this->lang('empty_message'), self::STOP_CRITICAL);
            }            
            $this->mime_header = $this->createHeader();
            $this->mime_body = $this->createBody();            
            // To capture the complete message when using mail(), create
            // an extra header list which CreateHeader() doesn't fold in
            if ($this->mailer == 'mail') {
                if (count($this->to) > 0) {
                    $mail_header .= $this->addrAppend('To', $this->to);
                } else {
                    $mail_header .= $this->headerLine('To', 'undisclosed-recipients:;');
                }
                $mail_header .= $this->headerLine('Subject', $this->encodeHeader($this->secureHeader(trim($this->subject))));
                // if (count($this->cc) > 0) {
                    // $mail_header .= $this->addrAppend("Cc", $this->cc);
                // }
            }            
            // digitally sign with DKIM if enabled
            if ($this->dkim_domain && $this->dkim_private) {
                $header_dkim = $this->dkimAdd($this->mime_header, $this->encodeHeader($this->secureHeader($this->subject)), $this->mime_body);
                $this->mime_header = str_replace("\r\n", "\n", $header_dkim) . $this->mime_header;
            }            
            $this->sent_mime_message = sprintf("%s%s\r\n\r\n%s", $this->mime_header, $mail_header, $this->mime_body);
            return TRUE;        
        } catch (MailerException $e) {
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return FALSE;
        }
    }
    
    protected function postSend() {
        try {
            // Choose the mailer and send through it
            switch($this->mailer) {
                case 'sendmail':
                    return $this->sendmailSend($this->mime_header, $this->mime_body);
                case 'smtp':
                    return $this->smtpSend($this->mime_header, $this->mime_body);
                case 'mail':
                    return $this->mailSend($this->mime_header, $this->mime_body);
                default:
                    return $this->mailSend($this->mime_header, $this->mime_body);
            }        
        } catch (MailerException $e) {
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            if ($this->smtp_debug) {
                echo $e->getMessage() . "\n";
            }
            return FALSE;
        }
    }

    protected function sendmailSend($header, $body) {
        if ($this->sender != '') {
            $sendmail = sprintf("%s -oi -f %s -t", escapeshellcmd($this->sendmail), escapeshellarg($this->sender));
        } else {
            $sendmail = sprintf("%s -oi -t", escapeshellcmd($this->sendmail));
        }
        if ($this->single_to === TRUE) {
            foreach ($this->single_to_array as $key => $val) {
                if (!@$mail = popen($sendmail, 'w')) {
                    throw new MailerException($this->lang('execute') . $this->sendmail, self::STOP_CRITICAL);
                }
                fputs($mail, 'To: ' . $val . "\n");
                fputs($mail, $header);
                fputs($mail, $body);
                $result = pclose($mail);
                // implement call back function if it exists
                $is_sent = ($result == 0) ? 1 : 0;
                $this->doCallback($is_sent, $val, $this->cc, $this->bcc, $this->subject, $body);
                if ($result != 0) {
                    throw new MailerException($this->lang('execute') . $this->sendmail, self::STOP_CRITICAL);
                }
            }
        } else {
            if (!@$mail = popen($sendmail, 'w')) {
                throw new MailerException($this->lang('execute') . $this->sendmail, self::STOP_CRITICAL);
            }
            fputs($mail, $header);
            fputs($mail, $body);
            $result = pclose($mail);
            // implement call back function if it exists
            $is_sent = ($result == 0) ? 1 : 0;
            $this->doCallback($is_sent, $this->to, $this->cc, $this->bcc, $this->subject, $body);
            if ($result != 0) {
                throw new MailerException($this->lang('execute') . $this->sendmail, self::STOP_CRITICAL);
            }
        }
        return TRUE;
    }

    protected function mailSend($header, $body) {
        $to_arr = array();
        foreach ($this->to as $t) {
            $to_arr[] = $this->addrFormat($t);
        }
        $to = implode(', ', $to_arr);        
        $params = empty($this->sender) ? '-oi ' : sprintf("-oi -f %s", $this->sender);
        if ($this->sender != '' && !ini_get('safe_mode')) {
            $old_from = ini_get('sendmail_from');
            ini_set('sendmail_from', $this->sender);
            if ($this->single_to === TRUE && count($to_arr) > 1) {
                foreach ($to_arr as $key => $val) {
                    $rt = @mail($val, $this->encodeHeader($this->secureHeader($this->subject)), $body, $header, $params);
                    // implement call back function if it exists
                    $is_sent = ($rt == 1) ? 1 : 0;
                    $this->doCallback($is_sent, $val, $this->cc, $this->bcc, $this->subject, $body);
                }
            } else {
                $rt = @mail($to, $this->encodeHeader($this->secureHeader($this->subject)), $body, $header, $params);
                // implement call back function if it exists
                $is_sent = ($rt == 1) ? 1 : 0;
                $this->doCallback($is_sent, $to, $this->cc, $this->bcc, $this->subject, $body);
            }
        } else {
            if ($this->single_to === TRUE && count($to_arr) > 1) {
                foreach ($to_arr as $key => $val) {
                    $rt = @mail($val, $this->encodeHeader($this->secureHeader($this->subject)), $body, $header, $params);
                    // implement call back function if it exists
                    $is_sent = ($rt == 1) ? 1 : 0;
                    $this->doCallback($is_sent, $val, $this->cc, $this->bcc, $this->subject, $body);
                }
            } else {
                $rt = @mail($to, $this->encodeHeader($this->secureHeader($this->subject)), $body, $header, $params);
                // implement call back function if it exists
                $is_sent = ($rt == 1) ? 1 : 0;
                $this->doCallback($is_sent, $to, $this->cc, $this->bcc, $this->subject, $body);
            }
        }
        if (isset($old_from)) {
            ini_set('sendmail_from', $old_from);
        }
        if (!$rt) {
            throw new MailerException($this->lang('instantiate'), self::STOP_CRITICAL);
        }
        return TRUE;
    }

    protected function smtpSend($header, $body) {
        require_once $this->plugin_dir . 'class.smtp.php'; // put this in autoload
        $bad_rcpt = array();        
        if (!$this->smtpConnect()) {
            throw new MailerException($this->lang('smtp_connect_failed'), self::STOP_CRITICAL);
        }
        $smtp_from = ($this->sender == '') ? $this->from : $this->sender;
        if (!$this->smtp->Mail($smtp_from)) {
            throw new MailerException($this->lang('from_failed') . $smtp_from, self::STOP_CRITICAL);
        }        
        // Attempt to send attach all recipients
        foreach ($this->to as $to) {
            if (!$this->smtp->recipient($to[0])) {
                $bad_rcpt[] = $to[0];
                // implement call back function if it exists
                $is_sent = 0;
                $this->doCallback($is_sent, $to[0], '', '', $this->subject, $body);
            } else {
                // implement call back function if it exists
                $is_sent = 1;
                $this->doCallback($is_sent, $to[0], '', '', $this->subject, $body);
            }
        }
        foreach ($this->cc as $cc) {
            if (!$this->smtp->recipient($cc[0])) {
                $bad_rcpt[] = $cc[0];
                // implement call back function if it exists
                $is_sent = 0;
                $this->doCallback($is_sent, '', $cc[0], '', $this->subject, $body);
            } else {
                // implement call back function if it exists
                $is_sent = 1;
                $this->doCallback($is_sent, '', $cc[0], '', $this->subject, $body);
            }
        }
        foreach ($this->bcc as $bcc) {
            if (!$this->smtp->recipient($bcc[0])) {
                $bad_rcpt[] = $bcc[0];
                // implement call back function if it exists
                $is_sent = 0;
                $this->doCallback($is_sent, '', '', $bcc[0], $this->subject, $body);
            } else {
                // implement call back function if it exists
                $is_sent = 1;
                $this->doCallback($is_sent, '', '', $bcc[0], $this->subject, $body);
            }
        }        
        if (count($bad_rcpt) > 0) { //Create error message for any bad addresses
            $bad_addresses = implode(', ', $bad_rcpt);
            throw new MailerException($this->lang('recipients_failed') . $bad_addresses);
        }
        if (!$this->smtp->data($header . $body)) {
            throw new MailerException($this->lang('data_not_accepted'), self::STOP_CRITICAL);
        }
        if ($this->smtp_keep_alive == TRUE) {
            $this->smtp->reset();
        }
        return TRUE;
    }

    public function smtpConnect() {
        if (is_NULL($this->smtp)) {
            $this->smtp = new Smtp();
        }        
        $this->smtp->do_debug = $this->smtp_debug;
        $hosts = explode(';', $this->host);
        $index = 0;
        $connection = $this->smtp->connected();        
        // Retry while there is no connection
        try {
            while ($index < count($hosts) && !$connection) {
                $hostinfo = array();
                if (preg_match('/^(.+):([0-9]+)$/', $hosts[$index], $hostinfo)) {
                    $host = $hostinfo[1];
                    $port = $hostinfo[2];
                } else {
                    $host = $hosts[$index];
                    $port = Smtp::DEFAULT_PORT;
                }                
                $tls = ($this->smtp_secure == 'tls');
                $ssl = ($this->smtp_secure == 'ssl');                
                if ($this->smtp->connect(($ssl ? 'ssl://':'') . $host, $port, $this->timeout)) {                
                    $hello = ($this->helo != '' ? $this->helo : $this->serverHostname());
                    $this->smtp->hello($hello);                
                    if ($tls) {
                        if (!$this->smtp->startTls()) {
                            throw new MailerException($this->lang('tls'));
                        }                
                        //We must resend HELO after tls negotiation
                        $this->smtp->hello($hello);
                    }                    
                    $connection = TRUE;
                    if ($this->smtp_auth) {
                        if (!$this->smtp->authenticate($this->username, $this->password)) {
                            throw new MailerException($this->lang('authenticate'));
                        }
                    }
                }
                $index++;
                if (!$connection) {
                  throw new MailerException($this->lang('connect_host'));
                }
            }
        } catch (MailerException $e) {
            $this->smtp->reset();
            if ($this->exceptions) {
                throw $e;
            }
        }
        return TRUE;
    }

    public function smtpClose() {
        if (!is_NULL($this->smtp)) {
            if ($this->smtp->connected()) {
                $this->smtp->quit();
                $this->smtp->close();
            }
        }
    }

    public function setLanguage($langcode='en', $lang_path='language/') {
        //Define full set of translatable strings
        $PHPMAILER_LANG = array(
            'provide_address' => 'You must provide at least one recipient email address. ',
            'mailer_not_supported' => ' mailer is not supported. ',
            'execute' => 'Could not execute: ',
            'instantiate' => 'Could not instantiate mail function. ',
            'authenticate' => 'SMTP Error: Could not authenticate. ',
            'from_failed' => 'The following From address failed: ',
            'recipients_failed' => 'SMTP Error: The following recipients failed: ',
            'data_not_accepted' => 'SMTP Error: Data not accepted. ',
            'connect_host' => 'SMTP Error: Could not connect to SMTP host. ',
            'file_access' => 'Could not access file: ',
            'file_open' => 'File Error: Could not open file: ',
            'encoding' => 'Unknown encoding: ',
            'signing' => 'Signing Error: ',
            'smtp_error' => 'SMTP server error: ',
            'empty_message' => 'Message body empty',
            'invalid_address' => 'Invalid address',
            'variable_set' => 'Cannot set or reset variable: '
        );
        //Overwrite language-specific strings. This way we'll never have missing translations - no more "language string failed to load"!
        $l = TRUE;
        if ($langcode != 'en') { //There is no English translation file
            $l = @include $lang_path. 'phpmailer.lang-' . $langcode. ' .php';
        }
        $this->language = $PHPMAILER_LANG;
        return ($l == TRUE); //Returns FALSE if language not found
    }

    public function GetTranslations() { return $this->language; }

  /////////////////////////////////////////////////
  // METHODS, MESSAGE CREATION
  /////////////////////////////////////////////////

    public function addrAppend($type, $addr) {
        $addr_str = $type . ': ';
        $addresses = array();
        foreach ($addr as $a) {
            $addresses[] = $this->addrFormat($a);
        }
        $addr_str .= implode(', ', $addresses);
        $addr_str .= $this->line_ending;        
        return $addr_str;
    }

    public function addrFormat($addr) {
        return empty($addr[1]) ? $this->secureHeader($addr[0]) : 
               $this->encodeHeader($this->secureHeader($addr[1]), 'phrase') . ' <' . $this->secureHeader($addr[0]) . '>';
    }

    public function wrapText($message, $length, $qp_mode=FALSE) {
        $soft_break = ($qp_mode) ? sprintf(" =%s", $this->line_ending) : $this->line_ending;
        // If utf-8 encoding is used, we will need to make sure we don't
        // split multibyte characters when we wrap
        $is_utf8 = (strtolower($this->charset) == 'utf-8');        
        $message = $this->fixEol($message);
        if (substr($message, -1) == $this->line_ending) {
            $message = substr($message, 0, -1);
        }        
        $line = explode($this->line_ending, $message);
        $message = '';
        for ($i = 0 ;$i < count($line); $i++) {
            $line_part = explode(' ', $line[$i]);
            $buf = '';
            for ($e = 0; $e < count($line_part); $e++) {
                $word = $line_part[$e];
                if ($qp_mode && (strlen($word) > $length)) {
                    $space_left = $length - strlen($buf) - 1;
                    if ($e != 0) {
                        if ($space_left > 20) {
                            $len = $space_left;
                            if ($is_utf8) {
                                $len = $this->utf8CharBoundary($word, $len);
                            } elseif (substr($word, $len - 1, 1) == "=") {
                                $len--;
                            } elseif (substr($word, $len - 2, 1) == "=") {
                                $len -= 2;
                            }
                            $part = substr($word, 0, $len);
                            $word = substr($word, $len);
                            $buf .= ' ' . $part;
                            $message .= $buf . sprintf("=%s", $this->line_ending);
                        } else {
                            $message .= $buf . $soft_break;
                        }
                        $buf = '';
                    }
                    while (strlen($word) > 0) {
                        $len = $length;
                        if ($is_utf8) {
                            $len = $this->utf8CharBoundary($word, $len);
                        } elseif (substr($word, $len - 1, 1) == '=') {
                            $len--;
                        } elseif (substr($word, $len - 2, 1) == '=') {
                            $len -= 2;
                        }
                        $part = substr($word, 0, $len);
                        $word = substr($word, $len);                        
                        if (strlen($word) > 0) {
                            $message .= $part . sprintf("=%s", $this->line_ending);
                        } else {
                            $buf = $part;
                        }
                    }
                } else {
                    $buf_o = $buf;
                    $buf .= ($e == 0) ? $word : (' ' . $word);                    
                    if (strlen($buf) > $length and $buf_o != '') {
                        $message .= $buf_o . $soft_break;
                        $buf = $word;
                    }
                }
            }
            $message .= $buf . $this->line_ending;
        }        
        return $message;
    }

    public function utf8CharBoundary($encoded_text, $maxlength) {
        $found_split_pos = FALSE;
        $look_back = 3;
        while (!$found_split_pos) {
            $last_chunk = substr($encoded_text, $maxlength - $look_back, $look_back);
            $encoded_char_pos = strpos($last_chunk, "=");
            if ($encoded_char_pos !== FALSE) {
                // Found start of encoded character byte within $look_back block.
                // Check the encoded byte value (the 2 chars after the '=')
                $hex = substr($encoded_text, $maxlength - $look_back + $encoded_char_pos + 1, 2);
                $dec = hexdec($hex);
                if ($dec < 128) { // Single byte character.
                    // If the encoded char was found at pos 0, it will fit
                    // otherwise reduce maxLength to start of the encoded char
                    $maxlength = ($encoded_char_pos == 0) ? $maxlength :
                    $maxlength - ($look_back - $encoded_char_pos);
                    $found_split_pos = TRUE;
                } elseif ($dec >= 192) { // First byte of a multi byte character
                    // Reduce maxLength to split at start of character
                    $maxlength = $maxlength - ($look_back - $encoded_char_pos);
                    $found_split_pos = TRUE;
                } elseif ($dec < 192) { // Middle byte of a multi byte character, look further back
                    $look_back += 3;
                }
            } else {
                // No encoded character found
                $found_split_pos = TRUE;
            }
        }
        return $maxlength;
    }

    public function setWordWrap() {
        if ($this->word_wrap < 1) {
            return;
        }    
        switch($this->message_type) {
            case 'alt':
            case 'alt_inline':
            case 'alt_attach':
            case 'alt_inline_attach':
                $this->alt_body = $this->wrapText($this->alt_body, $this->word_wrap);
                break;
            default:
                $this->body = $this->wrapText($this->body, $this->word_wrap);
                break;
        }
    }

    public function createHeader() {
        $result = '';        
        // Set the boundaries
        $uniq_id = md5(uniqid(time()));
        $this->boundary[1] = 'b1_' . $uniq_id;
        $this->boundary[2] = 'b2_' . $uniq_id;
        $this->boundary[3] = 'b3_' . $uniq_id;        
        $result .= $this->headerLine('Date', self::rfcDate());
        $result .= ($this->sender == '') ? $this->headerLine('Return-Path', trim($this->from)) : $this->headerLine('Return-Path', trim($this->sender));       
        // To be created automatically by mail()
        if ($this->mailer != 'mail') {
            if ($this->single_to === TRUE) {
                foreach ($this->to as $t) {
                    $this->single_to_array[] = $this->addrFormat($t);
                }
            } else {
                if (count($this->to) > 0) {
                    $result .= $this->addrAppend('To', $this->to);
                } elseif (count($this->cc) == 0) {
                    $result .= $this->headerLine('To', 'undisclosed-recipients:;');
                }
            }
        }        
        $from = array();
        $from[0][0] = trim($this->from);
        $from[0][1] = $this->from_name;
        $result .= $this->addrAppend('From', $from);        
        // sendmail and mail() extract Cc from the header before sending
        if (count($this->cc) > 0) {
            $result .= $this->addrAppend('Cc', $this->cc);
        }        
        // sendmail and mail() extract Bcc from the header before sending
        if ((($this->mailer == 'sendmail') || ($this->mailer == 'mail')) && (count($this->bcc) > 0)) {
            $result .= $this->addrAppend('Bcc', $this->bcc);
        }        
        if (count($this->reply_to) > 0) {
            $result .= $this->addrAppend('Reply-To', $this->reply_to);
        }        
        // mail() sets the subject itself
        if ($this->mailer != 'mail') {
          $result .= $this->headerLine('Subject', $this->encodeHeader($this->secureHeader($this->subject)));
        }        
        if ($this->message_id != '') {
            $result .= $this->headerLine('Message-ID', $this->message_id);
        } else {
            $result .= sprintf("Message-ID: <%s@%s>%s", $uniq_id, $this->serverHostname(), $this->line_ending);
        }
        $result .= $this->headerLine('X-Priority', $this->priority);
        if ($this->xmailer) {
            $result .= $this->headerLine('X-Mailer', $this->xmailer);
        } else {
            $result .= $this->headerLine('X-Mailer', 'PHPMailer ' . $this->version. ' (http://code.google.com/a/apache-extras.org/p/phpmailer/)');
        }        
        if ($this->confirm_reading_to != '') {
            $result .= $this->headerLine('Disposition-Notification-To', '<' . trim($this->confirm_reading_to) . '>');
        }        
        // Add custom headers
        for($index = 0; $index < count($this->custom_header); $index++) {
            $result .= $this->headerLine(trim($this->custom_header[$index][0]), $this->encodeHeader(trim($this->custom_header[$index][1])));
        }
        if (!$this->sign_key_file) {
            $result .= $this->headerLine('MIME-Version', '1.0');
            $result .= $this->getMailMime();
        }        
        return $result;
    }

    public function getMailMime() {
        $result = '';
        switch($this->message_type) {
            case 'plain':
                $result .= $this->headerLine('Content-Transfer-Encoding', $this->encoding);
                $result .= $this->textLine('Content-Type: ' . $this->content_type . '; charset=' . $this->charset);
                break;
            case 'inline':
                $result .= $this->headerLine('Content-Type', 'multipart/related;');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            case 'attach':
            case 'inline_attach':
            case 'alt_attach':
            case 'alt_inline_attach':
                $result .= $this->headerLine('Content-Type', 'multipart/mixed;');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            case 'alt':
            case 'alt_inline':
                $result .= $this->headerLine('Content-Type', 'multipart/alternative;');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
        }        
        if ($this->mailer != 'mail') {
            $result .= $this->line_ending . $this->line_ending;
        }        
        return $result;
    }

    public function getSentMimeMessage() { return $this->sent_mime_message; }

    public function createBody() {
        $body = '';
        if ($this->sign_key_file) {
            $body .= $this->getMailMime();
        }
        $this->setWordWrap();
        switch($this->message_type) {
            case 'plain':
                $body .= $this->encodeString($this->body, $this->encoding);
                break;
            case 'inline':
                $body .= $this->getBoundary($this->boundary[1], '', '', '');
                $body .= $this->encodeString($this->body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->attachAll('inline', $this->boundary[1]);
                break;
            case 'attach':
                $body .= $this->getBoundary($this->boundary[1], '', '', '');
                $body .= $this->encodeString($this->body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'inline_attach':
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', 'multipart/related;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= $this->line_ending;
                $body .= $this->getBoundary($this->boundary[2], '', '', '');
                $body .= $this->encodeString($this->body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->attachAll('inline', $this->boundary[2]);
                $body .= $this->line_ending;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'alt':
                $body .= $this->getBoundary($this->boundary[1], '', 'text/plain', '');
                $body .= $this->encodeString($this->alt_body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->getBoundary($this->boundary[1], '', 'text/html', '');
                $body .= $this->encodeString($this->body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->endBoundary($this->boundary[1]);
                break;
            case 'alt_inline':
                $body .= $this->getBoundary($this->boundary[1], '', 'text/plain', '');
                $body .= $this->encodeString($this->alt_body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', 'multipart/related;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= $this->line_ending;
                $body .= $this->getBoundary($this->boundary[2], '', 'text/html', '');
                $body .= $this->encodeString($this->body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->attachAll('inline', $this->boundary[2]);
                $body .= $this->line_ending;
                $body .= $this->endBoundary($this->boundary[1]);
                break;
            case 'alt_attach':
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', 'multipart/alternative;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= $this->line_ending;
                $body .= $this->getBoundary($this->boundary[2], '', 'text/plain', '');
                $body .= $this->encodeString($this->alt_body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->getBoundary($this->boundary[2], '', 'text/html', '');
                $body .= $this->encodeString($this->body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->endBoundary($this->boundary[2]);
                $body .= $this->line_ending;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'alt_inline_attach':
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', 'multipart/alternative;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= $this->line_ending;
                $body .= $this->getBoundary($this->boundary[2], '', 'text/plain', '');
                $body .= $this->encodeString($this->alt_body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->textLine("--" . $this->boundary[2]);
                $body .= $this->headerLine('Content-Type', 'multipart/related;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[3] . '"');
                $body .= $this->line_ending;
                $body .= $this->getBoundary($this->boundary[3], '', 'text/html', '');
                $body .= $this->encodeString($this->body, $this->encoding);
                $body .= $this->line_ending . $this->line_ending;
                $body .= $this->attachAll('inline', $this->boundary[3]);
                $body .= $this->line_ending;
                $body .= $this->endBoundary($this->boundary[2]);
                $body .= $this->line_ending;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
        }
        if ($this->isError()) {
            $body = '';
        } elseif ($this->sign_key_file) {
            try {
                $file = tempnam('', 'mail');
                file_put_contents($file, $body); //TODO check this worked
                $signed = tempnam('', 'signed');
                if (@openssl_pkcs7_sign($file, $signed, 'file://' . $this->sign_cert_file, array('file://' . $this->sign_key_file, $this->sign_key_pass), NULL)) {
                    @unlink($file);
                    $body = file_get_contents($signed);
                    @unlink($signed);
                } else {
                    @unlink($file);
                    @unlink($signed);
                    throw new MailerException($this->lang('signing') .openssl_error_string());
                }
            } catch (MailerException $e) {
                $body = '';
                if ($this->exceptions) {
                    throw $e;
                }
            }
        }
        return $body;
    }

    protected function getBoundary($boundary, $charset, $content_type, $encoding) {
        $result = '';
        if ($charset == '') {
            $charset = $this->charset;
        }
        if ($content_type == '') {
            $content_type = $this->content_type;
        }
        if ($encoding == '') {
            $encoding = $this->encoding;
        }
        $result .= $this->textLine('--' . $boundary);
        $result .= sprintf("Content-Type: %s; charset=%s", $content_type, $charset);
        $result .= $this->line_ending;
        $result .= $this->headerLine('Content-Transfer-Encoding', $encoding);
        $result .= $this->line_ending;        
        return $result;
    }

    protected function endBoundary($boundary) {
        return $this->line_ending . '--' . $boundary . '--' . $this->line_ending;
    }

    protected function setMessageType() {
        $this->message_type = array();
        if ($this->alternativeExists()) { $this->message_type[] = 'alt'; }
        if ($this->inlineImageExists()) { $this->message_type[] = 'inline'; }
        if ($this->attachmentExists()) { $this->message_type[] = 'attach'; }
        $this->message_type = implode('_', $this->message_type);
        if ($this->message_type == '') { $this->message_type = 'plain'; }
    }

    public function headerLine($name, $value) {
        return $name . ': ' . $value . $this->line_ending;
    }

    public function textLine($value) { return $value . $this->line_ending; }

  /////////////////////////////////////////////////
  // CLASS METHODS, ATTACHMENTS
  /////////////////////////////////////////////////

    public function addAttachment($path, $name='', $encoding='base64', $type='application/octet-stream') {
        try {
            if (!@is_file($path)) {
                throw new MailerException($this->lang('file_access') . $path, self::STOP_CONTINUE);
            }
            $filename = basename($path);
            if ($name == '') {
                $name = $filename;
            }            
            $this->attachment[] = array(
                0 => $path,
                1 => $filename,
                2 => $name,
                3 => $encoding,
                4 => $type,
                5 => FALSE,  // isStringAttachment
                6 => 'attachment',
                7 => 0
            );        
        } catch (MailerException $e) {
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            if ($this->smtp_debug) {
                echo $e->getMessage() . "\n";
            }
            if ($e->getCode() == self::STOP_CRITICAL) {
                return FALSE;
            }
        }
        return TRUE;
    }

    public function getAttachments() { return $this->attachment; }

    protected function attachAll($disposition_type, $boundary) {
        // Return text of body
        $mime     = array();
        $cid_uniq = array();
        $incl     = array();        
        // Add all attachments
        foreach ($this->attachment as $attachment) {
            // CHECK IF IT IS A VALID DISPOSITION_FILTER
            if ($attachment[6] == $disposition_type) {
                // Check for string attachment
                $bstring = $attachment[5];
                if ($bstring) {
                    $string = $attachment[0];
                } else {
                    $path = $attachment[0];
                }        
                $inclhash = md5(serialize($attachment));
                if (in_array($inclhash, $incl)) { continue; }
                $incl[]      = $inclhash;
                $filename    = $attachment[1];
                $name        = $attachment[2];
                $encoding    = $attachment[3];
                $type        = $attachment[4];
                $disposition = $attachment[6];
                $cid         = $attachment[7];
                if ($disposition == 'inline' && isset($cid_uniq[$cid])) { continue; }
                $cid_uniq[$cid] = TRUE;        
                $mime[] = sprintf("--%s%s", $boundary, $this->line_ending);
                $mime[] = sprintf("Content-Type: %s; name=\"%s\"%s", $type, $this->encodeHeader($this->secureHeader($name)), $this->line_ending);
                $mime[] = sprintf("Content-Transfer-Encoding: %s%s", $encoding, $this->line_ending);        
                if ($disposition == 'inline') {
                    $mime[] = sprintf("Content-ID: <%s>%s", $cid, $this->line_ending);
                }        
                $mime[] = sprintf("Content-Disposition: %s; filename=\"%s\"%s", $disposition, $this->encodeHeader($this->secureHeader($name)), $this->line_ending . $this->line_ending);        
                // Encode as string attachment
                if ($bstring) {
                    $mime[] = $this->encodeString($string, $encoding);
                    if ($this->isError()) {
                        return '';
                    }
                    $mime[] = $this->line_ending . $this->line_ending;
                } else {
                    $mime[] = $this->encodeFile($path, $encoding);
                    if ($this->isError()) {
                        return '';
                    }
                    $mime[] = $this->line_ending . $this->line_ending;
                }
            }
        }        
        $mime[] = sprintf("--%s--%s", $boundary, $this->line_ending);        
        return implode('', $mime);
    }

    protected function encodeFile($path, $encoding='base64') {
        try {
            if (!is_readable($path)) {
                throw new MailerException($this->lang('file_open') . $path, self::STOP_CONTINUE);
            }
            if (function_exists('get_magic_quotes')) {
                function get_magic_quotes() {
                    return FALSE;
                }
            }
            $magic_quotes = get_magic_quotes_runtime();
            if ($magic_quotes) {
                if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                    set_magic_quotes_runtime(0);
                } else {
                    ini_set('magic_quotes_runtime', 0); 
                }
            }
            $file_buffer = file_get_contents($path);
            $file_buffer = $this->encodeString($file_buffer, $encoding);
            if ($magic_quotes) {
                if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                    set_magic_quotes_runtime($magic_quotes);
                } else {
                    ini_set('magic_quotes_runtime', $magic_quotes); 
                }
            }
            return $file_buffer;
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            return '';
        }
    }

    public function encodeString($str, $encoding='base64') {
        $encoded = '';
        switch(strtolower($encoding)) {
            case 'base64':
                $encoded = chunk_split(base64_encode($str), 76, $this->line_ending);
                break;
            case '7bit':
            case '8bit':
                $encoded = $this->fixEol($str);
                //Make sure it ends with a line break
                if (substr($encoded, -(strlen($this->line_ending))) != $this->line_ending) {
                    $encoded .= $this->line_ending;
                }  
                break;
            case 'binary':
                $encoded = $str;
                break;
            case 'quoted-printable':
                $encoded = $this->encodeQp($str);
                break;
            default:
                $this->setError($this->lang('encoding') . $encoding);
                break;
        }
        return $encoded;
    }

    public function EncodeHeader($str, $position = 'text') {
        $x = 0;
        switch (strtolower($position)) {
            case 'phrase':
                if (!preg_match('/[\200-\377]/', $str)) {
                    // Can't use addslashes as we don't know what value has magic_quotes_sybase
                    $encoded = addcslashes($str, "\0..\37\177\\\"");
                    return (($str == $encoded) && !preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $str)) ?
                           $encoded : "\"$encoded\"";
                }
                $x = preg_match_all('/[^\040\041\043-\133\135-\176]/', $str, $matches);
                break;
            case 'comment':
                $x = preg_match_all('/[()"]/', $str, $matches);
                // Fall-through
            case 'text':
            default:
                $x += preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $str, $matches);
                break;
        }        
        if ($x == 0) {
            return $str;
        }        
        $maxlen = 75 - 7 - strlen($this->charset);
        // Try to select the encoding which should produce the shortest output
        if (strlen($str) / 3 < $x) {
            $encoding = 'B';
            if (function_exists('mb_strlen') && $this->hasMultiBytes($str)) {
                // Use a custom function which correctly encodes and wraps long
                // multibyte strings without breaking lines within a character
                $encoded = $this->base64EncodeWrapMb($str);
            } else {
                $encoded = base64_encode($str);
                $maxlen -= $maxlen % 4;
                $encoded = trim(chunk_split($encoded, $maxlen, "\n"));
            }
        } else {
            $encoding = 'Q';
            $encoded = $this->encodeQ($str, $position);
            $encoded = $this->wrapText($encoded, $maxlen, TRUE);
            $encoded = str_replace('=' . $this->line_ending, "\n", trim($encoded));
        }        
        $encoded = preg_replace('/^(.*)$/m', " =?" . $this->charset. "?$encoding?\\1?=", $encoded);
        $encoded = trim(str_replace("\n", $this->line_ending, $encoded));        
        return $encoded;
    }
    
    public function hasMultiBytes($str) {
        if (function_exists('mb_strlen')) {
            return (strlen($str) > mb_strlen($str, $this->charset));
        } else { // Assume no multibytes (we can't handle without mbstring functions anyway)
            return FALSE;
        }
    }

    public function base64EncodeWrapMb($str) {
        $start     = '=?' . $this->charset. '?B?';
        $end       = '?=';
        $encoded   = '';        
        $mb_length = mb_strlen($str, $this->charset);
        // Each line must have length <= 75, including $start and $end
        $length = 75 - strlen($start) - strlen($end);
        // Average multi-byte ratio
        $ratio = $mb_length / strlen($str);
        // Base64 has a 4:3 ratio
        $offset = $avg_length = floor($length * $ratio * .75);        
        for ($i = 0; $i < $mb_length; $i += $offset) {
            $look_back = 0;        
            do {
                $offset = $avg_length - $look_back;
                $chunk = mb_substr($str, $i, $offset, $this->charset);
                $chunk = base64_encode($chunk);
                $look_back++;
            }
            while (strlen($chunk) > $length);            
            $encoded .= $chunk . $this->line_ending;
        }        
        // Chomp the last linefeed
        $encoded = substr($encoded, 0, -strlen($this->line_ending));
        return $encoded;
    }

    public function encodeQpPhp($input='', $line_max=76, $space_conv=FALSE) {
        $hex    = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');
        $lines  = preg_split('/(?:\r\n|\r|\n)/', $input);
        $eol    = "\r\n";
        $escape = '=';
        $output = '';
        while (list(, $line) = each($lines)) {
            $linlen = strlen($line);
            $newline = '';
            for($i = 0; $i < $linlen; $i++) {
                $c = substr( $line, $i, 1 );
                $dec = ord( $c );
                if (($i == 0) && ($dec == 46)) { // convert first point in the line into =2E
                    $c = '=2E';
                }
                if ($dec == 32) {
                    if ($i == ( $linlen - 1)) { // convert space at eol only
                        $c = '=20';
                    } elseif ($space_conv) {
                        $c = '=20';
                    }
                } elseif (($dec == 61) || ($dec < 32 ) || ($dec > 126)) { // always encode "\t", which is *not* required
                    $h2 = floor($dec / 16);
                    $h1 = floor($dec % 16);
                    $c = $escape . $hex[$h2] . $hex[$h1];
                }
                if ((strlen($newline) + strlen($c)) >= $line_max) { // CRLF is not counted
                    $output .= $newline . $escape . $eol; //  soft line break; " =\r\n" is okay
                    $newline = '';
                    // check if newline first character will be point or not
                    if ($dec == 46) {
                        $c = '=2E';
                    }
                }
                $newline .= $c;
            } // end of for
            $output .= $newline . $eol;
        } // end of while
        return $output;
    }

    public function encodeQp($string, $line_max=76, $space_conv=FALSE) {
        if (function_exists('quoted_printable_encode')) { //Use native function if it's available (>= PHP5.3)
            return quoted_printable_encode($string);
        }
        $filters = stream_get_filters();
        if (!in_array('convert.*', $filters)) { //Got convert stream filter?
            return $this->encodeQpPhp($string, $line_max, $space_conv); //Fall back to old implementation
        }
        $fp = fopen('php://temp/', 'r+');
        $string = preg_replace('/\r\n?/', $this->line_ending, $string); //Normalise line breaks
        $params = array('line-length' => $line_max, 'line-break-chars' => $this->line_ending);
        $s = stream_filter_append($fp, 'convert.quoted-printable-encode', STREAM_FILTER_READ, $params);
        fputs($fp, $string);
        rewind($fp);
        $out = stream_get_contents($fp);
        stream_filter_remove($s);
        $out = preg_replace('/^\./m', '=2E', $out); //Encode . if it is first char on a line, workaround for bug in Exchange
        fclose($fp);
        return $out;
    }

    public function encodeQ($str, $position='text') {
        // There should not be any EOL in the string
        $encoded = preg_replace('/[\r\n]*/', '', $str);        
        switch (strtolower($position)) {
            case 'phrase':
                $encoded = preg_replace("/([^A-Za-z0-9!*+\/ -])/e", "'=' .sprintf('%02X', ord('\\1'))", $encoded);
                break;
            case 'comment':
                $encoded = preg_replace("/([\(\)\"])/e", "'=' .sprintf('%02X', ord('\\1'))", $encoded);
            case 'text':
            default:
                // Replace every high ascii, control =, ? and _ characters
                //TODO using /e (equivalent to eval()) is probably not a good idea
                $encoded = preg_replace('/([\000-\011\013\014\016-\037\075\077\137\177-\377])/e',
                                        "'=' . sprintf('%02X', ord(stripslashes('\\1')))", $encoded);
                break;
        }        
        // Replace every spaces to _ (more readable than =20)
        $encoded = str_replace(' ', '_', $encoded);        
        return $encoded;
    }
     
    public function addStringAttachment($string, $filename, $encoding='base64', $type='application/octet-stream') {
        // Append to $attachment array
        $this->attachment[] = array(
            $string,
            $filename,
            basename($filename),
            $encoding,
            $type,
            TRUE,  // isStringAttachment
            'attachment',
            0
        );
    }

    public function addEmbeddedImage($path, $cid, $name='', $encoding='base64', $type='application/octet-stream') {    
        if (!@is_file($path) ) {
            $this->setError($this->lang('file_access') . $path);
            return FALSE;
        }    
        $filename = basename($path);
        if ($name == '' ) {
            $name = $filename;
        }        
        // Append to $attachment array
        $this->attachment[] = array(
            $path,
            $filename,
            $name,
            $encoding,
            $type,
            FALSE,  // isStringAttachment
            'inline',
            $cid
        );        
        return TRUE;
    }

    public function addStringEmbeddedImage($string, $cid, $filename='', $encoding='base64', $type='application/octet-stream') {
        // Append to $attachment array
        $this->attachment[] = array(
            $string,
            $filename,
            basename($filename),
            $encoding,
            $type,
            TRUE,  // isStringAttachment
            'inline',
            $cid
        );
    }

    public function inlineImageExists() {
        foreach ($this->attachment as $attachment) {
            if ($attachment[6] == 'inline') {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function attachmentExists() {
        foreach ($this->attachment as $attachment) {
            if ($attachment[6] == 'attachment') {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function alternativeExists() { return strlen($this->alt_body) > 0; }

  /////////////////////////////////////////////////
  // CLASS METHODS, MESSAGE RESET
  /////////////////////////////////////////////////

    public function clearAddresses() {
        foreach ($this->to as $to) {
            unset($this->all_recipients[strtolower($to[0])]);
        }
        $this->to = array();
    }

    public function clearCcs() {
        foreach ($this->cc as $cc) {
            unset($this->all_recipients[strtolower($cc[0])]);
        }
        $this->cc = array();
    }

    public function clearBccs() {
        foreach ($this->bcc as $bcc) {
            unset($this->all_recipients[strtolower($bcc[0])]);
        }
        $this->bcc = array();
    }

    public function clearReplyTos() { $this->reply_to = array(); }

    public function clearAllRecipients() {
        $this->to = array();
        $this->cc = array();
        $this->bcc = array();
        $this->all_recipients = array();
    }

    public function clearAttachments() { $this->attachment = array(); }

    public function clearCustomHeaders() { $this->custom_header = array(); }

  /////////////////////////////////////////////////
  // CLASS METHODS, MISCELLANEOUS
  /////////////////////////////////////////////////

    protected function setError($msg) {
        $this->error_count++;
        if ($this->mailer == 'smtp' and !is_NULL($this->smtp)) {
            $lasterror = $this->smtp->getError();
            if (!empty($lasterror) and array_key_exists('smtp_msg', $lasterror)) {
                $msg .= '<p>' . $this->lang('smtp_error') . $lasterror['smtp_msg'] . "</p>\n";
            }
        }
        $this->error_info = $msg;
    }

    public static function rfcDate() {
        $tz  = date('Z');
        $tzs = ($tz < 0) ? '-' : '+';
        $tz  = abs($tz);
        $tz  = (int)($tz / 3600) * 100 + ($tz % 3600) / 60;
        $result = sprintf("%s %s%04d", date('D, j M Y H:i:s'), $tzs, $tz);        
        return $result;
    }

    protected function serverHostname() {
        if (!empty($this->hostname)) {
            return $this->hostname;
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        } else {
            return 'localhost.localdomain';
        }
    }
    
    protected function lang($key) {
        if (count($this->language) < 1) {
            $this->setLanguage('en'); // set the default language
        }        
        return (isset($this->language[$key])) ? $this->language[$key] : 'Language string failed to load: ' . $key;
    }
    
    public function isError() { return ($this->error_count > 0); }

    public function fixEol($str) {
        $str = str_replace("\r\n", "\n", $str);
        $str = str_replace("\r", "\n", $str);
        $str = str_replace("\n", $this->line_ending, $str);
        return $str;
    }

    public function addCustomHeader($custom_header) {
        $this->custom_header[] = explode(':', $custom_header, 2);
    }

    public function msgHtml($message, $basedir='') {
        preg_match_all("/(src|background)=[\"'](.*)[\"']/Ui", $message, $images);
        if (isset($images[2])) {
            foreach ($images[2] as $i => $url) {
                // do not change urls for absolute images (thanks to corvuscorax)
                if (!preg_match('#^[A-z]+://#', $url)) {
                    $filename = basename($url);
                    $directory = dirname($url);
                    if ($directory == ' . ') { $directory = ''; }
                    $cid = 'cid:' . md5($filename);
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $mime_type = self::getMimeTypes($ext);
                    if (strlen($basedir) > 1 && substr($basedir, -1) != '/') { $basedir .= '/'; }
                    if (strlen($directory) > 1 && substr($directory, -1) != '/') { $directory .= '/'; }
                    if ($this->addEmbeddedImage($basedir . $directory . $filename, md5($filename), $filename, 'base64', $mime_type) ) {
                        $message = preg_replace("/" . $images[1][$i] . "=[\"']" .preg_quote($url, '/') . "[\"']/Ui", $images[1][$i]. "=\"" . $cid . "\"", $message);
                    }
                }
            }
        }
        $this->isHtml(TRUE);
        $this->body = $message;
        if (empty($this->alt_body)) {
            $textMsg = trim(strip_tags(preg_replace('/<(head|title|style|script)[^>]*>.*?<\/\\1>/s', '', $message)));
            if (!empty($textMsg)) {
                $this->alt_body = html_entity_decode($textMsg, ENT_QUOTES, $this->charset);
            }
        }
        if (empty($this->alt_body)) {
            $this->alt_body = 'To view this email message, open it in a program that understands HTML!' . "\n\n";
        }
        return $message;
    }

    public static function getMimeTypes($ext = '') {
        $mimes = array(
            'hqx'   =>  'application/mac-binhex40',
            'cpt'   =>  'application/mac-compactpro',
            'doc'   =>  'application/msword',
            'bin'   =>  'application/macbinary',
            'dms'   =>  'application/octet-stream',
            'lha'   =>  'application/octet-stream',
            'lzh'   =>  'application/octet-stream',
            'exe'   =>  'application/octet-stream',
            'class' =>  'application/octet-stream',
            'psd'   =>  'application/octet-stream',
            'so'    =>  'application/octet-stream',
            'sea'   =>  'application/octet-stream',
            'dll'   =>  'application/octet-stream',
            'oda'   =>  'application/oda',
            'pdf'   =>  'application/pdf',
            'ai'    =>  'application/postscript',
            'eps'   =>  'application/postscript',
            'ps'    =>  'application/postscript',
            'smi'   =>  'application/smil',
            'smil'  =>  'application/smil',
            'mif'   =>  'application/vnd.mif',
            'xls'   =>  'application/vnd.ms-excel',
            'ppt'   =>  'application/vnd.ms-powerpoint',
            'wbxml' =>  'application/vnd.wap.wbxml',
            'wmlc'  =>  'application/vnd.wap.wmlc',
            'dcr'   =>  'application/x-director',
            'dir'   =>  'application/x-director',
            'dxr'   =>  'application/x-director',
            'dvi'   =>  'application/x-dvi',
            'gtar'  =>  'application/x-gtar',
            'php'   =>  'application/x-httpd-php',
            'php4'  =>  'application/x-httpd-php',
            'php3'  =>  'application/x-httpd-php',
            'phtml' =>  'application/x-httpd-php',
            'phps'  =>  'application/x-httpd-php-source',
            'js'    =>  'application/x-javascript',
            'swf'   =>  'application/x-shockwave-flash',
            'sit'   =>  'application/x-stuffit',
            'tar'   =>  'application/x-tar',
            'tgz'   =>  'application/x-tar',
            'xhtml' =>  'application/xhtml+xml',
            'xht'   =>  'application/xhtml+xml',
            'zip'   =>  'application/zip',
            'mid'   =>  'audio/midi',
            'midi'  =>  'audio/midi',
            'mpga'  =>  'audio/mpeg',
            'mp2'   =>  'audio/mpeg',
            'mp3'   =>  'audio/mpeg',
            'aif'   =>  'audio/x-aiff',
            'aiff'  =>  'audio/x-aiff',
            'aifc'  =>  'audio/x-aiff',
            'ram'   =>  'audio/x-pn-realaudio',
            'rm'    =>  'audio/x-pn-realaudio',
            'rpm'   =>  'audio/x-pn-realaudio-plugin',
            'ra'    =>  'audio/x-realaudio',
            'rv'    =>  'video/vnd.rn-realvideo',
            'wav'   =>  'audio/x-wav',
            'bmp'   =>  'image/bmp',
            'gif'   =>  'image/gif',
            'jpeg'  =>  'image/jpeg',
            'jpg'   =>  'image/jpeg',
            'jpe'   =>  'image/jpeg',
            'png'   =>  'image/png',
            'tiff'  =>  'image/tiff',
            'tif'   =>  'image/tiff',
            'css'   =>  'text/css',
            'html'  =>  'text/html',
            'htm'   =>  'text/html',
            'shtml' =>  'text/html',
            'txt'   =>  'text/plain',
            'text'  =>  'text/plain',
            'log'   =>  'text/plain',
            'rtx'   =>  'text/richtext',
            'rtf'   =>  'text/rtf',
            'xml'   =>  'text/xml',
            'xsl'   =>  'text/xml',
            'mpeg'  =>  'video/mpeg',
            'mpg'   =>  'video/mpeg',
            'mpe'   =>  'video/mpeg',
            'qt'    =>  'video/quicktime',
            'mov'   =>  'video/quicktime',
            'avi'   =>  'video/x-msvideo',
            'movie' =>  'video/x-sgi-movie',
            'doc'   =>  'application/msword',
            'word'  =>  'application/msword',
            'xl'    =>  'application/excel',
            'eml'   =>  'message/rfc822'
        );
        return (!isset($mimes[strtolower($ext)])) ? 'application/octet-stream' : $mimes[strtolower($ext)];
    }

    public function set($name, $value='') { // candidate for deletion, used only in tests
        try {
            if (isset($this->{$name}) ) {
                $this->{$name} = $value;
            } else {
                throw new MailerException($this->lang('variable_set') . $name, self::STOP_CRITICAL);
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            if ($e->getCode() == self::STOP_CRITICAL) {
                return FALSE;
            }
        }
        return TRUE;
    }

    public function secureHeader($str) {
        return trim(str_replace("\n", '', str_replace("\r", '', $str)));
    }

    public function sign($cert_filename, $key_filename, $key_pass) {
        $this->sign_cert_file = $cert_filename;
        $this->sign_key_file = $key_filename;
        $this->sign_key_pass = $key_pass;
    }

    public function dkimQp($txt) {
        $tmp = $line = '';
        for ($i = 0; $i < strlen($txt); $i++) {
            $ord = ord($txt[$i]);
            if (((0x21 <= $ord) && ($ord <= 0x3A)) || $ord == 0x3C || ((0x3E <= $ord) && ($ord <= 0x7E)) ) {
                $line .= $txt[$i];
            } else {
                $line .= '=' . sprintf("%02X", $ord);
            }
        }
        return $line;
    }

    public function dkimSign($s) {
        $priv_key_str = file_get_contents($this->dkim_private);
        if ($this->dkim_passphrase != '') {
            $priv_key = openssl_pkey_get_private($priv_key_str, $this->dkim_passphrase);
        } else {
            $priv_key = $priv_key_str;
        }
        if (openssl_sign($s, $signature, $priv_key)) {
            return base64_encode($signature);
        }
    }

    public function dkimCanonHeader($s) {
        $s = preg_replace("/\r\n\s+/", ' ', $s);
        $lines = explode("\r\n", $s);
        foreach ($lines as $key => $line) {
            list($heading, $value) = explode(':', $line, 2);
            $heading = strtolower($heading);
            $value   = preg_replace("/\s+/", ' ', $value) ; // Compress useless spaces
            $lines[$key] = $heading. ':' . trim($value) ; // Don't forget to remove WSP around the value
        }
        return implode("\r\n", $lines);
    }

    public function dkimCanonBody($body) {
        if ($body == '') { return "\r\n"; }
        // stabilize line endings
        $body = str_replace("\r\n", "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        // END stabilize line endings
        while (substr($body, strlen($body) - 4, 4) == "\r\n\r\n") {
            $body = substr($body, 0, strlen($body) - 2);
        }
        return $body;
    }

    public function dkimAdd($headers_line, $subject, $body) {
        $dkim_signature_type   = 'rsa-sha1'; // Signature & hash algorithms
        $dkim_canonicalization = 'relaxed/simple'; // Canonicalization of header/body
        $dkim_query            = 'dns/txt'; // Query method
        $dkim_time             = time() ; // Signature Timestamp = seconds since 00:00:00 - Jan 1, 1970 (UTC time zone)
        $subject_header        = "Subject: $subject";
        $headers               = explode($this->line_ending, $headers_line);
        foreach ($headers as $header) {
            if (strpos($header, 'From:') === 0) {
                $from_header = $header;
            } elseif (strpos($header, 'To:') === 0) {
                $to_header = $header;
            }
        }
        $from     = str_replace('|', '=7C', $this->dkimQp($from_header));
        $to       = str_replace('|', '=7C', $this->dkimQp($to_header));
        $subject  = str_replace('|', '=7C', $this->dkimQp($subject_header)) ; // Copied header fields (dkim-quoted-printable
        $body     = $this->dkimCanonBody($body);
        $dkim_length = strlen($body) ; // Length of body
        $dkim_base64 = base64_encode(pack("H*", sha1($body))) ; // Base64 of packed binary SHA-1 hash of body
        $ident    = ($this->dkim_identity == '')? '' : " i=" . $this->dkim_identity . ";";
        $dkimhdrs = "DKIM-Signature: v=1; a=" . $dkim_signature_type . "; q=" . $dkim_query . "; l=" . $dkim_length . "; s=" . $this->dkim_selector . ";\r\n" .
                    "\tt=" . $dkim_time . "; c=" . $dkim_canonicalization . ";\r\n" .
                    "\th=From:To:Subject;\r\n" .
                    "\td=" . $this->dkim_domain . ";" . $ident . "\r\n" .
                    "\tz=$from\r\n" .
                    "\t|$to\r\n" .
                    "\t|$subject;\r\n" .
                    "\tbh=" . $dkim_base64 . ";\r\n" .
                    "\tb=";
        $to_sign   = $this->dkimCanonHeader($from_header . "\r\n" . $to_header . "\r\n" . $subject_header . "\r\n" . $dkimhdrs);
        $signed   = $this->dkimSign($to_sign);
        return "X-MAILER-DKIM: mailer.kyoku.com\r\n" . $dkimhdrs . $signed . "\r\n";
    }
  
  protected function doCallback($is_sent, $to, $cc, $bcc, $subject, $body) {
    if (!empty($this->callback) && function_exists($this->callback)) {
      $params = array($is_sent, $to, $cc, $bcc, $subject, $body);
      call_user_func_array($this->callback, $params);
    }
  }
}

class MailerException extends Exception {
  public function errorMessage() {
    $errorMsg = '<strong>' . $this->getMessage() . "</strong><br />\n";
    return $errorMsg;
  }
}
?>
