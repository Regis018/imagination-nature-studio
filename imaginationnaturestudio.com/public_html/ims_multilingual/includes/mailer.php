<?php
/**
 * Lightweight SMTP mailer.
 * Sends email via your @imaginationnaturestudio.com mailbox
 * using TLS on port 587 (or SSL on 465).
 *
 * No external library required — uses PHP sockets.
 */
require_once __DIR__ . '/../config.php';

function send_mail(string $to, string $subject, string $htmlBody, string $replyTo = ''): bool
{
    if (!MAIL_ENABLED) {
        @error_log('[mail] disabled — skipping ' . $subject, 3, ERROR_LOG_FILE);
        return false;
    }

    $host    = MAIL_HOST;
    $port    = MAIL_PORT;
    $user    = MAIL_USER;
    $pass    = MAIL_PASS;
    $secure  = MAIL_SECURE;   // 'tls' or 'ssl'
    $from    = MAIL_FROM;
    $fromName= MAIL_FROM_NAME;

    /* Plain-text fallback */
    $plain = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $htmlBody)));

    /* Build MIME body */
    $boundary = 'INS_' . bin2hex(random_bytes(8));

    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    if ($replyTo) $headers .= "Reply-To: <{$replyTo}>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@" . preg_replace('/^.*@/', '', $from) . ">\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plain)) . "\r\n";

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

    $body .= "--{$boundary}--\r\n";

    $data = $headers . "\r\n" . $body;

    /* Connect */
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ]);
    $fp = @stream_socket_client("$remote:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        @error_log("[mail] connect failed: $errstr ($errno)", 3, ERROR_LOG_FILE);
        return false;
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $r = '';
        while ($line = fgets($fp, 515)) {
            $r .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $r;
    };
    $write = function ($cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };

    $expect = function ($code, $label) use ($read) {
        $resp = $read();
        if (substr($resp, 0, 3) !== $code) {
            @error_log("[mail] $label failed: " . trim($resp), 3, ERROR_LOG_FILE);
            return false;
        }
        return true;
    };

    if (!$expect('220', 'greeting')) { fclose($fp); return false; }

    $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if (!$expect('250', 'EHLO')) { fclose($fp); return false; }

    if ($secure === 'tls') {
        $write("STARTTLS");
        if (!$expect('220', 'STARTTLS')) { fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            @error_log('[mail] TLS negotiation failed', 3, ERROR_LOG_FILE);
            fclose($fp); return false;
        }
        $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$expect('250', 'EHLO-after-TLS')) { fclose($fp); return false; }
    }

    $write("AUTH LOGIN");
    if (!$expect('334', 'AUTH')) { fclose($fp); return false; }

    $write(base64_encode($user));
    if (!$expect('334', 'username')) { fclose($fp); return false; }

    $write(base64_encode($pass));
    if (!$expect('235', 'password')) { fclose($fp); return false; }

    $write("MAIL FROM:<{$from}>");
    if (!$expect('250', 'MAIL FROM')) { fclose($fp); return false; }

    $write("RCPT TO:<{$to}>");
    if (!$expect('250', 'RCPT TO')) { fclose($fp); return false; }

    $write("DATA");
    if (!$expect('354', 'DATA')) { fclose($fp); return false; }

    fwrite($fp, $data . "\r\n.\r\n");
    if (!$expect('250', 'message-accepted')) { fclose($fp); return false; }

    $write("QUIT");
    fclose($fp);

    @error_log('[mail] sent OK to ' . $to . ' — ' . $subject, 3, ERROR_LOG_FILE);
    return true;
}

/**
 * Send a nicely-formatted booking email to the customer.
 */
function send_booking_email(array $b, string $serviceName, ?string $packageName, string $receiptUrl): bool
{
    $to      = $b['email'];
    $subject = 'Your booking ' . $b['booking_number'] . ' — Imagination Nature Studio';

    $html = '
    <div style="font-family:Arial,Helvetica,sans-serif;background:#f5f2ee;padding:30px 0">
      <div style="max-width:600px;margin:auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <div style="background:#0e0d0c;padding:24px;color:#f3ece3;text-align:center">
          <div style="font-size:20px;font-weight:bold;letter-spacing:1px">IMAGINATION NATURE STUDIO</div>
          <div style="font-size:11px;letter-spacing:3px;color:#a79e95;margin-top:4px">PHOTOGRAPHY &amp; CREATIVE STUDIO</div>
        </div>
        <div style="padding:30px">
          <h2 style="color:#e07a2f;margin:0 0 8px;font-size:18px">Booking received — thank you!</h2>
          <p style="color:#333;line-height:1.6">Hi ' . htmlspecialchars($b['full_name']) . ',</p>
          <p style="color:#333;line-height:1.6">
            We have received your booking request. Our team will review it and contact you
            to confirm the appointment.
          </p>

          <table style="width:100%;border-collapse:collapse;margin:20px 0">
            <tr><td style="padding:8px 0;color:#666">Booking number</td>
                <td style="padding:8px 0;text-align:right;color:#e07a2f;font-weight:bold">'
                . htmlspecialchars($b['booking_number']) . '</td></tr>
            <tr><td style="padding:8px 0;color:#666">Service</td>
                <td style="padding:8px 0;text-align:right">' . htmlspecialchars($serviceName) . '</td></tr>';

    if ($packageName) {
        $html .= '<tr><td style="padding:8px 0;color:#666">Package</td>
                    <td style="padding:8px 0;text-align:right">' . htmlspecialchars($packageName) . '</td></tr>';
    }

    $html .= '
            <tr><td style="padding:8px 0;color:#666">Date</td>
                <td style="padding:8px 0;text-align:right">' . htmlspecialchars($b['booking_date']) . '</td></tr>
            <tr><td style="padding:8px 0;color:#666">Time</td>
                <td style="padding:8px 0;text-align:right">'
                . htmlspecialchars(substr($b['start_time'],0,5)) . ' – '
                . htmlspecialchars(substr($b['end_time'],0,5)) . '</td></tr>
            <tr><td style="padding:8px 0;color:#666">Location</td>
                <td style="padding:8px 0;text-align:right">' . htmlspecialchars($b['location']) . '</td></tr>
          </table>

          <div style="background:#faf7f3;border:1px solid #eee;border-radius:8px;padding:16px;margin:20px 0">
            <table style="width:100%;border-collapse:collapse">
              <tr><td style="color:#666">Total</td>
                  <td style="text-align:right;font-weight:bold">' . rwf($b['total_amount']) . '</td></tr>
              <tr><td style="color:#666">Deposit (30%)</td>
                  <td style="text-align:right">' . rwf($b['deposit_amount']) . '</td></tr>
              <tr><td style="color:#666">Amount paid</td>
                  <td style="text-align:right">' . rwf($b['amount_paid']) . '</td></tr>
              <tr><td style="color:#666">Balance</td>
                  <td style="text-align:right;font-weight:bold">' . rwf($b['balance']) . '</td></tr>
            </table>
          </div>

          <div style="text-align:center;margin:26px 0">
            <a href="' . htmlspecialchars($receiptUrl) . '"
               style="background:#e07a2f;color:#fff;text-decoration:none;padding:12px 24px;border-radius:24px;font-weight:bold;display:inline-block">
              Download PDF receipt
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

    return send_mail($to, $subject, $html, MAIL_FROM);
}

/**
 * Send a new-booking alert to the admin.
 */
function send_admin_booking_email(array $b, string $serviceName, ?string $packageName): bool
{
    $subject = 'New Booking – Imagination Nature Studio (' . $b['booking_number'] . ')';

    $html = '
    <div style="font-family:Arial,Helvetica,sans-serif;padding:20px">
      <h2 style="color:#e07a2f;margin-top:0">New booking received</h2>
      <table style="border-collapse:collapse;font-size:14px">
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Booking No</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($b['booking_number']) . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Client</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($b['full_name']) . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Phone</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($b['phone']) . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Email</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($b['email']) . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Service</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($serviceName) . '</td></tr>';

    if ($packageName) {
        $html .= '<tr><td style="padding:6px 10px;background:#f7f4f0"><b>Package</b></td>
                      <td style="padding:6px 10px">' . htmlspecialchars($packageName) . '</td></tr>';
    }

    $html .= '
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Date</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($b['booking_date']) . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Time</b></td>
            <td style="padding:6px 10px">'
              . htmlspecialchars(substr($b['start_time'],0,5)) . ' – '
              . htmlspecialchars(substr($b['end_time'],0,5)) . '</td></tr>
        <tr><td style="padding:6px 10px;background:#f7f4f0"><b>Location</b></td>
            <td style="padding:6px 10px">' . htmlspecialchars($b['location']) . '</td></tr>
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

    return send_mail(ADMIN_NOTIFY_EMAIL, $subject, $html, $b['email']);
}