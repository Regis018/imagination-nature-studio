<?php
/**
 * Imagination Nature Studio - SMTP Mailer
 * Lightweight SMTP client using PHP sockets (no external library).
 * Supports STARTTLS on port 587 and implicit SSL on port 465.
 */
require_once __DIR__ . '/../config.php';

if (!function_exists('ins_mail_log')) {
    function ins_mail_log(string $message): void
    {
        $target = defined('ERROR_LOG_FILE') ? ERROR_LOG_FILE : '';
        if ($target !== '') {
            @error_log('[mail] ' . $message . PHP_EOL, 3, $target);
        } else {
            error_log('[mail] ' . $message);
        }
    }
}

if (!function_exists('ins_mail_read_response')) {
    function ins_mail_read_response($fp): string
    {
        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            // SMTP multiline responses end with "250 " rather than "250-".
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}

if (!function_exists('ins_mail_command')) {
    function ins_mail_command($fp, string $command, array $expectedCodes, string $label): bool
    {
        $written = fwrite($fp, $command . "\r\n");
        if ($written === false) {
            ins_mail_log($label . ': failed to write SMTP command');
            return false;
        }

        $response = ins_mail_read_response($fp);
        $code = substr(ltrim($response), 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            // Do not log AUTH commands or credentials.
            ins_mail_log($label . ' failed: ' . trim($response));
            return false;
        }
        return true;
    }
}

if (!function_exists('send_mail')) {
    function send_mail(string $to, string $subject, string $htmlBody, string $replyTo = ''): bool
    {
        if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
            ins_mail_log('mail disabled; skipped message');
            return false;
        }

        $host     = defined('MAIL_HOST') ? trim((string)MAIL_HOST) : '';
        $port     = defined('MAIL_PORT') ? (int)MAIL_PORT : 587;
        $user     = defined('MAIL_USER') ? (string)MAIL_USER : '';
        $pass     = defined('MAIL_PASS') ? (string)MAIL_PASS : '';
        $secure   = strtolower(defined('MAIL_SECURE') ? trim((string)MAIL_SECURE) : 'tls');
        $from     = defined('MAIL_FROM') ? trim((string)MAIL_FROM) : '';
        $fromName = defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'Imagination Nature Studio';

        if (
            $host === '' || $port < 1 || $port > 65535 ||
            $user === '' || $pass === '' ||
            !filter_var($to, FILTER_VALIDATE_EMAIL) ||
            !filter_var($from, FILTER_VALIDATE_EMAIL) ||
            !in_array($secure, ['tls', 'ssl'], true)
        ) {
            ins_mail_log('invalid or incomplete SMTP configuration / recipient');
            return false;
        }

        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            ins_mail_log('invalid Reply-To address');
            return false;
        }

        // Prevent header injection through user-provided values.
        $subject = trim(str_replace(["\r", "\n"], '', $subject));
        $fromName = trim(str_replace(["\r", "\n"], '', $fromName));
        $ehloName = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if ($ehloName === '') {
            $ehloName = 'localhost';
        }

        $plain = trim(html_entity_decode(
            strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        try {
            $boundary = 'INS_' . bin2hex(random_bytes(16));
            $messageIdDomain = substr(strrchr($from, '@') ?: '@localhost', 1);
            $messageIdDomain = preg_replace('/[^A-Za-z0-9.-]/', '', $messageIdDomain) ?: 'localhost';

            $headers  = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . ">\r\n";
            $headers .= 'To: <' . $to . ">\r\n";
            if ($replyTo !== '') {
                $headers .= 'Reply-To: <' . $replyTo . ">\r\n";
            }
            $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
            $headers .= 'Date: ' . date('r') . "\r\n";
            $headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $messageIdDomain . ">\r\n";

            $body  = '--' . $boundary . "\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($plain)) . "\r\n";
            $body .= '--' . $boundary . "\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $body .= '--' . $boundary . "--\r\n";

            // Dot-stuff lines beginning with "." as required by SMTP DATA.
            $data = preg_replace('/(^|\r\n)\./', '$1..', $headers . "\r\n" . $body);

            $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => true,
                    'verify_peer_name'  => true,
                    'allow_self_signed' => false,
                    'peer_name'         => $host,
                    'SNI_enabled'       => true,
                ],
            ]);

            $fp = @stream_socket_client(
                $remote . ':' . $port,
                $errno,
                $errstr,
                20,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$fp) {
                ins_mail_log('connection failed: ' . $errstr . ' (' . $errno . ')');
                return false;
            }

            stream_set_timeout($fp, 20);

            $ok = true;
            $greeting = ins_mail_read_response($fp);
            if (substr(ltrim($greeting), 0, 3) !== '220') {
                ins_mail_log('SMTP greeting failed: ' . trim($greeting));
                $ok = false;
            }

            if ($ok) {
                $ok = ins_mail_command($fp, 'EHLO ' . $ehloName, ['250'], 'EHLO');
            }

            if ($ok && $secure === 'tls') {
                $ok = ins_mail_command($fp, 'STARTTLS', ['220'], 'STARTTLS');
                if ($ok) {
                    $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    if ($crypto !== true) {
                        ins_mail_log('TLS negotiation failed; check server certificate and PHP CA certificates');
                        $ok = false;
                    }
                }
                if ($ok) {
                    $ok = ins_mail_command($fp, 'EHLO ' . $ehloName, ['250'], 'EHLO after TLS');
                }
            }

            if ($ok) {
                $ok = ins_mail_command($fp, 'AUTH LOGIN', ['334'], 'AUTH LOGIN');
            }
            if ($ok) {
                $ok = ins_mail_command($fp, base64_encode($user), ['334'], 'SMTP username');
            }
            if ($ok) {
                $ok = ins_mail_command($fp, base64_encode($pass), ['235'], 'SMTP password');
            }
            if ($ok) {
                $ok = ins_mail_command($fp, 'MAIL FROM:<' . $from . '>', ['250'], 'MAIL FROM');
            }
            if ($ok) {
                $ok = ins_mail_command($fp, 'RCPT TO:<' . $to . '>', ['250', '251'], 'RCPT TO');
            }
            if ($ok) {
                $ok = ins_mail_command($fp, 'DATA', ['354'], 'DATA');
            }
            if ($ok) {
                $written = fwrite($fp, $data . "\r\n.\r\n");
                if ($written === false) {
                    ins_mail_log('failed writing email body');
                    $ok = false;
                } else {
                    $response = ins_mail_read_response($fp);
                    if (substr(ltrim($response), 0, 3) !== '250') {
                        ins_mail_log('message rejected: ' . trim($response));
                        $ok = false;
                    }
                }
            }

            if ($ok) {
                // QUIT failure does not undo an already accepted message.
                @fwrite($fp, "QUIT\r\n");
                ins_mail_log('message accepted by SMTP server for ' . $to);
            }

            fclose($fp);
            return $ok;
        } catch (Throwable $e) {
            ins_mail_log('mailer exception: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Send a nicely-formatted booking email to the customer.
 */
function send_booking_email(array $b, string $serviceName, ?string $packageName, string $receiptUrl): bool
{
    $to = trim((string)($b['email'] ?? ''));
    if ($to === '') {
        ins_mail_log('customer booking email skipped: customer email is missing');
        return false;
    }

    $bookingNo = (string)($b['booking_number'] ?? '');
    $customerName = (string)($b['full_name'] ?? 'Customer');
    $subject = 'Your booking ' . $bookingNo . ' — Imagination Nature Studio';

    $receiptHref = trim($receiptUrl);
    if ($receiptHref !== '' && !preg_match('~^https?://~i', $receiptHref)) {
        $receiptHref = rtrim((string)SITE_URL, '/') . '/' . ltrim($receiptHref, '/');
    }

    $html = '
    <div style="font-family:Arial,Helvetica,sans-serif;background:#f5f2ee;padding:30px 0">
      <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <div style="background:#0e0d0c;padding:24px;color:#f3ece3;text-align:center">
          <div style="font-size:20px;font-weight:bold;letter-spacing:1px">IMAGINATION NATURE STUDIO</div>
          <div style="font-size:11px;letter-spacing:3px;color:#a79e95;margin-top:4px">PHOTOGRAPHY &amp; CREATIVE STUDIO</div>
        </div>
        <div style="padding:30px">
          <h2 style="color:#e07a2f;margin:0 0 8px;font-size:18px">Booking received — thank you!</h2>
          <p style="color:#333;line-height:1.6">Hi ' . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . ',</p>
          <p style="color:#333;line-height:1.6">
            We have received your booking request. Our team will review it and contact you
            to confirm the appointment.
          </p>

          <table style="width:100%;border-collapse:collapse;margin:20px 0">
            <tr><td style="padding:8px 0;color:#666">Booking number</td>
                <td style="padding:8px 0;text-align:right;color:#e07a2f;font-weight:bold">'
                . htmlspecialchars($bookingNo, ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td style="padding:8px 0;color:#666">Service</td>
                <td style="padding:8px 0;text-align:right">' . htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8') . '</td></tr>';

    if ($packageName) {
        $html .= '<tr><td style="padding:8px 0;color:#666">Package</td>
                    <td style="padding:8px 0;text-align:right">' . htmlspecialchars($packageName, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }

    $html .= '
            <tr><td style="padding:8px 0;color:#666">Date</td>
                <td style="padding:8px 0;text-align:right">' . htmlspecialchars((string)($b['booking_date'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td style="padding:8px 0;color:#666">Time</td>
                <td style="padding:8px 0;text-align:right">'
                . htmlspecialchars(substr((string)($b['start_time'] ?? ''),0,5), ENT_QUOTES, 'UTF-8') . ' – '
                . htmlspecialchars(substr((string)($b['end_time'] ?? ''),0,5), ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td style="padding:8px 0;color:#666">Location</td>
                <td style="padding:8px 0;text-align:right">' . htmlspecialchars((string)($b['location'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</td></tr>
          </table>

          <div style="background:#faf7f3;border:1px solid #eee;border-radius:8px;padding:16px;margin:20px 0">
            <table style="width:100%;border-collapse:collapse">
              <tr><td style="color:#666">Total</td>
                  <td style="text-align:right;font-weight:bold">' . rwf($b['total_amount']) . '</td></tr>
              <tr><td style="color:#666">Deposit</td>
                  <td style="text-align:right">' . rwf($b['deposit_amount']) . '</td></tr>
              <tr><td style="color:#666">Amount paid</td>
                  <td style="text-align:right">' . rwf($b['amount_paid']) . '</td></tr>
              <tr><td style="color:#666">Balance</td>
                  <td style="text-align:right;font-weight:bold">' . rwf($b['balance']) . '</td></tr>
            </table>
          </div>

          <div style="text-align:center;margin:26px 0">
            <a href="' . htmlspecialchars($receiptHref, ENT_QUOTES, 'UTF-8') . '"
               style="background:#e07a2f;color:#fff;text-decoration:none;padding:12px 24px;border-radius:24px;font-weight:bold;display:inline-block">
              View booking receipt
            </a>
          </div>

          <p style="color:#666;font-size:13px;line-height:1.6">
            If you have questions, call us on +250 788 577 033 or reply to this email.
          </p>
        </div>
        <div style="background:#0a0908;color:#8c837b;padding:16px;text-align:center;font-size:11px">
          Kiyovu, Kigali · Rwanda · TIN 111997931
        </div>
      </div>
    </div>';

    return send_mail($to, $subject, $html, defined('MAIL_FROM') ? (string)MAIL_FROM : '');
}

/**
 * Send a new-booking alert to the admin.
 */
function send_admin_booking_email(array $b, string $serviceName, ?string $packageName): bool
{
    if (!defined('ADMIN_NOTIFY_EMAIL') || !filter_var(ADMIN_NOTIFY_EMAIL, FILTER_VALIDATE_EMAIL)) {
        ins_mail_log('admin booking alert skipped: ADMIN_NOTIFY_EMAIL is missing or invalid');
        return false;
    }

    $bookingNo = (string)($b['booking_number'] ?? '');
    $subject = 'New Booking – Imagination Nature Studio (' . $bookingNo . ')';

    $html = '
    <div style="font-family:Arial,Helvetica,sans-serif;padding:20px">
      <h2 style="color:#e07a2f;margin-top:0">New booking received</h2>
      <table style="border-collapse:collapse;font-size:14px">
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Booking No</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($bookingNo, ENT_QUOTES, 'UTF-8') . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Client</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Phone</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars((string)($b['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Email</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars((string)($b['email'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Service</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8') . '</td></tr>';

    if ($packageName) {
        $html .= '<tr><td style="padding:6px 10px;background:#f7f4f0"><b>Package</b></td>
                      <td style="padding:6px 10px">' . htmlspecialchars($packageName, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }

    $html .= '
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Date</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars((string)($b['booking_date'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Time</b></td>
            <td style="padding:6px 10px">'
              . htmlspecialchars(substr((string)($b['start_time'] ?? ''),0,5), ENT_QUOTES, 'UTF-8') . ' – '
              . htmlspecialchars(substr((string)($b['end_time'] ?? ''),0,5), ENT_QUOTES, 'UTF-8') . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Location</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars((string)($b['location'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Total</b></td>
            <td style="padding:6px 10px">' . rwf($b['total_amount']) . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Deposit</b></td>
            <td style="padding:6px 10px">' . rwf($b['deposit_amount']) . '</td></tr>
      </table>
      <p style="margin-top:20px">
        <a href="' . SITE_URL . '/admin/booking-view.php?id=' . (int)$b['id'] . '"
           style="background:#e07a2f;color:#fff;padding:10px 20px;border-radius:20px;text-decoration:none">
          Open in admin dashboard
        </a>
      </p>
    </div>';

    return send_mail(ADMIN_NOTIFY_EMAIL, $subject, $html, (string)($b['email'] ?? ''));
}