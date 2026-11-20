<?php
/**
 * Grand Strand Digital — API configuration (AI Project Brief + contact form)
 *
 * Copy this file to `config.php` on the server and fill in your live credentials.
 * config.php is git-ignored, excluded from deploys and blocked from web access.
 */

return [
    // LLM Provider settings
    'provider' => 'openai',
    'api_key'  => getenv('OPENAI_API_KEY') ?: 'YOUR_OPENAI_API_KEY_HERE',
    'model'    => 'gpt-4o-mini',

    // Where new briefs and contact-form leads are emailed
    'notification_email' => 'hello@grandstranddigital.com',
    'notification_from'  => 'briefs@grandstranddigital.com',

    // Contact-form leads are also appended here as a backup (keep it OUTSIDE public_html).
    // Default: one directory above public_html, e.g. /home/customer/www/grandstranddigital.com/gsd-leads.jsonl
    // 'lead_log_path' => '/home/customer/www/grandstranddigital.com/gsd-leads.jsonl',

    // Browsers may only call the API from these origins (same-origin is always allowed)
    'allowed_origins' => [
        'https://grandstranddigital.com',
        'https://www.grandstranddigital.com',
    ],

    // Cloudflare Turnstile secret key. With require_turnstile on, requests are
    // REFUSED if the secret is missing, so a config mistake can't disable bot protection.
    'turnstile_secret_key' => getenv('TURNSTILE_SECRET_KEY') ?: '',
    'require_turnstile'    => true, // set false only for local development

    // Rate limiting: max accepted requests per IP per rolling hour
    'rate_limit_per_hour'         => 6,
    'contact_rate_limit_per_hour' => 5,

    // Kill switch for the AI brief tool
    'enabled' => true,
];
