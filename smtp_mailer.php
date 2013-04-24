<?php
class SmtpMailer extends Mailer {

    protected $mailer;
    protected $server;
    protected $username;
    protected $password;
    protected $host;
    protected $authenticate;
    protected $from;
    protected $sender;
    protected $debug;
    protected $timeout;
    protected $to;
    protected $cc;
    protected $bcc;
    protected $debug;

    public function __construct($host, $sender, $keep_alive=FALSE, $authenticate=FALSE, $security='ssl', $helo='', $debug=0) {
        $this->mailer       = 'smtp';
        $this->server       = new Smtp($debug);
        $this->debug        = $debug;
        $this->host         = $host;
        $this->sender       = $sender;
        $this->security     = $security;
        $this->authenticate = $authenticate;
        $this->keep_alive   = $keep_alive;
        $this->helo         = $helo; // EHLO, HELO, or blank
        $this->to           =
        $this->cc           =
        $this->bcc          = array();
    }

    protected function send($header, $subject, $body) {
        $bad_rcpt = array();
        if (!$this->connect()) {
            throw new MailerException('smtp_connect_failed', MailerException::STOP_CRITICAL);
        }
        $smtp_from = ($this->sender == '') ? $this->from : $this->sender;
        if (!$this->server->mail($smtp_from)) {
            throw new MailerException('from_failed#' . $smtp_from, MailerException::STOP_CRITICAL);
        }
        foreach ($this->to as $to) {
            if (!$this->server->recipient($to[0])) {
                $bad_rcpt[] = $to[0];
                $is_sent = 0;
                $this->doCallback($is_sent, $to[0], '', '', $subject, $body);
            } else {
                $is_sent = 1;
                $this->doCallback($is_sent, $to[0], '', '', $subject, $body);
            }
        }
        foreach ($this->cc as $cc) {
            if (!$this->server->recipient($cc[0])) {
                $bad_rcpt[] = $cc[0];                
                $is_sent = 0;
                $this->doCallback($is_sent, '', $cc[0], '', $subject, $body);
            } else {
                $is_sent = 1;
                $this->doCallback($is_sent, '', $cc[0], '', $subject, $body);
            }
        }
        foreach ($this->bcc as $bcc) {
            if (!$this->server->recipient($bcc[0])) {
                $bad_rcpt[] = $bcc[0];
                $is_sent = 0;
                $this->doCallback($is_sent, '', '', $bcc[0], $subject, $body);
            } else {
                $is_sent = 1;
                $this->doCallback($is_sent, '', '', $bcc[0], $subject, $body);
            }
        }
        if (count($bad_rcpt) > 0) {
            $bad_addresses = implode(', ', $bad_rcpt);
            throw new MailerException('recipients_failed#' . $bad_addresses);
        }
        if (!$this->server->data($header . $body)) {
            throw new MailerException('data_not_accepted', MailerException::STOP_CRITICAL);
        }
        if ($this->keep_alive == TRUE) {
            $this->server->reset();
        }
        return TRUE;
    }

    public function connect() {
        if (is_null($this->server)) {
            $this->server = new Smtp($this->debug);
        }
        $hosts = explode(';', $this->host);
        $index = 0;
        $connection = $this->server->connected();        
        try {
            while ($index < count($hosts) && !$connection) {
                $hostinfo = array();
                if (preg_match('/^(.+):([0-9]+)$/', $hosts[$index], $hostinfo)) {
                    $host = $hostinfo[1];
                    $port = $hostinfo[2];
                } else {
                    $host = $hosts[$index];
                    $port = $this->port;
                }
                $tls = ($this->security == 'tls');
                $ssl = ($this->security == 'ssl');
                if ($this->server->connect(($ssl ? 'ssl://':'') . $host, $port, $this->timeout)) {
                    $hello = ($this->helo != '' ? $this->helo : $this->serverHostname());
                    $this->server->hello($hello);
                    if ($tls) {
                        if (!$this->server->startTls()) {
                            throw new MailerException('tls');
                        }                        
                        $this->server->hello($hello);
                    }
                    $connection = TRUE;
                    if ($this->authenticate) {
                        if (!$this->server->authenticate($this->username, $this->password)) {
                            throw new MailerException('authenticate');
                        }
                    }
                }
                $index++;
                if (!$connection) {
                  throw new MailerException('connect_host');
                }
            }
        } catch (MailerException $e) {
            $this->server->reset();
            if ($this->exceptions) {
                throw $e;
            }
        }
        return TRUE;
    }

    public function smtpClose() {
        if (!is_null($this->server)) {
            if ($this->server->connected()) {
                $this->server->quit();
                $this->server->close();
            }
        }
    }

}
?>