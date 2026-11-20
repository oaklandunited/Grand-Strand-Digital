<?php
/**
 * Grand Strand Digital — AI Project Brief Tool Configuration
 *
 * Copy this file to `config.php` and fill in your live credentials.
 */

return [
    // LLM Provider settings
    'provider' => 'openai', // 'openai', 'anthropic', or 'compatible'
    'api_key'  => getenv('OPENAI_API_KEY') ?: 'YOUR_OPENAI_API_KEY_HERE',
    'model'    => 'gpt-4o-mini',
    
    // Notification recipient for newly generated project briefs
    'notification_email' => 'hello@grandstranddigital.com',
    'notification_from'  => 'briefs@grandstranddigital.com',
    
    // Optional Cloudflare Turnstile secret key (leave blank to bypass in development)
    'turnstile_secret_key' => getenv('TURNSTILE_SECRET_KEY') ?: '',
    
    // Rate limiting: max accepted brief requests per IP per hour
    'rate_limit_per_hour' => 6,
    
    // Kill switch
    'enabled' => true,
];
