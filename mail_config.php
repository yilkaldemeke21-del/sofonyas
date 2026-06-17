<?php

require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';
require_once __DIR__ . '/vendor/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('loadDotEnvFile')) {
    function loadDotEnvFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            if (preg_match('/^\s*#/', $line) === 1) {
                continue;
            }
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $key = $matches[1];
            $value = trim($matches[2]);
            if (preg_match('/^("|\')(.*)\1$/', $value, $quoted) === 1) {
                $value = $quoted[2];
            }

            if (!array_key_exists($key, $_ENV) || $_ENV[$key] === '') {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER) || $_SERVER[$key] === '') {
                $_SERVER[$key] = $value;
            }
            putenv("$key=$value");
        }
    }
}

if (!function_exists('envValue')) {
    function envValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        $value = getenv($key);
        return ($value !== false && $value !== '') ? $value : $default;
    }
}

loadDotEnvFile(__DIR__ . '/.env');

if (!function_exists('sendAppEmail')) {
    function sendAppEmail(string $to, string $subject, string $message, array $options = []): bool
    {
        $fromEmail = envValue('SMTP_FROM_EMAIL', envValue('MAIL_FROM_ADDRESS', 'no-reply@example.com'));
        $fromName = envValue('SMTP_FROM_NAME', envValue('MAIL_FROM_NAME', 'Sofnyas LMS'));
        $host = (string)envValue('SMTP_HOST', '');
        $port = (int)envValue('SMTP_PORT', 587);
        $username = (string)envValue('SMTP_USERNAME', '');
        $password = (string)envValue('SMTP_PASSWORD', '');
        $encryption = (string)envValue('SMTP_ENCRYPTION', 'tls');
        $replyTo = (string)($options['reply_to'] ?? '');
        $cc = (array)($options['cc'] ?? []);
        $bcc = (array)($options['bcc'] ?? []);
        $isHtml = (bool)($options['is_html'] ?? true);
        $debugLevel = (int)envValue('SMTP_DEBUG', 0);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('sendAppEmail: invalid recipient email -> ' . $to);
            return false;
        }

        if ($host === '' && empty($username) && empty($password)) {
            error_log('sendAppEmail: no SMTP settings found. Set SMTP_HOST, SMTP_USERNAME, and SMTP_PASSWORD or add them to .env.');
        }

        $mail = new PHPMailer(true);

        try {
            if ($host !== '') {
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = $port;
                $mail->SMTPAuth = ($username !== '' && $password !== '');
                if ($mail->SMTPAuth) {
                    $mail->Username = $username;
                    $mail->Password = $password;
                }
                if ($encryption !== '') {
                    $mail->SMTPSecure = $encryption;
                }
                $mail->SMTPKeepAlive = true;
                $mail->SMTPDebug = $debugLevel;
            } else {
                $mail->isMail();
            }

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->XMailer = 'Sofnyas LMS';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);

            if ($replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }
            foreach ($cc as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($email);
                }
            }
            foreach ($bcc as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($email);
                }
            }

            $mail->Subject = $subject;
            $mail->isHTML($isHtml);
            if ($isHtml) {
                $mail->Body = $message;
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
            } else {
                $mail->Body = $message;
            }

            $result = $mail->send();
            if (!$result) {
                error_log('sendAppEmail: send() returned false for ' . $to);
            }
            return $result;
        } catch (Exception $e) {
            error_log('sendAppEmail failed for ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }
}
