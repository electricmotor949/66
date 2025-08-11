<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * PHP Version 5.5.
 *
 * @package PHPMailer
 * @link https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 * @author Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 * @copyright 2012 - 2020 Marcus Bointon
 * @copyright 2010 - 2012 Jim Jagielski
 * @copyright 2004 - 2009 Andy Prevost
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @note This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * PHPMailer - PHP email creation and transport class.
 *
 * @package PHPMailer
 * @author Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 */
class PHPMailer
{
    const CHARSET_ASCII = 'us-ascii';
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';

    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';
    const CONTENT_TYPE_TEXT_XML = 'text/xml';
    const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';

    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_BINARY = 'binary';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';

    /**
     * Email priority.
     * Options: null (default), 1 = High, 3 = Normal, 5 = low.
     * When null, the header is not set at all.
     *
     * @var int|null
     */
    public $Priority;

    /**
     * The character set of the message.
     *
     * @var string
     */
    public $CharSet = self::CHARSET_ISO88591;

    /**
     * The MIME Content-type of the message.
     *
     * @var string
     */
    public $ContentType = self::CONTENT_TYPE_PLAINTEXT;

    /**
     * The message encoding.
     * Options: "8bit", "7bit", "binary", "base64", and "quoted-printable".
     *
     * @var string
     */
    public $Encoding = self::ENCODING_8BIT;

    /**
     * Holds the most recent mailer error message.
     *
     * @var string
     */
    public $ErrorInfo = '';

    /**
     * The From email address for the message.
     *
     * @var string
     */
    public $From = 'root@localhost';

    /**
     * The From name of the message.
     *
     * @var string
     */
    public $FromName = 'Root User';

    /**
     * The envelope sender of the message.
     * This will usually be turned into a Return-Path header by the receiver,
     * and is the address that bounces will be sent to.
     * If not empty, will be passed via `-f` to sendmail or as the 'MAIL FROM' value to smtp.
     *
     * @var string
     */
    public $Sender = '';

    /**
     * The Subject of the message.
     *
     * @var string
     */
    public $Subject = '';

    /**
     * An HTML or plain text message body.
     * If HTML then call isHTML(true).
     *
     * @var string
     */
    public $Body = '';

    /**
     * The plain-text message body.
     * This body can be read by mail clients that do not have HTML email
     * capability such as mutt & Eudora.
     * Clients that can read HTML will view the normal Body.
     *
     * @var string
     */
    public $AltBody = '';

    /**
     * An array of all kinds of addresses.
     * Includes all of $to, $cc, and $bcc.
     *
     * @see PHPMailer::$to
     * @see PHPMailer::$cc
     * @see PHPMailer::$bcc
     *
     * @var array
     */
    protected $all_recipients = [];

    /**
     * An array of names and addresses queued for validation.
     * In send(), valid and non duplicate entries are moved to $all_recipients
     * and one or more RFC822 headers are created out of them.
     *
     * @var array
     */
    protected $to = [];

    /**
     * SMTP hosts.
     * Either a single hostname or multiple semicolon-delimited hostnames.
     * You can also specify a different port
     * for each host by using this format: [hostname:port]
     * (e.g. "smtp1.example.com:25;smtp2.example.com").
     * You can also specify encryption type, for example:
     * (e.g. "tls://smtp1.example.com:587;ssl://smtp2.example.com:465").
     * Hosts will be tried in order.
     *
     * @var string
     */
    public $Host = 'localhost';

    /**
     * The default SMTP server port.
     *
     * @var int
     */
    public $Port = 25;

    /**
     * The SMTP HELO/EHLO name used for the SMTP connection.
     * Default is $Hostname. If $Hostname is empty, PHPMailer attempts to find
     * it with the same method described above for $Hostname.
     *
     * @see PHPMailer::$Hostname
     *
     * @var string
     */
    public $Helo = '';

    /**
     * What kind of encryption to use on the SMTP connection.
     * Options: '', static::ENCRYPTION_STARTTLS, or static::ENCRYPTION_SMTPS.
     *
     * @var string
     */
    public $SMTPSecure = '';

    /**
     * Whether to enable TLS encryption automatically if a server supports it,
     * even if `SMTPSecure` is not set to 'tls'.
     * Be aware that in PHP >= 5.6 this requires that the server's certificates are valid.
     *
     * @var bool
     */
    public $SMTPAutoTLS = true;

    /**
     * Whether to use SMTP authentication.
     * Uses the Username and Password properties.
     *
     * @see PHPMailer::$Username
     * @see PHPMailer::$Password
     *
     * @var bool
     */
    public $SMTPAuth = false;

    /**
     * SMTP username.
     *
     * @var string
     */
    public $Username = '';

    /**
     * SMTP password.
     *
     * @var string
     */
    public $Password = '';

    /**
     * SMTP auth type.
     * Options are CRAM-MD5, LOGIN, PLAIN, XOAUTH2, attempted in that order if not specified.
     *
     * @var string
     */
    public $AuthType = '';

    /**
     * SMTP connection timeout in seconds.
     * Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2.
     *
     * @var int
     */
    public $Timeout = 300;

    /**
     * SMTP connection timeout in seconds.
     * Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2.
     *
     * @var int
     */
    public $SMTPTimeout = 300;

    /**
     * Whether to split long to addresses into multiple command arguments.
     * Automatically disabled if RFC 6531 is supported.
     *
     * @var bool
     */
    public $SingleTo = false;

    /**
     * Storage for the last sent to address.
     *
     * @var string
     */
    protected $lastMessageID = '';

    /**
     * The message ID to be used in the Message-Id header.
     * If empty, a unique id will be generated.
     *
     * @var string
     */
    protected $messageID = '';

    /**
     * Mailer. (smtp, mail, or sendmail).
     *
     * @var string
     */
    public $Mailer = 'mail';

    /**
     * The request to send a message to the server.
     *
     * @var string
     */
    protected $lastAck = '';

    /**
     * Whether to throw exceptions for errors.
     *
     * @var bool
     */
    public $SMTPDebug = 0;

    /**
     * Debug output level.
     * Options:
     * * `0` No output
     * * `1` Commands
     * * `2` Data and commands
     * * `3` As 2 plus connection status
     * * `4` Low-level data output.
     *
     * @var int
     */
    public $Debugoutput = 'echo';

    /**
     * Whether to keep SMTP connection open after each message.
     * If this is set to true then to close the connection
     * requires an explicit call to smtpClose().
     *
     * @var bool
     */
    public $SMTPKeepAlive = false;

    /**
     * Constructor.
     *
     * @param bool $exceptions Should we throw external exceptions?
     */
    public function __construct($exceptions = null)
    {
        if (null !== $exceptions) {
            $this->SMTPDebug = $exceptions;
        }
    }

    /**
     * Set the message type.
     *
     * @param bool $isHtml True for HTML mode
     *
     * @return PHPMailer
     */
    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = self::CONTENT_TYPE_TEXT_HTML;
        } else {
            $this->ContentType = self::CONTENT_TYPE_PLAINTEXT;
        }

        return $this;
    }

    /**
     * Set the mailer to use SMTP.
     *
     * @return PHPMailer
     */
    public function isSMTP()
    {
        $this->Mailer = 'smtp';

        return $this;
    }

    /**
     * Set the mailer to use the PHP mail() function.
     *
     * @return PHPMailer
     */
    public function isMail()
    {
        $this->Mailer = 'mail';

        return $this;
    }

    /**
     * Set the mailer to use the $Sendmail program.
     *
     * @return PHPMailer
     */
    public function isSendmail()
    {
        $this->Mailer = 'sendmail';

        return $this;
    }

    /**
     * Set the mailer to use the qmail MTA.
     *
     * @return PHPMailer
     */
    public function isQmail()
    {
        $this->Mailer = 'qmail';

        return $this;
    }

    /**
     * Add a "To" address.
     *
     * @param string $address The email address to send to
     * @param string $name
     *
     * @throws Exception
     *
     * @return PHPMailer
     */
    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    /**
     * Add a "CC" address.
     *
     * @param string $address The email address to send to
     * @param string $name
     *
     * @throws Exception
     *
     * @return PHPMailer
     */
    public function addCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }

    /**
     * Add a "BCC" address.
     *
     * @param string $address The email address to send to
     * @param string $name
     *
     * @throws Exception
     *
     * @return PHPMailer
     */
    public function addBCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }

    /**
     * Add a "Reply-To" address.
     *
     * @param string $address The email address to reply to
     * @param string $name
     *
     * @throws Exception
     *
     * @return PHPMailer
     */
    public function addReplyTo($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }

    /**
     * Add an address to one of the recipient arrays or to the ReplyTo array.
     * Because PHPMailer can't tell a valid address from a domain, it accepts any string
     * as an address, and then lets the mail server decide what to do with it.
     *
     * @param string $kind One of 'to', 'cc', 'bcc', 'Reply-To'
     * @param string $address The email address
     * @param string $name An optional name to associate with the address
     *
     * @throws Exception
     *
     * @return PHPMailer
     */
    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!$this->validateAddress($address)) {
            throw new Exception("Invalid address: $address");
        }
        if ('Reply-To' === $kind) {
            $this->ReplyTo[$address] = [$address, $name];
        } else {
            if (!isset($this->$kind)) {
                $this->$kind = [];
            }
            $this->{$kind}[$address] = [$address, $name];
        }

        return $this;
    }

    /**
     * Validate email address.
     *
     * @param string $address The email address to validate
     *
     * @return bool
     */
    public static function validateAddress($address)
    {
        return (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Set the From and FromName properties.
     *
     * @param string $address
     * @param string $name
     * @param bool $auto Whether to also set the Sender address, defaults to true
     *
     * @throws Exception
     *
     * @return PHPMailer
     */
    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!$this->validateAddress($address)) {
            throw new Exception("Invalid address: $address");
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }

        return $this;
    }

    /**
     * Send messages using SMTP.
     *
     * @return bool
     */
    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $exc) {
            $this->mailHeader = '';
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }

            return false;
        }
    }

    /**
     * Prepare a message for sending.
     *
     * @throws Exception
     *
     * @return bool
     */
    public function preSend()
    {
        if (empty($this->to) && empty($this->cc) && empty($this->bcc)) {
            throw new Exception('Message body empty');
        }

        return true;
    }

    /**
     * Actually send a message via the selected mechanism.
     *
     * @throws Exception
     *
     * @return bool
     */
    public function postSend()
    {
        // Choose the mailer and send through it
        switch ($this->Mailer) {
            case 'smtp':
                return $this->smtpSend($this->Host, $this->Port, $this->SMTPSecure, $this->SMTPAuth, $this->Username, $this->Password, $this->Timeout);
            case 'mail':
                return $this->mailSend($this->Sendmail, $this->Subject, $this->Body, $this->AltBody, $this->From, $this->FromName, $this->to, $this->cc, $this->bcc, $this->ReplyTo);
            default:
                return false;
        }
    }

    /**
     * Send using SMTP.
     *
     * @param string $host
     * @param int $port
     * @param string $secure
     * @param bool $auth
     * @param string $username
     * @param string $password
     * @param int $timeout
     *
     * @return bool
     */
    protected function smtpSend($host, $port, $secure, $auth, $username, $password, $timeout)
    {
        // This is a simplified version - in a real implementation, you'd use the SMTP class
        return true;
    }

    /**
     * Send using mail().
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string $altbody
     * @param string $from
     * @param string $fromname
     * @param array $to_addresses
     * @param array $cc_addresses
     * @param array $bcc_addresses
     * @param array $reply_to
     *
     * @return bool
     */
    protected function mailSend($to, $subject, $body, $altbody, $from, $fromname, $to_addresses, $cc_addresses, $bcc_addresses, $reply_to)
    {
        // This is a simplified version - in a real implementation, you'd use PHP's mail() function
        return true;
    }

    /**
     * Set error message.
     *
     * @param string $msg
     */
    protected function setError($msg)
    {
        $this->ErrorInfo = $msg;
    }

    /**
     * Get the SMTP instance.
     *
     * @return SMTP
     */
    public function getSMTPInstance()
    {
        if (!($this->smtp instanceof SMTP)) {
            $this->smtp = new SMTP();
        }

        return $this->smtp;
    }

    /**
     * Connect to an SMTP server.
     *
     * @return bool
     */
    public function smtpConnect()
    {
        $this->smtp = $this->getSMTPInstance();
        $this->smtp->do_verp = $this->DKIM_selector;
        $this->smtp->Debugoutput = $this->Debugoutput;
        $this->smtp->DebugLevel = $this->SMTPDebug;
        $this->smtp->Timeout = $this->Timeout;
        $this->smtp->Timelimit = $this->Timelimit;

        return $this->smtp->connect($this->Host, $this->Port);
    }

    /**
     * Authenticate with SMTP server.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function smtpAuthenticate($username, $password)
    {
        if (!$this->smtp) {
            return false;
        }

        return $this->smtp->authenticate($username, $password);
    }

    /**
     * Close SMTP connection.
     *
     * @return bool
     */
    public function smtpClose()
    {
        if ($this->smtp) {
            return $this->smtp->quit();
        }

        return true;
    }
}