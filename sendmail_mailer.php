<?php
class SendmailMailer extends Mailer {
    
    const SENDMAIL = '/usr/sbin/sendmail';
    const QMAIL    = '/var/qmail/bin/sendmail';

    protected $mailer;
    protected $sender; 
    protected $single_to;
    protected $single_to_array;
    protected $to;
    protected $cc;
    protected $bcc;    
    protected $deamon;    

    public function __construct($sender, $single_to=FALSE) {
        $this->mailer          = 'sendmail';
        $this->sender          = $sender;
        $this->single_to       = $single_to;
        $this->single_to_array = 
        $this->to              =
        $this->cc              =
        $this->bcc             = array();
        $this->deamon          = (!stristr(ini_get('sendmail_path'), 'sendmail')) ? self::QMAIL : self::SENDMAIL;
    }    

    protected function send($header, $subject, $body) {
        if ($this->sender != '') {
            $sendmail = sprintf("%s -oi -f %s -t", escapeshellcmd($this->deamon), escapeshellarg($this->sender));
        } else {
            $sendmail = sprintf("%s -oi -t", escapeshellcmd($this->deamon));
        }
        if ($this->single_to === TRUE) {
            foreach ($this->single_to_array as $key => $val) {
                if (!@$mail = popen($sendmail, 'w')) {
                    throw new MailerException('execute#' . $this->deamon, MailerException::STOP_CRITICAL);
                }
                fputs($mail, 'To: ' . $val . "\n");
                fputs($mail, $header);
                fputs($mail, $body);
                $result = pclose($mail);
                // implement call back function if it exists
                $is_sent = ($result == 0) ? 1 : 0;
                $this->mailer->doCallback($is_sent, $val, $this->cc, $this->bcc, $subject, $body);
                if ($result != 0) {
                    throw new MailerException('execute#' . $this->deamon, MailerException::STOP_CRITICAL);
                }
            }
        } else {
            if (!@$mail = popen($sendmail, 'w')) {
                throw new MailerException('execute#' . $this->deamon, MailerException::STOP_CRITICAL);
            }
            fputs($mail, $header);
            fputs($mail, $body);
            $result = pclose($mail);
            // implement call back function if it exists
            $is_sent = ($result == 0) ? 1 : 0;
            $this->mailer->doCallback($is_sent, $this->to, $this->cc, $this->bcc, $subject, $body);
            if ($result != 0) {
                throw new MailerException('execute#' . $this->deamon, MailerException::STOP_CRITICAL);
            }
        }
        return TRUE;
    }
    
}
?>