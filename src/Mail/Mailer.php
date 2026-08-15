<?php

namespace App\Mail;

use App\Support\Env;

/**
 * Minimal SMTP client for sending transactional email.
 * Uses stream sockets + STARTTLS (no external dependency required).
 */
class Mailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromAddress;
    private string $fromName;
    private int $timeout;
    private string $apiKey;

    public function __construct(array $config = [])
    {
        $config = array_merge([
            'host' => self::env('MAIL_HOST', 'smtp-relay.brevo.com'),
            'port' => (int)self::env('MAIL_PORT', 587),
            'username' => self::env('MAIL_USERNAME', ''),
            'password' => self::env('MAIL_PASSWORD', ''),
            'from_address' => self::env('MAIL_FROM_ADDRESS', 'noreply@wamblog.com'),
            'from_name' => self::env('MAIL_FROM_NAME', 'WAM Blog'),
            'timeout' => 30,
        ], $config);

        // Render deployments set MAIL_PASS; the code historically read MAIL_PASSWORD.
        if ($config['password'] === '') {
            $config['password'] = self::env('MAIL_PASS', '');
        }

        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->fromAddress = $config['from_address'];
        $this->fromName = $config['from_name'];
        $this->timeout = $config['timeout'];
        // Brevo HTTP API key (v3). When set, mail is sent over HTTPS via the
        // Brevo API instead of raw SMTP — required on Render's free tier, which
        // blocks outbound SMTP ports (25/465/587).
        $this->apiKey = self::env('BREVO_API_KEY', '');
    }

    /**
     * Send a plain-text + HTML email.
     *
     * @throws \RuntimeException on failure
     */
    public function send(string $to, string $subject, string $htmlBody, string $textBody = ''): void
    {
        $textBody = $textBody !== '' ? $textBody : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        // Prefer the Brevo HTTP API when an API key is present (works on
        // Render's free tier, which blocks outbound SMTP ports).
        if ($this->apiKey !== '') {
            $this->sendViaBrevoApi($to, $subject, $htmlBody, $textBody);
            return;
        }

        if ($this->username === '' || $this->password === '') {
            throw new \RuntimeException('SMTP credentials (MAIL_USERNAME / MAIL_PASSWORD) are not configured.');
        }

        $socket = $this->connect();
        try {
            $this->expect($socket, '220');

            $this->command($socket, "EHLO " . (gethostname() ?: 'localhost'));
            $this->readMultiline($socket);

            // STARTTLS
            $this->command($socket, 'STARTTLS');
            $this->expect($socket, '220');
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) {
                throw new \RuntimeException('Failed to enable STARTTLS encryption.');
            }

            $this->command($socket, "EHLO " . (gethostname() ?: 'localhost'));
            $this->readMultiline($socket);

            // AUTH LOGIN
            $this->command($socket, 'AUTH LOGIN');
            $this->expect($socket, '334');
            $this->command($socket, base64_encode($this->username));
            $this->expect($socket, '334');
            $this->command($socket, base64_encode($this->password));
            $this->expect($socket, '235');

            $from = $this->address($this->fromAddress, $this->fromName);
            $this->command($socket, "MAIL FROM:<{$this->fromAddress}>");
            $this->expect($socket, '250');
            $this->command($socket, "RCPT TO:<{$to}>");
            $this->expect($socket, '250');

            $this->command($socket, 'DATA');
            $this->expect($socket, '354');

            $headers = [
                'From: ' . $from,
                'To: <' . $to . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'X-Mailer: WAM-Blog/1.0',
            ];

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody;
            $message = str_replace(["\r\n", "\r"], "\n", $message);
            $message = str_replace("\n", "\r\n", $message);

            fwrite($socket, $message . "\r\n.\r\n");
            $this->expect($socket, '250');

            $this->command($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    /**
     * Send via the Brevo Transactional Email HTTP API (v3).
     * HTTPS (443) is allowed on Render's free tier, unlike SMTP ports.
     *
     * @throws \RuntimeException on failure
     */
    private function sendViaBrevoApi(string $to, string $subject, string $htmlBody, string $textBody): void
    {
        $payload = [
            'sender' => ['name' => $this->fromName, 'email' => $this->fromAddress],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'htmlContent' => $htmlBody,
        ];
        if ($textBody !== '') {
            $payload['textContent'] = $textBody;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'api-key: ' . $this->apiKey,
                ]),
                'content' => json_encode($payload),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $context);

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }

        if ($status < 200 || $status >= 300) {
            $detail = is_string($response) && $response !== '' ? trim($response) : '';
            throw new \RuntimeException("Brevo API error (HTTP $status): " . ($detail ?: 'empty response'));
        }
    }

    private function connect()
    {
        $errno = 0;
        $errstr = '';
        $remote = "tcp://{$this->host}:{$this->port}";
        $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            throw new \RuntimeException("SMTP connection failed: $errstr ($errno)");
        }
        stream_set_timeout($socket, $this->timeout);
        return $socket;
    }

    private function command($socket, string $cmd): void
    {
        fwrite($socket, $cmd . "\r\n");
    }

    private function expect($socket, string $expectedCode): void
    {
        $line = fgets($socket);
        if ($line === false) {
            throw new \RuntimeException('SMTP connection closed unexpectedly.');
        }
        $code = substr($line, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("SMTP error: expected $expectedCode, got: " . trim($line));
        }
    }

    private function readMultiline($socket): void
    {
        while (($line = fgets($socket)) !== false) {
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
    }

    private function address(string $email, string $name = ''): string
    {
        if ($name === '') {
            return '<' . $email . '>';
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = Env::get($key);
        return $value === null ? $default : $value;
    }
}