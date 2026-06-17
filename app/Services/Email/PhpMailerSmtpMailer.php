<?php

namespace App\Services\Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PhpMailerException;
use RuntimeException;

class PhpMailerSmtpMailer
{
    public function __construct()
    {
        // โหลด PHPMailer จากโฟลเดอร์ Mail ที่อยู่ใน root โปรเจกต์
        require_once base_path('Mail/Exception.php');
        require_once base_path('Mail/PHPMailer.php');
        require_once base_path('Mail/SMTP.php');
    }

    /**
     * @throws RuntimeException
     */
    public function send(
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        ?string $toName = null
    ): bool {
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name') ?: config('app.name');

        if (!filled($host) || !filled($port) || !filled($username) || !filled($password) || !filled($fromAddress)) {
            throw new RuntimeException('Mail SMTP is not configured (check MAIL_* in .env)');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;

            // รองรับทั้ง ssl/tls ตามค่า config
            $enc = config('mail.mailers.smtp.encryption');
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port = (int) $port;

            $mail->setFrom($fromAddress, (string) $fromName);
            $mail->addAddress($toEmail, $toName ?: '');

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

            return (bool) $mail->send();
        } catch (PhpMailerException $e) {
            throw new RuntimeException('PHPMailer send failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
