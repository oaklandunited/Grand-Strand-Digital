<?php
/**
 * Grand Strand Digital — AI Project Brief Tool API
 * Endpoint: POST /api/project-brief.php
 *
 * Streams Server-Sent Events (SSE) brief generation.
 *   event: delta  data: {"text": "..."}
 *   event: done   data: {"ok": true}
 *   event: error  data: {"code": "...", "message": "..."}
 */

require __DIR__ . '/_bootstrap.php';

gsd_cors(['POST']);
$config = gsd_config();

if (empty($config['enabled'])) {
    gsd_fail(503, 'The AI Project Brief tool is currently unavailable. Please contact us directly at 843-900-1976.');
}

$data = gsd_input();

$email        = gsd_line($data['email'] ?? '', 254);
$businessName = gsd_line($data['businessName'] ?? '', 150);
$goals        = gsd_text($data['goals'] ?? '', 6001);
$consent      = !empty($data['consent']);
$honeypot     = gsd_line($data['website_hp'] ?? '');
$turnstile    = gsd_line($data['turnstileToken'] ?? '', 4096);

// Validation
if ($honeypot !== '') {
    gsd_fail(400, 'Spam verification failed.');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    gsd_fail(400, 'A valid email address is required.');
}
$goalsLen = mb_strlen($goals);
if ($goalsLen < 20 || $goalsLen > 6000) {
    gsd_fail(400, 'Please provide between 20 and 6,000 characters describing your project goals or challenges.');
}
if (!$consent) {
    gsd_fail(400, 'Consent is required to generate a project brief and receive follow-up.');
}
if (!gsd_verify_turnstile($turnstile)) {
    gsd_fail(400, 'Spam verification failed. Please refresh the page and try again, or call 843-900-1976.');
}
if (!gsd_rate_limit('brief', (int)($config['rate_limit_per_hour'] ?? 6))) {
    gsd_fail(429, 'Rate limit exceeded. Please try again later or call 843-900-1976.');
}

// Load system prompt
$systemPromptFile = __DIR__ . '/system-prompt.php';
$systemPrompt = file_exists($systemPromptFile)
    ? (require $systemPromptFile)
    : 'You are the Grand Strand Digital AI Project Brief Specialist. Ground your advice strictly in Grand Strand Digital services.';

$apiKey = $config['api_key'] ?? '';

// No live key configured: stream a grounded demo brief.
// Output is Markdown rendered by the browser, which escapes it itself, so no HTML escaping here.
if ($apiKey === '' || $apiKey === 'YOUR_OPENAI_API_KEY_HERE') {
    startSseStream();
    $quotedGoals = '> ' . str_replace("\n", "\n> ", $goals);
    $demo = [
        "## 1. Project Summary\n\n",
        "**Business**: " . ($businessName !== '' ? $businessName : 'Prospective Client') . "\n",
        "**Contact**: " . $email . "\n\n",
        "The client is looking to resolve key operational bottlenecks and modernize digital capabilities on the Grand Strand. Based on the stated challenge:\n\n" . $quotedGoals . "\n\n",
        "Grand Strand Digital has analyzed these requirements to structure a high-impact, phased implementation plan.\n\n",
        "## 2. Objectives & Proposed Success Measures\n\n",
        "- **Inquiry Response Time**: Reduce visitor acknowledgment time to under 1 minute, 24/7.\n",
        "- **Lead Capture Lift**: Increase qualified inbound inquiries by 25-35% within 90 days.\n",
        "- **Operational Efficiency**: Eliminate manual intake triage through automated workflow triggers.\n\n",
        "## 3. Recommended Scope\n\n",
        "- **Must-Have Phase 1 Work**: Implement tailored conversational AI intake agent and real-time lead notification workflow.\n",
        "- **Optional Follow-On Phase 2 Work**: Advanced CRM integration, multi-channel SMS notifications, and local SEO landing pages.\n",
        "- **Explicit Exclusions**: Full site rewrite; autonomous pricing commitments without human sign-off.\n\n",
        "## 4. Recommended Grand Strand Digital Services\n\n",
        "1. **Custom AI Agent Development**: Build and ground an interactive agent to qualify visitors and answer inquiries around the clock.\n",
        "2. **Workflow Automation**: Build automated pipelines connecting inquiry captures with instant staff notification alerts.\n",
        "3. **AI Strategy Consulting**: A focused architecture audit to map workflows and establish safety guardrails.\n\n",
        "## 5. Phased Implementation Roadmap\n\n",
        "- **Phase 1: Discovery & Architecture Audit** (Weeks 1-2): System review and escalation rules definition.\n",
        "- **Phase 2: Build & System Integration** (Weeks 3-5): Agent development, prompt calibration, and webhook integration.\n",
        "- **Phase 3: Testing, Enablement & Launch** (Weeks 6-7): Staff training, edge-case verification, and live rollout.\n\n",
        "## 6. Key Discovery Questions\n\n",
        "1. What systems and calendar tools currently manage incoming customer inquiries?\n",
        "2. What are the most frequent repetitive questions your team answers daily?\n",
        "3. Who on your team should receive immediate SMS/email alerts for high-value inquiries?\n",
        "4. What is your preferred timeline for having this capability live in production?\n\n",
        "## 7. Next Step\n\n",
        "Schedule a 30-minute Strategy Call with Grand Strand Digital at **[grandstranddigital.com/contact.html](contact.html)** or call us directly at **843-900-1976**.\n",
    ];
    foreach ($demo as $chunk) {
        sendSseChunk($chunk);
    }
    sendSseDone();
    notifyLead($config, $businessName, $email, $goals);
    exit;
}

// Live streaming request via OpenAI API
startSseStream();

$userContent = "Client Business: " . ($businessName ?: 'Not specified') . "\n"
             . "Client Email: " . $email . "\n"
             . "Project Goals / Challenges:\n" . $goals;

$requestData = [
    'model'       => $config['model'] ?? 'gpt-4o-mini',
    'messages'    => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userContent],
    ],
    'temperature' => 0.4,
    'stream'      => true,
    'max_tokens'  => 1500,
];

$httpStatus = 0;
$sseBuffer  = '';
$errorBody  = '';
$gotText    = false;
$sentDone   = false;

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($requestData),
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$httpStatus) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
            $httpStatus = (int)$m[1];
        }
        return strlen($header);
    },
    CURLOPT_WRITEFUNCTION  => function ($curl, $chunk) use (&$sseBuffer, &$errorBody, &$httpStatus, &$gotText, &$sentDone) {
        if ($httpStatus !== 200) {
            // Error responses are plain JSON, not a stream. Keep it for the log.
            $errorBody .= substr($chunk, 0, 4000);
            return strlen($chunk);
        }
        // Buffer partial lines: a streamed line can be split across network packets.
        $sseBuffer .= $chunk;
        while (($pos = strpos($sseBuffer, "\n")) !== false) {
            $line = trim(substr($sseBuffer, 0, $pos));
            $sseBuffer = substr($sseBuffer, $pos + 1);
            if (strpos($line, 'data: ') !== 0) {
                continue;
            }
            $payload = substr($line, 6);
            if ($payload === '[DONE]') {
                if ($gotText && !$sentDone) {
                    sendSseDone();
                    $sentDone = true;
                }
                continue;
            }
            $json = json_decode($payload, true);
            if (isset($json['choices'][0]['delta']['content']) && $json['choices'][0]['delta']['content'] !== '') {
                $gotText = true;
                sendSseChunk($json['choices'][0]['delta']['content']);
            }
        }
        return strlen($chunk);
    },
]);

curl_exec($ch);
$curlErr = curl_errno($ch) ? curl_error($ch) : '';
curl_close($ch);

$genericError = 'We could not generate your brief right now. Please try again in a moment or call 843-900-1976.';
if ($curlErr !== '') {
    error_log('[gsd] OpenAI request failed: ' . $curlErr);
    sendSseError('upstream_unavailable', $genericError);
} elseif ($httpStatus !== 200) {
    error_log('[gsd] OpenAI returned HTTP ' . $httpStatus . ': ' . $errorBody);
    sendSseError($httpStatus === 429 ? 'upstream_busy' : 'upstream_error', $genericError);
} elseif (!$gotText) {
    error_log('[gsd] OpenAI stream ended without content');
    sendSseError('empty_response', $genericError);
} elseif (!$sentDone) {
    sendSseDone();
}

notifyLead($config, $businessName, $email, $goals);

// Helpers
function notifyLead(array $config, string $businessName, string $email, string $goals): void
{
    if (empty($config['notification_email'])) {
        return;
    }
    $body = "A new project brief was generated on Grand Strand Digital.\n\n"
          . "Business: " . ($businessName ?: 'N/A') . "\n"
          . "Email: " . $email . "\n"
          . "Date/Time: " . date('Y-m-d H:i:s T') . "\n\n"
          . "Stated Goals:\n" . $goals . "\n\n"
          . "-- Grand Strand Digital System";
    gsd_mail($config['notification_email'], 'New AI Project Brief Generated: ' . ($businessName ?: $email), $body, $email);
}

function startSseStream(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    header('X-Accel-Buffering: no');
    flush();
}

function sseSend(string $event, array $payload): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload) . "\n\n";
    flush();
}

function sendSseChunk(string $text): void
{
    sseSend('delta', ['text' => $text]);
}

function sendSseDone(): void
{
    sseSend('done', ['ok' => true]);
}

function sendSseError(string $code, string $message): void
{
    sseSend('error', ['code' => $code, 'message' => $message]);
}
