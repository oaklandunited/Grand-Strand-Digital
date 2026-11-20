<?php
/**
 * Grand Strand Digital — AI Project Brief Tool API
 * Endpoint: POST /api/project-brief.php
 *
 * Streams Server-Sent Events (SSE) brief generation.
 */

// Set headers for CORS and SSE
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Method not allowed']);
    exit;
}

// Load config
$configFile = __DIR__ . '/config.php';
$exampleConfigFile = __DIR__ . '/config.example.php';
$config = file_exists($configFile) ? require($configFile) : (file_exists($exampleConfigFile) ? require($exampleConfigFile) : []);

if (empty($config['enabled'])) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'The AI Project Brief tool is currently unavailable. Please contact us directly at 843-900-2032.']);
    exit;
}

// Parse request body
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$email        = isset($data['email']) ? trim($data['email']) : '';
$businessName = isset($data['businessName']) ? trim($data['businessName']) : '';
$goals        = isset($data['goals']) ? trim($data['goals']) : '';
$consent      = !empty($data['consent']);
$honeypot     = isset($data['website_hp']) ? trim($data['website_hp']) : '';
$turnstile    = isset($data['turnstileToken']) ? trim($data['turnstileToken']) : '';

// Validation
if (!empty($honeypot)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Spam verification failed.']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'A valid email address is required.']);
    exit;
}

if (strlen($goals) < 20 || strlen($goals) > 6000) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Please provide between 20 and 6,000 characters describing your project goals or challenges.']);
    exit;
}

if (!$consent) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Consent is required to generate a project brief and receive follow-up.']);
    exit;
}

// Cloudflare Turnstile verification (enforced only when a secret key is configured)
$turnstileSecret = $config['turnstile_secret_key'] ?? '';
if (!empty($turnstileSecret)) {
    $verified = false;
    if (!empty($turnstile)) {
        $vc = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($vc, CURLOPT_POST, true);
        curl_setopt($vc, CURLOPT_POSTFIELDS, http_build_query([
            'secret'   => $turnstileSecret,
            'response' => $turnstile,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]));
        curl_setopt($vc, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($vc, CURLOPT_TIMEOUT, 10);
        $vr = curl_exec($vc);
        curl_close($vc);
        $vj = $vr ? json_decode($vr, true) : null;
        $verified = is_array($vj) && !empty($vj['success']);
    }
    if (!$verified) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => 'Spam verification failed. Please refresh the page and try again, or call 843-900-2032.']);
        exit;
    }
}

// Basic IP rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$ipHash = md5($ip . date('Y-m-d-H'));
$rateFile = sys_get_temp_dir() . '/gsd_brief_limit_' . $ipHash . '.txt';
$currentCount = file_exists($rateFile) ? (int)file_get_contents($rateFile) : 0;
$maxPerHour = $config['rate_limit_per_hour'] ?? 6;

if ($currentCount >= $maxPerHour) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Rate limit exceeded. Please try again later or call 843-900-2032.']);
    exit;
}
file_put_contents($rateFile, (string)($currentCount + 1));

// Load system prompt
$systemPromptFile = __DIR__ . '/system-prompt.php';
$systemPrompt = file_exists($systemPromptFile) 
    ? (require $systemPromptFile) 
    : 'You are the Grand Strand Digital AI Project Brief Specialist. Ground your advice strictly in Grand Strand Digital services.';

// Check API Key
$apiKey = $config['api_key'] ?? '';
if (empty($apiKey) || $apiKey === 'YOUR_OPENAI_API_KEY_HERE') {
    // If no live key is set, stream a helpful grounded demo response
    startSseStream();
    sendSseChunk("## 1. Project Summary\n\n");
    sendSseChunk("**Business**: " . ($businessName ? htmlspecialchars($businessName) : "Prospective Client") . "\n");
    sendSseChunk("**Contact**: " . htmlspecialchars($email) . "\n\n");
    sendSseChunk("The client is looking to resolve key operational bottlenecks and modernize digital capabilities on the Grand Strand. Based on the stated challenge:\n> " . nl2br(htmlspecialchars($goals)) . "\n\n");
    sendSseChunk("Grand Strand Digital has analyzed these requirements to structure a high-impact, phased implementation plan.\n\n");
    sendSseChunk("## 2. Objectives & Proposed Success Measures\n\n");
    sendSseChunk("- **Inquiry Response Time**: Reduce visitor acknowledgment time to under 1 minute, 24/7.\n");
    sendSseChunk("- **Lead Capture Lift**: Increase qualified inbound inquiries by 25-35% within 90 days.\n");
    sendSseChunk("- **Operational Efficiency**: Eliminate manual intake triage through automated workflow triggers.\n\n");
    sendSseChunk("## 3. Recommended Scope\n\n");
    sendSseChunk("- **Must-Have Phase 1 Work**: Implement tailored conversational AI intake agent and real-time lead notification workflow.\n");
    sendSseChunk("- **Optional Follow-On Phase 2 Work**: Advanced CRM integration, multi-channel SMS notifications, and local SEO landing pages.\n");
    sendSseChunk("- **Explicit Exclusions**: Full site rewrite; autonomous pricing commitments without human sign-off.\n\n");
    sendSseChunk("## 4. Recommended Grand Strand Digital Services\n\n");
    sendSseChunk("1. **Custom AI Agent Development**: Build and ground an interactive agent to qualify visitors and answer inquiries around the clock.\n");
    sendSseChunk("2. **Workflow Automation**: Build automated pipelines connecting inquiry captures with instant staff notification alerts.\n");
    sendSseChunk("3. **AI Strategy Consulting**: A focused architecture audit to map workflows and establish safety guardrails.\n\n");
    sendSseChunk("## 5. Phased Implementation Roadmap\n\n");
    sendSseChunk("- **Phase 1: Discovery & Architecture Audit** (Weeks 1-2): System review and escalation rules definition.\n");
    sendSseChunk("- **Phase 2: Build & System Integration** (Weeks 3-5): Agent development, prompt calibration, and webhook integration.\n");
    sendSseChunk("- **Phase 3: Testing, Enablement & Launch** (Weeks 6-7): Staff training, edge-case verification, and live rollout.\n\n");
    sendSseChunk("## 6. Key Discovery Questions\n\n");
    sendSseChunk("1. What systems and calendar tools currently manage incoming customer inquiries?\n");
    sendSseChunk("2. What are the most frequent repetitive questions your team answers daily?\n");
    sendSseChunk("3. Who on your team should receive immediate SMS/email alerts for high-value inquiries?\n");
    sendSseChunk("4. What is your preferred timeline for having this capability live in production?\n\n");
    sendSseChunk("## 7. Next Step\n\n");
    sendSseChunk("Schedule a 30-minute Strategy Call with Grand Strand Digital at **[grandstranddigital.com/contact.html](contact.html)** or call us directly at **843-900-2032**.\n");
    sendSseDone();
    exit;
}

// Live Streaming Request via OpenAI API
startSseStream();

$userContent = "Client Business: " . ($businessName ?: 'Not specified') . "\n";
$userContent .= "Client Email: " . $email . "\n";
$userContent .= "Project Goals / Challenges:\n" . $goals;

$requestData = [
    'model' => $config['model'] ?? 'gpt-4o-mini',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userContent]
    ],
    'temperature' => 0.4,
    'stream' => true,
    'max_tokens' => 1500
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: ' . 'Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
$sseBuffer = '';
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$sseBuffer) {
    // Buffer partial lines: a streamed line can be split across network packets.
    $sseBuffer .= $data;
    while (($pos = strpos($sseBuffer, "\n")) !== false) {
        $line = trim(substr($sseBuffer, 0, $pos));
        $sseBuffer = substr($sseBuffer, $pos + 1);
        if (strpos($line, 'data: ') !== 0) {
            continue;
        }
        $payload = substr($line, 6);
        if ($payload === '[DONE]') {
            sendSseDone();
            continue;
        }
        $json = json_decode($payload, true);
        if (isset($json['choices'][0]['delta']['content'])) {
            sendSseChunk($json['choices'][0]['delta']['content']);
        }
    }
    return strlen($data);
});

$result = curl_exec($ch);
if (curl_errno($ch)) {
    sendSseError('curl_error', curl_error($ch));
}
curl_close($ch);

// Send background lead notification email if configured
if (!empty($config['notification_email'])) {
    $to = $config['notification_email'];
    $subject = "New AI Project Brief Generated: " . ($businessName ?: $email);
    $body = "A new project brief was generated on Grand Strand Digital.\n\n"
          . "Business: " . ($businessName ?: 'N/A') . "\n"
          . "Email: " . $email . "\n"
          . "Date/Time: " . date('Y-m-d H:i:s T') . "\n\n"
          . "Stated Goals:\n" . $goals . "\n\n"
          . "-- Grand Strand Digital System";
    $headers = "From: " . ($config['notification_from'] ?? 'noreply@grandstranddigital.com') . "\r\n"
             . "Reply-To: " . $email . "\r\n"
             . "X-Mailer: PHP/" . phpversion();
    @mail($to, $subject, $body, $headers);
}

// Helpers
function startSseStream() {
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    flush();
}

function sendSseChunk($text) {
    echo "event: delta\n";
    echo "data: " . json_encode(['text' => $text]) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

function sendSseDone() {
    echo "event: done\n";
    echo "data: " . json_encode(['ok' => true]) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

function sendSseError($code, $message) {
    echo "event: error\n";
    echo "data: " . json_encode(['code' => $code, 'message' => $message]) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}
