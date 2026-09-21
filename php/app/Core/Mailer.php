<?php
/**
 * SMTP mailer (replaces nodemailer) using stream_socket_client.
 * Falls back to PHP mail() when SMTP is not configured. Never throws.
 */
final class Mailer
{
    public static function enabled(): bool
    {
        return Settings::get('mail_enabled') === 'on' && Settings::get('smtp_host') !== '';
    }

    /** @return array{ok:bool,error?:string,transport:string} */
    public static function send(string $to, string $subject, string $html, ?string $text = null): array
    {
        $from = Settings::get('mail_from', 'NobitaHost <noreply@localhost>');
        $boundary = 'nh-' . bin2hex(random_bytes(8));
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'From: ' . $from;
        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'X-Mailer: NobitaHost-PHP';

        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . ($text ?: trim(strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $html))))
            . "\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $html
            . "\r\n--{$boundary}--\r\n";

        if (!self::enabled()) {
            $ok = @mail($to, $subject, $body, implode("\r\n", array_slice($headers, 3)));
            return ['ok' => (bool) $ok, 'transport' => 'mail', 'error' => $ok ? null : 'php mail() unavailable'];
        }

        return self::smtp($to, $subject, $headers, $body);
    }

    private static function smtp(string $to, string $subject, array $headers, string $body): array
    {
        $host = Settings::get('smtp_host');
        $port = (int) (Settings::get('smtp_port') ?: 587);
        $secure = strtolower(Settings::get('smtp_secure', 'false')) === 'true';
        $user = Settings::get('smtp_user');
        $pass = Settings::get('smtp_pass');
        $remote = ($secure && $port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $sock = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) return ['ok' => false, 'transport' => 'smtp', 'error' => "connect: $errstr ($errno)"];

        stream_set_timeout($sock, 15);
        $read = function () use ($sock) {
            $data = '';
            while ($line = fgets($sock, 1024)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return trim($data);
        };
        $cmd = function (string $c) use ($sock, $read) {
            fwrite($sock, $c . "\r\n");
            return $read();
        };

        $read();
        $hostname = gethostname() ?: 'localhost';
        $cmd("EHLO $hostname");
        if (!$secure) {
            $resp = $cmd('STARTTLS');
            if (str_starts_with($resp, '220')) {
                if (@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    $cmd("EHLO $hostname");
                }
            }
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN');
            $cmd(base64_encode($user));
            $resp = $cmd(base64_encode($pass));
            if (!str_starts_with($resp, '235')) {
                fclose($sock);
                return ['ok' => false, 'transport' => 'smtp', 'error' => 'auth failed: ' . $resp];
            }
        }
        $from = Settings::get('mail_from');
        $fromAddr = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : $from;
        $cmd('MAIL FROM: <' . $fromAddr . '>');
        $cmd('RCPT TO: <' . $to . '>');
        $cmd('DATA');
        fwrite($sock, implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $body) . "\r\n.");
        $resp = $read();
        $cmd('QUIT');
        fclose($sock);
        $ok = str_starts_with($resp, '250');
        return ['ok' => $ok, 'transport' => 'smtp', 'error' => $ok ? null : $resp];
    }

    /* ---------------- templates ---------------- */

    public static function template(string $title, string $intro, array $rows = [], ?string $ctaLabel = null, ?string $ctaUrl = null): string
    {
        $accent = Settings::get('accent_color', '#3388ff');
        $name = e(Settings::get('panel_name', 'NobitaHost'));
        $html = '<div style="font-family:Figtree,Inter,system-ui,sans-serif;background:#f4f6fb;padding:32px 12px">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(16,24,40,.08)">'
            . '<div style="background:' . e($accent) . ';padding:22px 26px;color:#fff;font-size:20px;font-weight:700">' . $name . '</div>'
            . '<div style="padding:26px">'
            . '<h2 style="margin:0 0 10px;font-size:20px;color:#101828">' . e($title) . '</h2>'
            . '<p style="margin:0 0 18px;color:#475467;line-height:1.6">' . e($intro) . '</p>';
        if ($rows) {
            $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:18px">';
            foreach ($rows as $k => $v) {
                $html .= '<tr><td style="padding:8px 10px;background:#f9fafb;border-radius:8px;color:#667085;font-size:13px;width:38%">' . e($k) . '</td>'
                    . '<td style="padding:8px 10px;color:#101828;font-size:13px;font-weight:600">' . e($v) . '</td></tr>';
            }
            $html .= '</table>';
        }
        if ($ctaLabel && $ctaUrl) {
            $html .= '<a href="' . e($ctaUrl) . '" style="display:inline-block;background:' . e($accent) . ';color:#fff;padding:12px 22px;border-radius:12px;text-decoration:none;font-weight:600">' . e($ctaLabel) . '</a>';
        }
        $html .= '</div><div style="padding:16px 26px;background:#f9fafb;color:#98a2b3;font-size:12px">© ' . date('Y') . ' ' . $name . '</div></div></div>';
        return $html;
    }
}
