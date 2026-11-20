<?php
/**
 * Grand Strand Digital — Contact form handler
 * Endpoint: POST /api/contact.php  (JSON or form-encoded)
 *
 * Emails the inquiry to config 'notification_email' and appends a backup copy
 * to a JSON-lines file outside the web root, so a lead is never lost even if
 * mail delivery fails.
 */

require __DIR__ . '/_bootstrap.php';

gsd_cors(['POST']);
$config = gsd_config();
$data   = gsd_input();

// Honeypot: pretend success so bots learn nothing.
if (gsd_line($data['website_hp'] ?? '') !== '') {
    gsd_json(200, ['ok' => true]);
}

// Short homepage form: email only. Relies on honeypot + a tighter rate limit
// (no Turnstile widget there), and is clearly labeled in the notification.
$quick = gsd_line($data['source'] ?? '') === 'homepage-cta';

$lead = [
    'firstName'  => gsd_line($data['firstName'] ?? '', 80),
    'lastName'   => gsd_line($data['lastName'] ?? '', 80),
    'email'      => gsd_line($data['email'] ?? '', 254),
    'phone'      => gsd_line($data['phone'] ?? '', 40),
    'company'    => gsd_line($data['company'] ?? '', 150),
    'website'    => gsd_line($data['website'] ?? '', 300),
    'services'   => gsd_line($data['services'] ?? '', 300),
    'industry'   => gsd_line($data['industry'] ?? '', 60),
    'budget'     => gsd_line($data['budget'] ?? '', 60),
    'challenge'  => gsd_text($data['challenge'] ?? '', 5000),
    'timeline'   => gsd_line($data['timeline'] ?? '', 60),
    'referral'   => gsd_line($data['referral'] ?? '', 60),
    'additional' => gsd_text($data['additional'] ?? '', 3000),
];

$missing = [];
foreach ($quick ? [] : ['firstName', 'lastName', 'company', 'challenge'] as $field) {
    if ($lead[$field] === '') {
        $missing[] = $field;
    }
}
if ($missing) {
    gsd_fail(400, 'Please complete all required fields.');
}
if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
    gsd_fail(400, 'Please enter a valid email address.');
}

if (!$quick && !gsd_verify_turnstile(gsd_line($data['turnstileToken'] ?? $data['cf-turnstile-response'] ?? '', 4096))) {
    gsd_fail(400, 'Spam check failed. Please refresh the page and try again, or call 843-900-1976.');
}

$limit = $quick ? 3 : (int)($config['contact_rate_limit_per_hour'] ?? 5);
if (!gsd_rate_limit($quick ? 'contact-quick' : 'contact', $limit)) {
    gsd_fail(429, 'Too many submissions from your connection. Please call us at 843-900-1976.');
}

$received = date('Y-m-d H:i:s T');

// 1) Durable backup outside public_html (default: one level above the web root).
$logPath = $config['lead_log_path'] ?? dirname(__DIR__, 2) . '/gsd-leads.jsonl';
$stored  = @file_put_contents(
    $logPath,
    json_encode(['received' => $received, 'source' => $quick ? 'homepage-cta' : 'contact-page', 'ip' => gsd_client_ip()] + $lead, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX
) !== false;
if (!$stored) {
    error_log('[gsd] could not write lead backup to ' . $logPath);
}

// 2) Email notification.
$name = trim($lead['firstName'] . ' ' . $lead['lastName']) ?: 'Website visitor';
if ($quick) {
    $lead['company'] = $lead['company'] ?: 'Homepage email signup';
    $lead['challenge'] = $lead['challenge'] ?: 'Submitted the homepage "Let\'s Talk" form and asked about a free AI strategy session. Reply to schedule.';
}
$body = "New strategy call request from grandstranddigital.com\n\n"
      . "Name:      {$name}\n"
      . "Email:     {$lead['email']}\n"
      . "Phone:     " . ($lead['phone'] ?: 'n/a') . "\n"
      . "Company:   {$lead['company']}\n"
      . "Website:   " . ($lead['website'] ?: 'n/a') . "\n"
      . "Services:  " . ($lead['services'] ?: 'n/a') . "\n"
      . "Industry:  " . ($lead['industry'] ?: 'n/a') . "\n"
      . "Budget:    " . ($lead['budget'] ?: 'n/a') . "\n"
      . "Timeline:  " . ($lead['timeline'] ?: 'n/a') . "\n"
      . "Referral:  " . ($lead['referral'] ?: 'n/a') . "\n"
      . "Received:  {$received}\n\n"
      . "Biggest challenge:\n{$lead['challenge']}\n\n"
      . ($lead['additional'] !== '' ? "Additional notes:\n{$lead['additional']}\n\n" : '')
      . "-- Grand Strand Digital website";

$to     = $config['notification_email'] ?? 'hello@grandstranddigital.com';
$mailed = gsd_mail($to, 'New strategy call request: ' . $lead['company'] . ' (' . $name . ')', $body, $lead['email']);

if (!$mailed && !$stored) {
    gsd_fail(500, 'We could not send your message. Please call us at 843-900-1976 or email hello@grandstranddigital.com.');
}

gsd_json(200, ['ok' => true]);
