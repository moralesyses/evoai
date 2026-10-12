<?php
/**
 * Outgoing email for Backoffice > Contacts (config "mail"): sent through an
 * SMTP server with PHPMailer (vendor/), written to storage/mail/mail.log in
 * development, or off (messages then wait in the member's outbox).
 */
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/** 'smtp', 'log' or 'off'. SMTP without a host or sender address counts as off. */
function mail_transport(): string
{
    $transport = (string) config('mail.transport', 'off');
    if ($transport === 'smtp') {
        $ready = config('mail.smtp.host') && valid_email((string) config('mail.from_email', '')) && class_exists(PHPMailer::class);
        return $ready ? 'smtp' : 'off';
    }
    return $transport === 'log' ? 'log' : 'off';
}

/** Where the development mail log is written. */
function mail_log_file(): string
{
    return APP_ROOT . '/storage/mail/mail.log';
}

/**
 * Send one plain-text email. $m: to, toName, subject, body, and optionally
 * fromName (shown as "<fromName> via <mail.from_name>"), replyTo,
 * replyToName, unsubscribeUrl (List-Unsubscribe header).
 * Returns ['ok' => bool, 'error' => ?string].
 */
function mail_send(array $m): array
{
    $transport = mail_transport();
    if ($transport === 'off') {
        return ['ok' => false, 'error' => 'Email sending is not set up.'];
    }
    $site = (string) config('mail.from_name', '88MMM Circle');
    $fromName = ($m['fromName'] ?? '') !== '' ? $m['fromName'] . ' via ' . $site : $site;
    $from = (string) config('mail.from_email', '');
    $replyTo = valid_email((string) ($m['replyTo'] ?? '')) ? (string) $m['replyTo'] : '';

    if ($transport === 'log') {
        $dir = dirname(mail_log_file());
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create ' . $dir . '.'];
        }
        $lines = [
            '==== ' . date('Y-m-d H:i:s') . ' ====',
            'From: ' . $fromName . ' <' . ($from ?: 'no-reply@localhost') . '>',
            'Reply-To: ' . ($replyTo !== '' ? ($m['replyToName'] ?? '') . ' <' . $replyTo . '>' : '-'),
            'To: ' . ($m['toName'] ?? '') . ' <' . $m['to'] . '>',
            'Subject: ' . $m['subject'],
            'List-Unsubscribe: ' . (($m['unsubscribeUrl'] ?? '') !== '' ? '<' . $m['unsubscribeUrl'] . '>' : '-'),
            '',
            $m['body'],
            '',
            '',
        ];
        $ok = file_put_contents(mail_log_file(), implode("\n", $lines), FILE_APPEND | LOCK_EX) !== false;
        return ['ok' => $ok, 'error' => $ok ? null : 'Could not write the mail log.'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = (string) config('mail.smtp.host');
        $mail->Port = (int) config('mail.smtp.port', 587);
        $encryption = (string) config('mail.smtp.encryption', 'tls');
        $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
        $mail->SMTPAutoTLS = $encryption !== '';
        $username = (string) config('mail.smtp.username', '');
        if ($username !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = (string) config('mail.smtp.password', '');
        }
        $mail->Timeout = 20;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($from, $fromName);
        if ($replyTo !== '') {
            $mail->addReplyTo($replyTo, (string) ($m['replyToName'] ?? ''));
        }
        $mail->addAddress((string) $m['to'], (string) ($m['toName'] ?? ''));
        $mail->Subject = (string) $m['subject'];
        $mail->isHTML(false);
        $mail->Body = (string) $m['body'];
        if (($m['unsubscribeUrl'] ?? '') !== '') {
            $mail->addCustomHeader('List-Unsubscribe', '<' . $m['unsubscribeUrl'] . '>');
        }
        $mail->send();
        return ['ok' => true, 'error' => null];
    } catch (MailerException $e) {
        // The full SMTP conversation goes to the server log; the member sees its first sentence.
        $detail = $mail->ErrorInfo ?: $e->getMessage();
        error_log('Mail to ' . $m['to'] . ' failed: ' . $detail);
        return ['ok' => false, 'error' => mb_substr(preg_match('/^.*?\.(?=\s|$)/s', $detail, $first) ? $first[0] : $detail, 0, 250)];
    }
}
