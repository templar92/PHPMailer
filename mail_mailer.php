<?php
class MailMailer extends Mailer {

    protected $mailer;
    protected $sender; 
    protected $single_to;
    protected $to;
    protected $cc;
    protected $bcc;    

    public function __construct($sender, $single_to=FALSE) {
        $this->mailer    = 'mail';
        $this->sender    = $sender;
        $this->single_to = $single_to;
        $this->to        =
        $this->cc        =
        $this->bcc       = array();
    } 

    protected function mailSend($header, $subject, $body) {
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
                    $rt = @mail($val, $this->encodeHeader($this->secureHeader($subject)), $body, $header, $params);
                    // implement call back function if it exists
                    $is_sent = ($rt == 1) ? 1 : 0;
                    $this->doCallback($is_sent, $val, $this->cc, $this->bcc, $subject, $body);
                }
            } else {
                $rt = @mail($to, $this->encodeHeader($this->secureHeader($subject)), $body, $header, $params);
                // implement call back function if it exists
                $is_sent = ($rt == 1) ? 1 : 0;
                $this->doCallback($is_sent, $to, $this->cc, $this->bcc, $subject, $body);
            }
        } else {
            if ($this->single_to === TRUE && count($to_arr) > 1) {
                foreach ($to_arr as $key => $val) {
                    $rt = @mail($val, $this->encodeHeader($this->secureHeader($subject)), $body, $header, $params);
                    // implement call back function if it exists
                    $is_sent = ($rt == 1) ? 1 : 0;
                    $this->doCallback($is_sent, $val, $this->cc, $this->bcc, $subject, $body);
                }
            } else {
                $rt = @mail($to, $this->encodeHeader($this->secureHeader($subject)), $body, $header, $params);
                // implement call back function if it exists
                $is_sent = ($rt == 1) ? 1 : 0;
                $this->doCallback($is_sent, $to, $this->cc, $this->bcc, $subject, $body);
            }
        }
        if (isset($old_from)) {
            ini_set('sendmail_from', $old_from);
        }
        if (!$rt) {
            throw new MailerException('instantiate', MailerException::STOP_CRITICAL);
        }
        return TRUE;
    }
}
?>