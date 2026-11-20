<?php
/**
 * Grand Strand Digital — shared helpers for the API endpoints.
 * Blocked from direct web access by api/.htaccess.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

function gsd_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $configFile  = __DIR__ . '/config.php';
    $exampleFile = __DIR__ . '/config.example.php';
    if (file_exists($configFile)) {
        $config = require $configFile;
    } elseif (file_exists($exampleFile)) {
        error_log('[gsd] api/config.php is missing; falling back to config.example.php');
        $config = require $exampleFile;
    } else {
        $config = [];
    }
    return is_array($config) ? $config : [];
}

/** Send JSON and stop. */
function gsd_json(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body);
    exit;
}

function gsd_fail(int $status, string $message): void
{
    gsd_json($status, ['error' => true, 'message' => $message]);
}

/**
 * Same-origin by default. Cross-origin browser requests are only allowed for
 * origins listed in config 'allowed_origins'. Requests from other origins are
 * rejected outright so third-party sites cannot spend the OpenAI budget.
 */
function gsd_cors(array $methods = ['POST']): void
{
    $config  = gsd_config();
    $allowed = $config['allowed_origins'] ?? [
        'https://grandstranddigital.com',
        'https://www.grandstranddigital.com',
    ];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host   = $_SERVER['HTTP_HOST'] ?? '';

    header('Vary: Origin');
    if ($origin !== '') {
        $sameOrigin = in_array($origin, ['https://' . $host, 'http://' . $host], true);
        if (!$sameOrigin && !in_array($origin, $allowed, true)) {
            gsd_fail(403, 'Origin not allowed.');
        }
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . implode(', ', array_merge($methods, ['OPTIONS'])));
        header('Access-Control-Allow-Headers: Content-Type');
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if (!in_array($method, $methods, true)) {
        gsd_fail(405, 'Method not allowed');
    }
}

/** JSON body, falling back to form fields. */
function gsd_input(): array
{
    $raw  = file_get_contents('php://input', false, null, 0, 65536);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : $_POST;
}

/** Trimmed single-line string, capped in length, with control characters removed. */
function gsd_line($value, int $max = 200): string
{
    if (is_array($value)) {
        $value = implode(', ', array_map('strval', $value));
    }
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$value);
    return mb_substr(trim((string)$value), 0, $max);
}

/** Trimmed multi-line string: keeps newlines/tabs, removes other control characters. */
function gsd_text($value, int $max = 6000): string
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$value);
    return mb_substr(trim((string)$value), 0, $max);
}

/**
 * Real client IP. Only trusts CF-Connecting-IP when the request actually
 * arrives from a Cloudflare edge address; otherwise the header is spoofable.
 */
function gsd_client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $cf     = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && gsd_is_cloudflare($remote)) {
        return $cf;
    }
    return $remote;
}

function gsd_is_cloudflare(string $ip): bool
{
    // https://www.cloudflare.com/ips/ — review occasionally.
    $ranges = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    foreach ($ranges as $cidr) {
        [$net, $bits] = explode('/', $cidr);
        $netBin = inet_pton($net);
        if (strlen($netBin) !== strlen($bin)) {
            continue;
        }
        $bytes = intdiv((int)$bits, 8);
        $rem   = (int)$bits % 8;
        if (substr($bin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            continue;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr((0xFF << (8 - $rem)) & 0xFF);
        if ((($bin[$bytes] ^ $netBin[$bytes]) & $mask) === "\0") {
            return true;
        }
    }
    return false;
}

/**
 * Sliding one-hour window per IP, stored as timestamps in a locked file so
 * concurrent requests cannot both slip under the limit.
 * Returns true when the request is allowed (and records it).
 */
function gsd_rate_limit(string $bucket, int $maxPerHour): bool
{
    $dir = rtrim(sys_get_temp_dir(), '/') . '/gsd_rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $key  = hash('sha256', $bucket . '|' . gsd_client_ip() . '|' . __DIR__);
    $file = $dir . '/' . $key;
    $fh   = @fopen($file, 'c+');
    if (!$fh) {
        error_log('[gsd] rate limit store unavailable: ' . $file);
        return true; // fail open rather than block real customers
    }
    flock($fh, LOCK_EX);
    $now   = time();
    $stamp = json_decode((string)stream_get_contents($fh), true);
    $stamp = array_values(array_filter(is_array($stamp) ? $stamp : [], fn($t) => is_int($t) && $t > $now - 3600));
    $allowed = count($stamp) < $maxPerHour;
    if ($allowed) {
        $stamp[] = $now;
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($stamp));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $allowed;
}

/**
 * Cloudflare Turnstile. Enforced when a secret key is configured.
 * When 'require_turnstile' is true and no secret is set, requests are refused
 * (fail closed) so a missing key can't silently disable bot protection.
 */
function gsd_verify_turnstile(string $token): bool
{
    $config  = gsd_config();
    $secret  = $config['turnstile_secret_key'] ?? '';
    $require = !empty($config['require_turnstile']);

    if ($secret === '') {
        if ($require) {
            error_log('[gsd] require_turnstile is on but turnstile_secret_key is empty; refusing request');
            return false;
        }
        return true;
    }
    if ($token === '') {
        return false;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => gsd_client_ip(),
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $json = $res ? json_decode($res, true) : null;
    return is_array($json) && !empty($json['success']);
}

/** Plain-text email with header-injection-safe subject and headers. */
function gsd_mail(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    $config = gsd_config();
    $from   = gsd_line($config['notification_from'] ?? 'noreply@grandstranddigital.com', 200);
    $subject = gsd_line($subject, 150);
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'noreply@grandstranddigital.com';
    }
    $headers = [
        'From: Grand Strand Digital <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) {
        error_log('[gsd] mail() failed for subject: ' . $subject);
    }
    return $ok;
}
