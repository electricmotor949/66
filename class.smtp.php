<?php
/**
 * PHPMailer RFC821 SMTP email transport class.
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
 * PHPMailer RFC821 SMTP email transport class.
 * Implements RFC 821 SMTP commands and provides some utility methods for sending mail to an SMTP server.
 *
 * @package PHPMailer
 * @author Chris Ryan
 * @author Marcus Bointon <phpmailer@synchromedia.co.uk>
 */
class SMTP
{
    const VERSION = '6.8.0';
    const LE = "\r\n";
    const DEFAULT_PORT = 25;
    const DEFAULT_TIMEOUT = 300;
    const MAX_LINE_LENGTH = 998;
    const MAX_REPLY_LENGTH = 512;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;

    /**
     * The socket to the server.
     *
     * @var resource
     */
    protected $smtp_conn;

    /**
     * Error information, if any, for the last SMTP command.
     *
     * @var array
     */
    protected $error = [];

    /**
     * The reply the server sent to us for HELO.
     * If null, no HELO string has yet been received.
     *
     * @var string|null
     */
    protected $helo_rply = null;

    /**
     * The set of SMTP extensions sent in reply to EHLO command.
     * Indexes of the array are extension names.
     * Value at index 'HELP' is the human-readable help text.
     *
     * @var array
     */
    protected $server_caps = null;

    /**
     * The most recent reply received from the server.
     *
     * @var string
     */
    protected $last_reply = '';

    /**
     * Output debugging info via a user-selected method.
     *
     * @param string $str Debug string to output
     *
     * @return void
     */
    protected function edebug($str)
    {
        if ($this->Debugoutput == 'error_log') {
            error_log($str);
        } else {
            echo $str;
        }
    }

    /**
     * Connect to an SMTP server.
     *
     * @param string $host SMTP servers to connect to
     * @param int $port The default port to connect to
     * @param int $timeout How long to wait for the connection to open
     * @param array $options An array of options for stream_context_create()
     *
     * @return bool
     */
    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        // Clear errors to avoid confusion
        $this->setError('');

        // Make sure we are __not__ connected
        if ($this->connected()) {
            // Already connected, generate error
            $this->setError('Already connected to a server');

            return false;
        }

        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }

        // Connect to the SMTP server
        $this->edebug("Connection: opening to $host:$port, timeout=$timeout, options=" . var_export($options, true), self::DEBUG_CONNECTION);

        $errno = 0;
        $errstr = '';
        $socket_context = stream_context_create($options);
        // Suppress errors; connection failures are handled at a higher level
        $this->smtp_conn = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $socket_context
        );

        // Verify we connected properly
        if (!is_resource($this->smtp_conn)) {
            $this->setError(
                'Failed to connect to server',
                $errno,
                $errstr
            );
            $this->edebug(
                'SMTP ERROR: ' . $this->error['error'] . ": $errstr ($errno)",
                self::DEBUG_CLIENT
            );

            return false;
        }

        $this->edebug('Connection: opened', self::DEBUG_CONNECTION);

        // SMTP server can take longer to respond, give longer timeout for first read
        // Windows does not have support for this timeout function
        if (substr(PHP_OS, 0, 3) != 'WIN') {
            $max = ini_get('max_execution_time');
            if ($max != 0 && $timeout > $max) {
                @set_time_limit($timeout);
            }
            stream_set_timeout($this->smtp_conn, $timeout, 0);
        }

        // Get any initial server response
        $this->last_reply = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $this->last_reply, self::DEBUG_SERVER);

        $r = substr($this->last_reply, 0, 3);

        if ($r != '220') {
            $this->setError(
                'SMTP not accepted from server',
                $this->last_reply
            );
            $this->edebug(
                'SMTP ERROR: ' . $this->error['error'] . ': ' . $this->last_reply,
                self::DEBUG_CLIENT
            );

            return false;
        }

        return true;
    }

    /**
     * Initiate a TLS (encrypted) session.
     *
     * @return bool
     */
    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }

        // Begin encrypted connection
        if (!stream_socket_enable_crypto($this->smtp_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return false;
        }

        return true;
    }

    /**
     * Perform SMTP authentication.
     *
     * @param string $username The user name
     * @param string $password The password
     * @param string $authtype The auth type (PLAIN, LOGIN, NTLM, CRAM-MD5)
     * @param string $realm The auth realm for NTLM
     * @param string $workstation The auth workstation for NTLM
     *
     * @return bool True if successfully authenticated
     */
    public function authenticate($username, $password, $authtype = null, $realm = '', $workstation = '')
    {
        if (!$this->server_caps) {
            $this->setError('Authentication is not allowed before HELO/EHLO');

            return false;
        }

        if (array_key_exists('AUTH', $this->server_caps)) {
            $this->edebug('Auth method requested: ' . ($authtype ? $authtype : 'UNSPECIFIED'), self::DEBUG_CONNECTION);
            $this->edebug('Auth methods available on the server: ' . implode(',', $this->server_caps['AUTH']), self::DEBUG_CONNECTION);

            if (!$authtype) {
                foreach (['CRAM-MD5', 'LOGIN', 'PLAIN', 'NTLM'] as $method) {
                    if (in_array($method, $this->server_caps['AUTH'])) {
                        $authtype = $method;
                        break;
                    }
                }
                if (!$authtype) {
                    $this->setError('No supported authentication methods found');

                    return false;
                }
                $this->edebug('Selected authentication method: ' . $authtype, self::DEBUG_CONNECTION);
            }

            if (!in_array($authtype, $this->server_caps['AUTH'])) {
                $this->setError("The requested authentication method \"$authtype\" is not supported by the server");

                return false;
            }
        } else {
            $this->setError('Authentication is not supported by the server');

            return false;
        }

        switch ($authtype) {
            case 'PLAIN':
                // Start authentication
                if (!$this->sendCommand('AUTH', 'AUTH PLAIN', 334)) {
                    return false;
                }
                // Send encoded username and password
                if (!$this->sendCommand('User & Password', base64_encode("\0" . $username . "\0" . $password), 235)) {
                    return false;
                }
                break;
            case 'LOGIN':
                // Start authentication
                if (!$this->sendCommand('AUTH LOGIN', 'AUTH LOGIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand('Username', base64_encode($username), 334)) {
                    return false;
                }
                if (!$this->sendCommand('Password', base64_encode($password), 235)) {
                    return false;
                }
                break;
            case 'CRAM-MD5':
                // Start authentication
                if (!$this->sendCommand('AUTH CRAM-MD5', 'AUTH CRAM-MD5', 334)) {
                    return false;
                }
                // Get the challenge
                $challenge = base64_decode(substr($this->last_reply, 4));

                // Build the response
                $response = $username . ' ' . $this->hmac($challenge, $password);

                // Send encoded credentials
                return $this->sendCommand('Username', base64_encode($response), 235);
            case 'NTLM':
                /*
                 * ntlm_sasl_client.php
                 * Bundled with permission
                 *
                 * How to telnet in windows:
                 * http://technet.microsoft.com/en-us/library/aa995718%28EXCHG.65%29.aspx
                 * PROTOCOL Docs http://curl.haxx.se/rfc/ntlm.html#ntlmSmtpAuthentication
                 */
                require_once 'extras/ntlm_sasl_client.php';
                $temp = new stdClass();
                $ntlm_client = new ntlm_sasl_client_class();
                if (!$ntlm_client->Initialize($temp)) {
                    $this->setError($temp->error);

                    return false;
                }
                $msg1 = $ntlm_client->TypeMsg1($realm, $workstation); //msg1

                $this->client_send('AUTH NTLM ' . base64_encode($msg1) . self::LE);

                $rply = $this->get_lines();
                $code = substr($rply, 0, 3);

                if ($code != 334) {
                    $this->setError('AUTH not accepted from server', $rply);

                    return false;
                }

                $challenge = base64_decode(substr($rply, 4)); //though 0 based, there is a white space after the 3 digit number  //msg2
                $msg3 = $ntlm_client->TypeMsg3($temp->user, $temp->password, $realm, $workstation, $challenge);
                $this->client_send(base64_encode($msg3) . self::LE);

                $rply = $this->get_lines();
                $code = substr($rply, 0, 3);

                if ($code != 235) {
                    $this->setError('Expected 235 but got "' . $code . '", with message: "' . $rply . '"', $rply);

                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * Send an SMTP command and handle the response.
     *
     * @param string $command The command name - not sent to the server
     * @param string $commandstring The actual command to send
     * @param int|array $expect One or more expected integer success codes
     *
     * @return bool True on success
     */
    protected function sendCommand($command, $commandstring, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Called $command without being connected");

            return false;
        }

        $this->client_send($commandstring . self::LE, $command);

        $this->last_reply = $this->get_lines();
        $code = substr($this->last_reply, 0, 3);

        $this->edebug('SERVER -> CLIENT: ' . $this->last_reply, self::DEBUG_SERVER);

        if (!in_array($code, (array) $expect)) {
            $this->setError("$command command failed", $this->last_reply, $code);

            return false;
        }

        $this->setError('');

        return true;
    }

    /**
     * Send raw data to the server.
     *
     * @param string $data The data to send
     * @param string $command Optionally, the command this is part of, used only for controlling debug output
     *
     * @return int The number of bytes sent to the server
     */
    protected function client_send($data, $command = '')
    {
        //If SMTP transcripts are left enabled, or debug output is posted online
        //it can leak credentials, so hide credentials in all but lowest level
        if (self::DEBUG_LOWLEVEL > $this->do_verp && 'Password' != $command && 'Username' != $command) {
            $this->edebug('CLIENT -> SERVER: ' . $data, self::DEBUG_CLIENT);
        } else {
            $this->edebug('CLIENT -> SERVER: [credentials hidden]', self::DEBUG_CLIENT);
        }

        return fwrite($this->smtp_conn, $data);
    }

    /**
     * Get the lines from the server.
     *
     * @return string The lines from the server
     */
    protected function get_lines()
    {
        // If the connection is bad, give up straight away
        if (!is_resource($this->smtp_conn)) {
            return '';
        }

        $data = '';
        $endtime = time() + $this->Timeout;
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $str = @fgets($this->smtp_conn, 515);
            if (false === $str) {
                break;
            }
            $data .= $str;
            // If 4th character is a space, we are done reading, break the loop
            // Implements RFC 2821 section 4.5.2.
            if (' ' == substr($str, 3, 1)) {
                break;
            }
            // Timed-out? Log and break
            $info = stream_get_meta_data($this->smtp_conn);
            if ($info['timed_out']) {
                $this->edebug('SMTP -> get_lines(): timed-out (' . $this->Timeout . ' sec)', self::DEBUG_LOWLEVEL);
                break;
            }
            // Now check if reads took too long
            if ($endtime && time() > $endtime) {
                $this->edebug('SMTP -> get_lines(): timelimit reached (' . $this->Timeout . ' sec)', self::DEBUG_LOWLEVEL);
                break;
            }
        }

        return $data;
    }

    /**
     * Enable or disable VERP address generation.
     *
     * @param bool $enabled
     */
    public function setVerp($enabled = false)
    {
        $this->do_verp = $enabled;
    }

    /**
     * Get VERP address generation mode.
     *
     * @return bool
     */
    public function getVerp()
    {
        return $this->do_verp;
    }

    /**
     * Set error messages and codes.
     *
     * @param string $message The error message
     * @param string $detail Further detail on the error
     * @param string $smtp_code An associated SMTP error code
     * @param string $smtp_code_ex An extended SMTP error code
     */
    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '')
    {
        $this->error = [
            'error' => $message,
            'detail' => $detail,
            'smtp_code' => $smtp_code,
            'smtp_code_ex' => $smtp_code_ex,
        ];
    }

    /**
     * Set debug output method.
     *
     * @param string|callable $method The name of the mechanism to use for debugging output, or a callable to handle it
     */
    public function setDebugOutput($method = 'echo')
    {
        $this->Debugoutput = $method;
    }

    /**
     * Get debug output method.
     *
     * @return string
     */
    public function getDebugOutput()
    {
        return $this->Debugoutput;
    }

    /**
     * Set debug output level.
     *
     * @param int $level
     */
    public function setDebugLevel($level = 0)
    {
        $this->do_verp = $level;
    }

    /**
     * Get debug output level.
     *
     * @return int
     */
    public function getDebugLevel()
    {
        return $this->do_verp;
    }

    /**
     * Set SMTP timeout.
     *
     * @param int $timeout The timeout duration in seconds
     */
    public function setTimeout($timeout = 0)
    {
        $this->Timeout = $timeout;
    }

    /**
     * Get SMTP timeout.
     *
     * @return int
     */
    public function getTimeout()
    {
        return $this->Timeout;
    }

    /**
     * Reports an error number and string.
     *
     * @return array An array containing the error number and error string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Returns true if connected to a server otherwise false.
     *
     * @return bool
     */
    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                // The socket is valid but we are not connected
                $this->edebug('SMTP NOTICE: EOF caught while checking if connected', self::DEBUG_CLIENT);
                $this->close();

                return false;
            }

            return true; // everything looks good
        }

        return false;
    }

    /**
     * Closes the socket and cleans up the state of the class.
     * It is not considered good to use this function without
     * first trying to use QUIT.
     *
     * @return void
     */
    public function close()
    {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            // Close the connection and cleanup
            fclose($this->smtp_conn);
            $this->smtp_conn = null; //Makes for cleaner serialization
            $this->edebug('Connection: closed', self::DEBUG_CONNECTION);
        }
    }

    /**
     * Send an SMTP QUIT command.
     * Closes the socket if there is no error or the QUIT command was successful.
     *
     * @return bool
     */
    public function quit($close_on_error = true)
    {
        $no_err = $this->sendCommand('QUIT', 'QUIT', 221);
        $err = $this->getError(); //Save any error
        if ($no_err || $close_on_error) {
            $this->close();
            $this->setError($err); //Restore any error from the quit command
        }

        return $no_err;
    }

    /**
     * Send an SMTP RCPT command.
     * Sets the TO argument to $toaddr.
     * Returns true if the recipient was accepted false if it was rejected.
     *
     * Implements from rfc 821: RCPT <SP> TO:<forward-path> <CRLF>.
     *
     * @param string $toaddr The address the message is being sent to
     *
     * @return bool
     */
    public function recipient($toaddr)
    {
        return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $toaddr . '>', [250, 251]);
    }

    /**
     * Send an SMTP RSET command.
     * Abort any transaction that is currently in progress.
     *
     * Implements rfc 821: RSET <CRLF>.
     *
     * @return bool True on success
     */
    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }

    /**
     * Send an SMTP VRFY command.
     *
     * @param string $name The name to verify
     *
     * @return bool
     */
    public function verify($name)
    {
        return $this->sendCommand('VRFY', 'VRFY ' . $name, [250, 251, 252]);
    }

    /**
     * Send an SMTP NOOP command.
     * Used to keep keep-alives alive, doesn't actually do anything.
     *
     * @return bool
     */
    public function noop()
    {
        return $this->sendCommand('NOOP', 'NOOP', 250);
    }

    /**
     * Send an SMTP MAIL command.
     * Starts a mail transaction from the email address specified in
     * $from. Returns true if successful or false otherwise. If True
     * the mail transaction is started and then one or more recipient
     * commands may be called followed by a data command.
     *
     * Implements rfc 821: MAIL <SP> FROM:<reverse-path> <CRLF>.
     *
     * @param string $from Source address of this message
     *
     * @return bool
     */
    public function mail($from)
    {
        $useVerp = ($this->do_verp ? ' XVERP' : '');

        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>' . $useVerp, 250);
    }

    /**
     * Send an SMTP DATA command.
     * Issues a data command and sends the msg_data to the server
     * finializing the mail transaction. $msg_data is the message
     * that is to be send with the headers. Each header needs to be
     * on a single line followed by a <CRLF> with the message headers
     * and the message body being separated by and additional <CRLF>.
     *
     * Implements rfc 821: DATA <CRLF>.
     *
     * @param string $msg_data Message data to send
     *
     * @return bool
     */
    public function data($msg_data)
    {
        //This will use the standard timelimit
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }

        /* The server is ready to accept data!
         * According to rfc 821 we should not send more than 1000 characters on a single line (including the CRLF)
         * so we will break the data up into lines by \r and/or \n
         * and make sure we don't exceed 1000 characters when we do this.
         */
        $normalized = str_replace(["\r\n", "\r"], "\n", $msg_data);
        $lines = explode("\n", $normalized);
        $field = substr($lines[0], 0, strpos($lines[0], ':'));
        $in_headers = false;
        if (!empty($field) && !strpos($field, ' ')) {
            $in_headers = true;
        }

        foreach ($lines as $line) {
            $lines_out = null;
            if ($in_headers && $line == '') {
                $in_headers = false;
            }
            // ok we need to break this line up into several smaller lines
            //This is a small micro-optimisation: isset($str[$len]) is equivalent to (strlen($str) > $len)
            while (isset($line[self::MAX_LINE_LENGTH])) {
                //Working backwards, try to find a space within the last MAX_LINE_LENGTH chars of the line to break on
                //so as to avoid breaking in the middle of a word
                $pos = strrpos(substr($line, 0, self::MAX_LINE_LENGTH), ' ');
                if (!$pos) { //Deliberately matches both false and 0
                    //No nice break found, add a hard break
                    $pos = self::MAX_LINE_LENGTH - 1;
                    $lines_out[] = substr($line, 0, $pos);
                    $line = substr($line, $pos);
                } else {
                    //Break at the found point
                    $lines_out[] = substr($line, 0, $pos);
                    $line = substr($line, $pos + 1);
                }
                /* If processing headers add a LWSP-char to the front of new line
                 * RFC822 section 3.1.1
                 */
                if ($in_headers) {
                    $line = "\t" . $line;
                }
            }
            $lines_out[] = $line;

            // Send the lines to the server
            foreach ($lines_out as $line_out) {
                // RFC2821 section 4.5.2
                if (!empty($line_out) && $line_out[0] == '.') {
                    $line_out = '.' . $line_out;
                }
                $this->client_send($line_out . self::LE, 'DATA');
            }
        }

        // Message data has been sent, complete the command
        // Increase timelimit for end of DATA command
        $savetimelimit = $this->Timelimit;
        $this->Timelimit = $this->Timelimit * 2;
        $result = $this->sendCommand('DATA END', '.', 250);
        $this->Timelimit = $savetimelimit;

        return $result;
    }

    /**
     * Send an SMTP HELO or EHLO command.
     * Used to identify the sending server to the receiving server.
     * This makes sure that client and server are in a known state.
     *
     * Implements from RFC 821: HELO <SP> <domain> <CRLF>
     * and RFC 2821: EHLO <SP> <domain> <CRLF>.
     *
     * @param string $host The host name or IP to connect to
     *
     * @return bool
     */
    public function hello($host = '')
    {
        // Try extended first
        if ($this->server_caps) {
            if (!$this->sendCommand('EHLO', 'EHLO ' . $host, 250)) {
                if (!$this->sendCommand('HELO', 'HELO ' . $host, 250)) {
                    return false;
                }
            }
        } else {
            if (!$this->sendCommand('HELO', 'HELO ' . $host, 250)) {
                return false;
            }
        }

        $this->helo_rply = $this->last_reply;

        return true;
    }

    /**
     * Send an SMTP HELO or EHLO command.
     * Low-level implementation used by hello().
     *
     * @see hello()
     *
     * @param string $host The host name or IP to connect to
     *
     * @return bool
     */
    public function sendHello($host, $hello)
    {
        $noerror = $this->sendCommand($hello, $hello . ' ' . $host, 250);
        $this->helo_rply = $this->last_reply;
        if ($noerror) {
            $this->parseHelloFields($hello);
        }

        return $noerror;
    }

    /**
     * Parse a reply to HELO out to separate server name.
     * HELO's reply can contain extended information after the server name, where the old HELO was just
     * server name. The HELO reply (from many servers) is implemented in various formats, the below should
     * extract the correct hostname from the HELO reply.
     *
     * @param string $helo_reply
     *
     * @return string
     */
    protected function parseHelloFields($helo_reply)
    {
        $this->server_caps = [];
        $lines = explode("\n", $this->last_reply);

        foreach ($lines as $n => $s) {
            $s = trim(substr($s, 4));
            if (empty($s)) {
                continue;
            }
            $fields = explode(' ', $s);
            if (!empty($fields)) {
                if (!$n) {
                    $name = $fields[0];
                    if (isset($name[0]) && $name[0] == '[') {
                        $name = substr($name, 1, -1);
                    }
                    $this->server_caps['EHLO'] = $name;
                } else {
                    $name = array_shift($fields);
                    if (isset($name[0]) && $name[0] == '[') {
                        $name = substr($name, 1, -1);
                    }
                    $fields = array_values($fields);
                    $this->server_caps[$name] = empty($fields) ? $name : $fields;
                }
            }
        }
    }

    /**
     * Send an SMTP AUTH command.
     *
     * @param string $username The username
     * @param string $password The password
     *
     * @return bool
     */
    public function authenticate($username, $password)
    {
        return $this->authenticate($username, $password, 'LOGIN');
    }

    /**
     * Calculate an MD5 HMAC hash.
     * Works like hash_hmac('md5', $data, $key)
     * in case that function is not available.
     *
     * @param string $data The data to hash
     * @param string $key The key to hash with
     *
     * @return string
     */
    protected function hmac($data, $key)
    {
        if (function_exists('hash_hmac')) {
            return hash_hmac('md5', $data, $key);
        }

        // The following borrowed from
        // http://php.net/manual/en/function.mhash.php#27225

        // RFC 2104 HMAC implementation for php.
        // Creates an md5 HMAC.
        // Eliminates the need to install mhash to compute a HMAC
        // Hacked by Lance Rushing

        $bytelen = 64; // byte length for md5
        if (strlen($key) > $bytelen) {
            $key = pack('H*', md5($key));
        }
        $key = str_pad($key, $bytelen, chr(0x00));
        $ipad = str_pad('', $bytelen, chr(0x36));
        $opad = str_pad('', $bytelen, chr(0x5c));
        $k_ipad = $key ^ $ipad;
        $k_opad = $key ^ $opad;

        return md5($k_opad . pack('H*', md5($k_ipad . $data)));
    }
}