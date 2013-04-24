<?php
class Pop3 {

    const DEFAULT_PORT = 110;
    const DEFAULT_TIMEOUT = 30;

    public $do_debug = 2;

    public $username;
    public $password;
    public $host;
    public $port;
    public $tval;
    
    public $version = '1.0.0';

    private $pop_conn;
    private $connected;
    private $error;         //  Error log array

    public function __construct($host, $username, $password, $port=self::DEFAULT_PORT, $tval=self::DEFAULT_TIMEOUT, $debug_level=0) {
        $this->pop_conn  = 0;
        $this->connected = FALSE;
        $this->error     = NULL;
        $this->do_debug = $debug_level;
        $this->username = $username;
        $this->password = $password;        
        $this->host     = $host;        
        $this->port     = $port;        
        $this->tval     = $tval;        
    }

    public function authorize() {
        $result = FALSE; 
        if ($this->connect()) {            
            $result = $this->login();
        }
        $this->disconnect();
        return $result;
    }

    public function connect() {
        //  Are we already connected?
        if (!$this->connected) {
            /*
            On Windows this will raise a PHP Warning error if the hostname doesn't exist.
            Rather than supress it with @fsockopen, let's capture it cleanly instead
            */
            set_error_handler(array(&$this, 'catchWarning'));
            //  Connect to the POP3 server
            $this->pop_conn = fsockopen($this->host, $this->port, $errno, $errstr, $this->tval);
            //  Restore the error handler
            restore_error_handler();
            //  Does the Error Log now contain anything?
            if ($this->error && $this->do_debug >= 1) {
                $this->displayErrors();
            }
            //  Did we connect?
            if ($this->pop_conn == FALSE) {
                //  It would appear not...
                $this->error = array(
                    'error'  => "Failed to connect to server $host on port $port",
                    'errno'  => $errno,
                    'errstr' => $errstr
                );
                if ($this->do_debug >= 1) {
                    $this->displayErrors();
                }
                return FALSE;
            }            
            //  Increase the stream time-out            
            //  Check for PHP 4.3.0 or later
            if (version_compare(phpversion(), '5.0.0', 'ge')) {
                stream_set_timeout($this->pop_conn, $this->tval, 0);
            } else {
                //  Does not work on Windows
                if (substr(PHP_OS, 0, 3) !== 'WIN') {
                    socket_set_timeout($this->pop_conn, $this->tval, 0);
                }
            }            
            //  Get the POP3 server response
            $pop3_response = $this->getResponse();            
            //  Check for the +OK
            if ($this->checkResponse($pop3_response)) {
                //  The connection is established and the POP3 server is talking
                $this->connected = TRUE;
                return TRUE;
            }
            return FALSE;
        }
        return TRUE;
    }

    public function login() {
        if ($this->connected || (!$this->connected && $this->connect())) {
            $pop_username = "USER $this->username" . Mailer::CRLF;
            $pop_password = "PASS $this->password" . Mailer::CRLF;            
            //  Send the Username
            $this->sendString($pop_username);
            $pop3_response = $this->getResponse();            
            if ($this->checkResponse($pop3_response)) {
                //  Send the Password
                $this->sendString($pop_password);          
                return $this->checkResponse($this->getResponse());
            }
        }
        return FALSE;
    }

    public function disconnect() {
        $this->sendString('QUIT');
        fclose($this->pop_conn);
    }

    private function getResponse ($size=128) { return fgets($this->pop_conn, $size); }

    private function sendString ($string) { return fwrite($this->pop_conn, $string, strlen($string)); }

    private function checkResponse ($string) {
        if (substr($string, 0, 3) !== '+OK') {
            $this->error = array(
                'error'  => "Server reported an error: $string",
                'errno'  => 0,
                'errstr' => ''
            );
            if ($this->do_debug >= 1) {
                $this->displayErrors();
            }
            return FALSE;
        }
        return TRUE;
    }

    private function displayErrors () {
        echo '<pre>';
        foreach ($this->error as $single_error) {
            print_r($single_error);
        }
        echo '</pre>';
    }

    private function catchWarning ($errno, $errstr, $errfile, $errline) {
        $this->error[] = array(
            'error'  => "Connecting to the POP3 server raised a PHP warning: ",
            'errno'  => $errno,
            'errstr' => $errstr
        );
    }

}
?>
