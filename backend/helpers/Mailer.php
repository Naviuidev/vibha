<?php

declare(strict_types=1);

final class Mailer
{
    private array $config;
    private string $lastError = '';

    public function __construct()
    {
        $appConfig = require dirname(__DIR__) . '/config/app.php';
        $this->config = $appConfig['mail'];
        $this->config['mailer'] = strtolower(trim((string) ($this->config['mailer'] ?? 'smtp')));
        $this->config['username'] = trim((string) ($this->config['username'] ?? ''));
        $this->config['password'] = preg_replace('/\s+/', '', (string) ($this->config['password'] ?? '')) ?? '';
        $this->config['from_address'] = trim((string) ($this->config['from_address'] ?? ''));
        $this->config['from_name'] = trim((string) ($this->config['from_name'] ?? 'Vibhaa Jewellery'));
        $this->config['reply_to'] = trim((string) ($this->config['reply_to'] ?? ''));
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function send(string $to, string $subject, string $body, bool $isHtml = true, ?string $replyTo = null, ?string $altBody = null): bool
    {
        $this->lastError = '';
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;

            if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                return $this->sendViaPhpMailer($to, $subject, $body, $isHtml, $replyTo, $altBody);
            }
        }

        if ($this->config['mailer'] !== 'sendmail' && $this->config['username'] !== '' && $this->config['password'] !== '') {
            $this->lastError = 'PHPMailer is not installed. Run composer install in backend/.';
            error_log('Mailer error: ' . $this->lastError);
            return false;
        }

        return $this->sendViaMailFunction($to, $subject, $body, $isHtml, $replyTo);
    }

    private function mailHostname(): string
    {
        foreach (['FRONTEND_URL', 'APP_URL'] as $key) {
            $host = parse_url((string) ($_ENV[$key] ?? ''), PHP_URL_HOST);
            if (is_string($host) && $host !== '' && $host !== 'localhost' && !str_starts_with($host, '127.')) {
                return $host;
            }
        }
        return 'vibhaajewellery.in';
    }

    private function useSendmail(): bool
    {
        return in_array($this->config['mailer'], ['sendmail', 'mail'], true);
    }

    private function fromAddress(): string
    {
        if ($this->useSendmail()) {
            $from = $this->config['from_address'] !== '' ? $this->config['from_address'] : 'noreply@vibhaajewellery.in';
            if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
                return $from;
            }
            return 'noreply@vibhaajewellery.in';
        }

        return $this->config['username'];
    }

    private function sendViaPhpMailer(string $to, string $subject, string $body, bool $isHtml, ?string $replyTo = null, ?string $altBody = null): bool
    {
        $sendmail = $this->useSendmail();

        if (!$sendmail && ($this->config['username'] === '' || $this->config['password'] === ''
            || str_contains($this->config['username'], 'your-gmail@'))) {
            $this->lastError = 'SMTP is not configured. Set MAIL_USERNAME and MAIL_PASSWORD in backend/.env.';
            error_log('Mailer error: ' . $this->lastError);
            return false;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'quoted-printable';
            $mail->Hostname = $this->mailHostname();
            $mail->XMailer = ' ';

            $from = $this->fromAddress();
            $fromName = $this->config['from_name'] !== '' ? $this->config['from_name'] : 'Vibhaa Jewellery';

            if ($sendmail) {
                $mail->isSendmail();
                $path = trim((string) ini_get('sendmail_path'));
                if ($path === '') {
                    $path = '/usr/sbin/sendmail -t -i';
                }
                if (!str_contains($path, '-f')) {
                    $path .= ' -f' . escapeshellarg($from);
                }
                $mail->Sendmail = $path;
            } else {
                $mail->isSMTP();
                $mail->Host = $this->config['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $this->config['username'];
                $mail->Password = $this->config['password'];
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $this->config['port'];
                $mail->Timeout = 20;
            }

            $mail->setFrom($from, $fromName, false);
            $mail->Sender = $from;
            $mail->addAddress($to);

            $reply = $replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)
                ? $replyTo
                : ($this->config['reply_to'] !== '' && filter_var($this->config['reply_to'], FILTER_VALIDATE_EMAIL)
                    ? $this->config['reply_to']
                    : $from);
            $mail->addReplyTo($reply, $fromName);
            $mail->Subject = $subject;

            if ($isHtml) {
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->AltBody = $altBody !== null && $altBody !== ''
                    ? $altBody
                    : trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body)), ENT_QUOTES, 'UTF-8'));
            } else {
                $mail->Body = $body;
            }

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('Mailer error: ' . $this->lastError);
            return false;
        }
    }

    private function sendViaMailFunction(string $to, string $subject, string $body, bool $isHtml, ?string $replyTo = null): bool
    {
        $from = $this->fromAddress();
        $reply = $replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)
            ? $replyTo
            : $from;
        $headers = [
            'From: ' . $this->config['from_name'] . ' <' . $from . '>',
            'Reply-To: ' . $reply,
        ];

        if ($isHtml) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=utf-8';
        }

        return @mail($to, $subject, $body, implode("\r\n", $headers), '-f' . $from);
    }
}
