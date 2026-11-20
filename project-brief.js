/**
 * Grand Strand Digital — AI Project Brief Tool
 * Client-Side Intake & Streaming Engine
 */

(function() {
  'use strict';

  // DOM Elements
  const form = document.getElementById('briefForm');
  const businessNameInput = document.getElementById('businessName');
  const emailInput = document.getElementById('email');
  const goalsInput = document.getElementById('goals');
  const consentInput = document.getElementById('consent');
  const honeypotInput = document.getElementById('website_hp');
  const submitBtn = document.getElementById('submitBtn');
  const stopBtn = document.getElementById('stopBtn');
  const charCounter = document.getElementById('charCount');

  // Containers & Views
  const idleView = document.getElementById('idleView');
  const outputContainer = document.getElementById('outputContainer');
  const briefContent = document.getElementById('briefContent');
  const statusBadge = document.getElementById('statusBadge');
  const actionToolbar = document.getElementById('actionBar');
  const copyBtn = document.getElementById('copyBtn');
  const downloadBtn = document.getElementById('downloadBtn');
  const reviseBtn = document.getElementById('reviseBtn');
  const resetBtn = document.getElementById('resetBtn');
  const toast = document.getElementById('briefToast');

  // State
  let abortController = null;
  let rawMarkdownBuffer = '';
  let isGenerating = false;

  // Starter Presets
  const presets = {
    hospitality: {
      business: 'Palmetto Shores Resort & Vacation Rentals',
      goals: 'We manage 45 boutique beachfront rental units in Myrtle Beach. During the summer and peak golf weekends, our front desk is completely swamped with repetitive guest questions: check-in instructions, pool hours, parking passes, and restaurant recommendations. Furthermore, when potential guests ask about booking availability after 7 PM, nobody answers and we lose direct bookings to third-party OTAs. We want an automated 24/7 AI guest concierge that can answer guest questions instantly, qualify booking inquiries, and escalate maintenance emergencies immediately to our on-call property manager.'
    },
    contractor: {
      business: 'Coastal Carolina HVAC & Roofing',
      goals: 'We are a residential and commercial contractor serving Horry and Georgetown counties. We get 15-25 calls and website inquiries a day, especially during storm season. Most callers have urgent AC or roofing repair needs, but after business hours they just reach a voicemail and often call a competitor before we can call back in the morning. We need an automated lead qualification intake that gathers job details, property address, and urgency level immediately, schedules priority diagnostic appointments, and alerts our on-call technician for real emergencies.'
    },
    professional: {
      business: 'Grand Strand Family Medicine & Aesthetics',
      goals: 'Our front-office medical staff spends 4+ hours a day on the phone answering repetitive questions about insurance acceptance, appointment availability, and service pricing for our wellness and aesthetic treatments. We want to streamline our patient intake on the website, allowing patients to get immediate answers to common procedure questions, understand insurance guidelines, and be seamlessly guided into our online scheduling portal without staff phone tag.'
    }
  };

  // Attach Preset Buttons
  document.querySelectorAll('.preset-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      const type = pill.dataset.preset;
      if (presets[type]) {
        businessNameInput.value = presets[type].business;
        goalsInput.value = presets[type].goals;
        updateCharCount();
        goalsInput.focus();
        showToast('Template loaded: ' + presets[type].business);
      }
    });
  });

  // Character Counter
  function updateCharCount() {
    if (charCounter && goalsInput) {
      const len = goalsInput.value.length;
      charCounter.textContent = `${len.toLocaleString()} / 6,000 characters (min. 20)`;
      charCounter.style.color = len < 20 ? 'var(--text-muted)' : (len > 5800 ? 'var(--accent)' : 'var(--tertiary)');
    }
  }
  if (goalsInput) {
    goalsInput.addEventListener('input', updateCharCount);
    updateCharCount();
  }

  // Toast Feedback Helper
  function showToast(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  // Set Visual State
  function setState(state, message) {
    if (state === 'generating') {
      isGenerating = true;
      idleView.style.display = 'none';
      outputContainer.style.display = 'block';
      statusBadge.className = 'status-badge status-generating';
      statusBadge.innerHTML = '<span class="status-pulse"></span> Generating Brief...';
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.6';
      stopBtn.style.display = 'inline-flex';
      actionToolbar.style.display = 'none';
    } else if (state === 'complete') {
      isGenerating = false;
      statusBadge.className = 'status-badge status-complete';
      statusBadge.innerHTML = '&#10003; Brief Ready';
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      stopBtn.style.display = 'none';
      actionToolbar.style.display = 'flex';
    } else if (state === 'stopped') {
      isGenerating = false;
      statusBadge.className = 'status-badge status-stopped';
      statusBadge.innerHTML = '&#9208; Generation Stopped';
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      stopBtn.style.display = 'none';
      actionToolbar.style.display = 'flex';
    } else if (state === 'error') {
      isGenerating = false;
      statusBadge.className = 'status-badge status-error';
      statusBadge.innerHTML = '&#9888; ' + (message || 'Generation Error');
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      stopBtn.style.display = 'none';
      actionToolbar.style.display = 'flex';
    }
  }

  // Form Validation
  function validateForm() {
    let isValid = true;
    const emailVal = emailInput.value.trim();
    const goalsVal = goalsInput.value.trim();

    // Reset error states
    document.querySelectorAll('.form-group').forEach(g => g.classList.remove('error'));

    if (!emailVal || !emailVal.includes('@') || !emailVal.includes('.')) {
      emailInput.closest('.form-group').classList.add('error');
      isValid = false;
    }
    if (goalsVal.length < 20 || goalsVal.length > 6000) {
      goalsInput.closest('.form-group').classList.add('error');
      isValid = false;
    }
    if (!consentInput.checked) {
      consentInput.closest('.checkbox-label').style.borderColor = 'var(--accent)';
      isValid = false;
    } else {
      consentInput.closest('.checkbox-label').style.borderColor = 'var(--border)';
    }

    return isValid;
  }

  // Stop Generation
  stopBtn.addEventListener('click', () => {
    if (abortController) {
      abortController.abort();
      rawMarkdownBuffer += '\n\n*-- [Brief generation stopped by user] --*';
      renderMarkdown(rawMarkdownBuffer);
      setState('stopped');
      showToast('Generation stopped');
    }
  });

  // Copy to Clipboard
  copyBtn.addEventListener('click', () => {
    if (!rawMarkdownBuffer) return;
    navigator.clipboard.writeText(rawMarkdownBuffer).then(() => {
      showToast('Markdown brief copied to clipboard!');
    }).catch(() => {
      showToast('Unable to copy to clipboard.');
    });
  });

  // Download Markdown File
  downloadBtn.addEventListener('click', () => {
    if (!rawMarkdownBuffer) return;
    const blob = new Blob([rawMarkdownBuffer], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const safeName = (businessNameInput.value.trim() || 'Grand-Strand-Digital')
      .replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
    a.href = url;
    a.download = `${safeName}-ai-project-brief.md`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast('Download started');
  });

  // Revise Inputs
  reviseBtn.addEventListener('click', () => {
    goalsInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    goalsInput.focus();
    showToast('Edit your inputs and click generate again.');
  });

  // Start Over
  resetBtn.addEventListener('click', () => {
    if (confirm('Start over? This will clear the generated brief.')) {
      form.reset();
      updateCharCount();
      rawMarkdownBuffer = '';
      briefContent.innerHTML = '';
      idleView.style.display = 'block';
      outputContainer.style.display = 'none';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });

  // Stream Form Submission
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!validateForm()) {
      showToast('Please correct highlighted fields.');
      return;
    }

    if (honeypotInput && honeypotInput.value) {
      console.warn('Bot detected via honeypot.');
      return;
    }

    // Scroll output card into view on small devices
    if (window.innerWidth < 1024) {
      outputContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    setState('generating');
    rawMarkdownBuffer = '';
    briefContent.innerHTML = '<div class="brief-placeholder"><span class="spinner" style="border-top-color:var(--accent);width:20px;height:20px"></span> Grounding requirements in Grand Strand Digital catalog...</div>';

    abortController = new AbortController();

    const payload = {
      businessName: businessNameInput.value.trim(),
      email: emailInput.value.trim(),
      goals: goalsInput.value.trim(),
      consent: consentInput.checked,
      website_hp: honeypotInput ? honeypotInput.value : '',
      turnstileToken: (form.querySelector('[name="cf-turnstile-response"]') || {}).value || ''
    };

    try {
      const response = await fetch('api/project-brief.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        signal: abortController.signal
      });
      // Turnstile tokens are single-use: request a fresh one for the next submit
      if (window.turnstile) { try { window.turnstile.reset(); } catch (_) {} }

      if (!response.ok) {
        let errMessage = 'Server responded with status ' + response.status;
        try {
          const errData = await response.json();
          if (errData.message) errMessage = errData.message;
        } catch (_) {}
        throw new Error(errMessage);
      }

      // Check if response is Server-Sent Events stream
      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('text/event-stream')) {
        const reader = response.body.getReader();
        const decoder = new TextDecoder('utf-8');
        let streamBuffer = '';

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;

          streamBuffer += decoder.decode(value, { stream: true });
          const lines = streamBuffer.split('\n\n');
          streamBuffer = lines.pop(); // Keep partial line

          for (const block of lines) {
            const trimmed = block.trim();
            if (!trimmed) continue;

            const eventMatch = trimmed.match(/^event:\s*(\w+)/m);
            const dataMatch = trimmed.match(/^data:\s*(.*)$/m);

            const event = eventMatch ? eventMatch[1] : 'message';
            const dataStr = dataMatch ? dataMatch[1] : '';

            if (event === 'delta') {
              try {
                const parsed = JSON.parse(dataStr);
                if (parsed.text) {
                  rawMarkdownBuffer += parsed.text;
                  renderMarkdown(rawMarkdownBuffer);
                }
              } catch (_) {
                rawMarkdownBuffer += dataStr;
                renderMarkdown(rawMarkdownBuffer);
              }
            } else if (event === 'done') {
              setState('complete');
              showToast('Project brief successfully generated!');
              return;
            } else if (event === 'error') {
              let message = 'Stream error occurred.';
              try { message = JSON.parse(dataStr).message || message; } catch (_) {}
              throw new Error(message);
            }
          }
        }
        // Stream closed without a "done" event
        if (!rawMarkdownBuffer.trim()) throw new Error('');
        setState('complete');
      } else {
        // Fallback for direct JSON responses
        const data = await response.json();
        if (data.markdown) {
          rawMarkdownBuffer = data.markdown;
          renderMarkdown(rawMarkdownBuffer);
          setState('complete');
        } else {
          throw new Error(data.message || 'Unexpected response format.');
        }
      }
    } catch (err) {
      if (err.name === 'AbortError') return;

      console.warn('Backend stream unavailable or error:', err && err.message);

      // Only simulate a brief for local/offline previews. On the live site, show a real error.
      const isLocalPreview = location.protocol === 'file:' || /^(localhost|127\.0\.0\.1)$/.test(location.hostname);
      if (isLocalPreview) {
        simulateClientGeneration(payload);
        return;
      }

      let msg = (err && err.message) ? err.message : '';
      if (!msg || /^Server responded with status|Failed to fetch|NetworkError|Load failed|Unexpected response/i.test(msg)) {
        msg = 'We could not generate your brief right now. Please try again in a moment.';
      }
      if (msg.indexOf('843-900-1976') === -1) {
        msg += ' If it keeps happening, call us at 843-900-1976.';
      }
      setState('error', 'Could not generate brief');
      actionToolbar.style.display = 'none';
      briefContent.innerHTML = '';
      const errBox = document.createElement('div');
      errBox.className = 'brief-placeholder';
      errBox.textContent = msg;
      briefContent.appendChild(errBox);
      showToast('Brief could not be generated.');
    }
  });

  // High-fidelity fallback client streaming simulator for testing/offline previews
  function simulateClientGeneration(payload) {
    setState('generating');
    rawMarkdownBuffer = '';
    const bName = payload.businessName || 'Your Business';
    
    const sampleBriefChunks = [
      `# AI Project Brief: ${bName}\n`,
      `*Generated for ${payload.email} by Grand Strand Digital*\n\n`,
      `---\n\n`,
      `## 1. Project Summary\n`,
      `**Business Profile**: ${bName} is seeking to eliminate operational friction and accelerate digital growth along the Grand Strand.\n\n`,
      `**Core Opportunity**: Based on your requirements:\n> "${payload.goals.slice(0, 200)}..."\n\n`,
      `The immediate objective is to replace manual communication bottlenecks with an intelligent, 24/7 AI-driven workflow that captures and qualifies prospective clients before follow-up.\n\n`,
      `## 2. Objectives & Proposed Success Measures\n`,
      `- **Inquiry Response Speed**: Reduce first-response time from hours to under 30 seconds, 24/7/365.\n`,
      `- **Qualified Lead Velocity**: Increase strategy inquiries and appointment completions by 25–40% within 90 days.\n`,
      `- **Staff Capacity Savings**: Automate 70%+ of repetitive incoming questions, returning 10+ hours per week to operational staff.\n\n`,
      `## 3. Recommended Scope\n`,
      `### Must-Have Phase 1 Work\n`,
      `- **Custom AI Conversational Agent**: Tailored agent grounded in your business FAQs, services, and booking flow.\n`,
      `- **Automated Lead Intake & Routing**: Webhook and notification pipeline sending instant SMS/Email alerts for priority leads.\n`,
      `- **Safe Human Escalation**: Built-in rules that immediately hand off complex inquiries to your staff.\n\n`,
      `### Optional Follow-On Phase 2 Work\n`,
      `- **CRM & Calendar Synchronization**: Direct two-way integration with your existing CRM and scheduling tools.\n`,
      `- **Local SEO & Answer Engine Optimization**: AI-assisted landing pages targeting Myrtle Beach & Carolinas searches.\n`,
      `- **Multi-Channel SMS Deployment**: Extending your conversational agent to two-way SMS messaging.\n\n`,
      `### Explicit Exclusions\n`,
      `- Complete website redesign or database migration.\n`,
      `- Autonomous financial commitments or legally binding quotes without staff review.\n\n`,
      `## 4. Recommended Grand Strand Digital Services\n`,
      `1. **Custom AI Agent Development**: Build and calibrate a specialized agent that knows your business rules and serves customers round the clock.\n`,
      `2. **Workflow Automation**: Build automated pipelines connecting incoming visitor inquiries to staff notification channels.\n`,
      `3. **AI Strategy Consulting**: A 45-minute readiness audit to map workflows, verify integration hooks, and ensure data privacy.\n\n`,
      `## 5. Phased Implementation Roadmap\n`,
      `- **Phase 1: Discovery & Architecture Audit (Weeks 1–2)**: Map customer touchpoints, compile knowledge assets, and set safety boundaries.\n`,
      `- **Phase 2: Build & System Integration (Weeks 3–5)**: Agent engineering, prompt grounding, webhook setup, and brand styling.\n`,
      `- **Phase 3: Testing, Enablement & Launch (Weeks 6–7)**: End-to-end team scenario tests, staff training, and live public deployment.\n\n`,
      `## 6. Key Discovery Questions\n`,
      `1. What channels (website, phone, email, social) currently receive the highest volume of inquiries?\n`,
      `2. What specific calendar or CRM software does your team currently rely on?\n`,
      `3. Who on your staff should receive instant notifications for high-priority leads?\n`,
      `4. What is your preferred target launch date for having this live?\n\n`,
      `## 7. Next Step\n`,
      `Ready to discuss this brief and get an exact implementation plan?\n\n`,
      `👉 **[Book Your Free 30-Minute Strategy Call](contact.html)** or call us directly at **843-900-1976**.\n`
    ];

    let i = 0;
    const interval = setInterval(() => {
      if (!isGenerating) {
        clearInterval(interval);
        return;
      }
      if (i < sampleBriefChunks.length) {
        rawMarkdownBuffer += sampleBriefChunks[i];
        renderMarkdown(rawMarkdownBuffer);
        i++;
      } else {
        clearInterval(interval);
        setState('complete');
        showToast('Project brief successfully generated!');
      }
    }, 120);
  }

  // Stream-Safe Markdown Renderer
  function renderMarkdown(md) {
    if (!briefContent) return;

    // Basic HTML escaping
    let html = md
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

    // Headings
    html = html.replace(/^### (.*$)/gim, '<h4>$1</h4>');
    html = html.replace(/^## (.*$)/gim, '<h3 class="brief-heading">$1</h3>');
    html = html.replace(/^# (.*$)/gim, '<h2 class="brief-main-title">$1</h2>');

    // Horizontal Rule
    html = html.replace(/^---$/gim, '<hr class="brief-divider">');

    // Bold & Italics
    html = html.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/gim, '<em>$1</em>');

    // Blockquotes
    html = html.replace(/^\&gt; (.*$)/gim, '<blockquote class="brief-quote">$1</blockquote>');

    // Unordered Lists
    html = html.replace(/^\s*-\s+(.*$)/gim, '<li class="brief-li">$1</li>');
    html = html.replace(/(<li class="brief-li">[\s\S]*?<\/li>)/gim, '<ul class="brief-ul">$1</ul>');
    html = html.replace(/<\/ul>\s*<ul class="brief-ul">/gim, '');

    // Ordered Lists
    html = html.replace(/^\s*\d+\.\s+(.*$)/gim, '<li class="brief-oli">$1</li>');
    html = html.replace(/(<li class="brief-oli">[\s\S]*?<\/li>)/gim, '<ol class="brief-ol">$1</ol>');
    html = html.replace(/<\/ol>\s*<ol class="brief-ol">/gim, '');

    // Links [text](url)
    // Only allow safe link targets (blocks javascript:, data:, etc.)
    html = html.replace(/\[([^\]]+)\]\(([^)\s]+)\)/gim, (m, text, url) => {
      const decoded = url.replace(/&amp;/g, '&');
      const safe = /^(https?:|mailto:|tel:)/i.test(decoded) || /^(\/(?!\/)|#|[a-z0-9][\w\-./]*\.html(#[\w-]*)?$)/i.test(decoded);
      if (!safe) return text;
      const external = /^https?:/i.test(decoded);
      return '<a href="' + url + '"' + (external ? ' target="_blank" rel="noopener noreferrer"' : '') + ' class="brief-link">' + text + '</a>';
    });

    // Paragraph line breaks
    html = html.replace(/\n\n/gim, '</p><p>');

    briefContent.innerHTML = '<div class="brief-prose"><p>' + html + '</p></div>';
  }

})();
