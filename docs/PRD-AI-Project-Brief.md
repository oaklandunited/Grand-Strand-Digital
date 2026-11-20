---
title: Grand Strand Digital - AI Project Brief Tool PRD
version: 1.0
date: 2026-11-19
owner: Grand Strand Digital
status: Approved for implementation
tags: [grand-strand-digital, website, ai-project-brief, prd]
---

# Grand Strand Digital
## AI Project Brief Tool — Product Requirements Document
Version 1.0 | November 19, 2026 | Owner: Grand Strand Digital

---

## 1. Executive Summary

Add a dedicated, interactive **AI Project Brief Tool** to `grandstranddigital.com` (`/project-brief.html`). A prospective client enters their business challenges, goals, and contact information, and instantly receives a practical, structured, editable planning brief grounded strictly in Grand Strand Digital's actual services.

The tool demonstrates Grand Strand Digital's AI capabilities in real time, qualifies inbound inquiries with actionable project context, and makes initial discovery and strategy calls far more productive.

### Core Experience
- **Intake**: Visitor email, optional business name, project goals/challenges, and explicit inquiry consent.
- **Generation**: Real-time streaming output (Server-Sent Events) generating a 7-section structured brief.
- **Visitor Actions**: Stop mid-stream, revise inputs, copy formatted Markdown, download `.md` file, or immediately proceed to book a Strategy Call.
- **Handoff**: Directly connects the generated brief to Grand Strand Digital's strategy consultation booking (`contact.html`) and phone (`843-900-1976`).

---

## 2. Goals and Non-Goals

### P0 Goals
1. **Interactive Capability Proof**: Turn vague business operational and web problems into an actionable project brief, proving AI capability without requiring open-ended, hallucination-prone chatbots.
2. **Lead Qualification**: Capture validated prospect contact information and project goals before generation.
3. **Productive Discovery**: Provide both the client and Grand Strand Digital's team with an intake summary before the first strategy call.
4. **Service Grounding**: Recommend exclusively services that Grand Strand Digital actually delivers (AI agents, workflow automation, AI strategy, web development, SEO, and systems integration).
5. **Fast Response**: Provide immediate value (streamed response beginning in < 3 seconds) 24/7.

### Non-Goals (Out of Scope for v1)
- Full website redesign or CMS overhaul.
- Automated contract pricing quotes or legally binding cost estimates.
- Replacement of human strategy calls.
- User accounts, client portals, or authentication gates.
- Database retention of visitor trade secrets or unencrypted goal text.
- Autonomous outbound sales calls.

---

## 3. Audience and Brand Positioning

### Target Audience
- **Local Grand Strand Business Owners & Operators**: Restaurants, resorts, vacation rental managers, healthcare practices, real estate brokerages, contractors, and retail shops across Myrtle Beach, Conway, Pawleys Island, North Myrtle Beach, and the coastal Carolinas.
- **Operations & Marketing Leaders**: Mid-sized businesses seeking to eliminate manual processes, improve customer response times, and scale operations.

### Brand Voice
- Practical, consultative, clear, and local.
- Grounded in real-world business ROI; honest about when AI is needed and when simple automation or good web engineering suffices.
- Direct, friendly Grand Strand presence with national delivery capability.

### Canonical Contact Details
- **Website**: `https://grandstranddigital.com`
- **Phone**: `843-900-1976`
- **Email**: `hello@grandstranddigital.com`
- **Primary Next Step**: Book a Strategy Call via `/contact.html`

---

## 4. Feature Requirements & User Journey

### Visitor Journey
1. **Entry**: Visitor clicks "AI Project Brief" in header/footer or hero CTA on `index.html`, landing on `/project-brief.html`.
2. **Intake**: Visitor enters:
   - Work email (required, max 254 chars)
   - Business / Company name (optional, max 160 chars)
   - Project goals / current challenges (required, 20–6,000 chars)
   - Consent checkbox (required boolean true)
   - Honeypot check (hidden field, must remain empty)
3. **Starter Presets**: Visitor can click one of three starter templates to auto-fill common Grand Strand scenarios:
   - *Hospitality & Tourism*: Guest inquiry response & booking automation
   - *Contractor & Home Services*: After-hours intake & job quote qualification
   - *Professional & Health Services*: Repetitive intake reduction & CRM scheduling
4. **Validation & Streaming**:
   - Client validates fields; server re-validates.
   - Server streams generated brief via Server-Sent Events (SSE).
   - Real-time progress animation displays before the first chunk arrives.
5. **Interaction & Export**:
   - Visitor can Stop generation at any time.
   - Visitor can Copy Markdown to clipboard.
   - Visitor can Download `project-brief.md` file.
   - Visitor can click "Revise" to edit inputs or "Start Over".
6. **Handoff**:
   - Clear call to action: "Ready to discuss this brief? Book Your Free Strategy Call" linking to `/contact.html` with pre-filled context, or call `843-900-1976`.

---

## 5. Generated Brief Structure (7 Sections)

The brief generation must adhere to concise Markdown (target 550–850 words) with these seven grounded sections:

1. **Project Summary**: Business context, core pain points, and target outcome.
2. **Objectives & Proposed Success Measures**: Concrete KPIs (e.g. response time reduction, lead conversion lift) labeled explicitly as proposed targets.
3. **Recommended Scope**:
   - *Must-Have Scope (Phase 1)*: Core implementation items.
   - *Optional Follow-On (Phase 2)*: Advanced optimizations.
   - *Explicit Exclusions*: What is not included in the initial sprint.
4. **Recommended Grand Strand Digital Services**:
   Select 2–4 services from Grand Strand Digital's catalog:
   - *Custom AI Agent Development*
   - *Workflow Automation*
   - *AI Strategy Consulting*
   - *AI-Powered Web Design & Development*
   - *SEO & Content Strategy*
   - *System Integration*
5. **Phased Implementation Roadmap**: Discovery & Architecture Audit, Build & System Integration, Testing & Enablement Launch.
6. **Discovery Questions**: 4–6 specific diagnostic questions to explore during the strategy call.
7. **Next Step**: Actionable prompt to book the 30-minute strategy call via `/contact.html` or call `843-900-1976`.

---

## 6. Architecture & Technical Contract

### Frontend
- File: `project-brief.html`
- Uses shared CSS (`styles.css`), brand fonts (Montserrat & Lato), and brand color palette (`--accent: #e8352c`, `--gold: #f5a623`, `--tertiary: #1a5c7a`).
- Dedicated vanilla JavaScript client: `project-brief.js`.
- Custom stream-safe Markdown renderer (sanitizes text, converts headings, lists, bold, italics, quotes; blocks raw HTML, scripts, images).

### Backend Endpoint: `POST /api/project-brief.php`
- Accepts `application/json` or form data: `{ email, businessName, goals, consent, honeypot }`.
- Validates parameters, checks honeypot, applies IP rate limiter.
- Calls LLM endpoint (OpenAI `gpt-4o-mini` / compatible provider) with system instructions grounded in Grand Strand Digital's services.
- Sends Server-Sent Events headers:
  ```http
  Content-Type: text/event-stream
  Cache-Control: no-cache
  Connection: keep-alive
  ```
- Emits events:
  - `delta`: `{ "text": "..." }`
  - `done`: `{ "ok": true }`
  - `error`: `{ "code": "...", "message": "..." }`
- Asynchronous notification: sends lead notification email to `hello@grandstranddigital.com` with prospect email, company name, timestamp, and request ID.

---

## 7. Security, Privacy, and Abuse Controls

1. **Bot Protection**: Hidden honeypot field; support for Cloudflare Turnstile verification.
2. **Rate Limiting**: Maximum 6 generation attempts per IP per hour.
3. **Input Sanitization**: Request body capped at 16 KB; goals trimmed between 20 and 6,000 characters.
4. **Data Privacy**: No storage of visitor goals or brief text in database; transient processing only. Clear privacy notice displayed above the consent checkbox.
5. **Prompt Injection Defense**: Goals are treated strictly as user data in the prompt template, never as system instructions. System prompt enforces strict boundaries preventing disclosure of prompt instructions, pricing guarantees, or fabricated case studies.

---

## 8. Acceptance Criteria & Release Gates

- **AT-01**: `/project-brief.html` renders with correct brand styles, responsive layout (360px–1440px), and working navigation links.
- **AT-02**: All 7 required sections stream properly and contain Grand Strand Digital services, Myrtle Beach context, and phone `843-900-1976`. No Capitol CoDesign, Sacramento, or Web Palm Beaches references appear.
- **AT-03**: Form validation blocks empty inputs, invalid emails, unchecked consent, and triggers honeypot rejection without making LLM API calls.
- **AT-04**: Copy to clipboard, Download Markdown (`project-brief.md`), Stop Generation, and Revise buttons operate smoothly.
- **AT-05**: Error states (network timeout, rate limit, provider outage) display user-friendly fallback messaging offering direct phone booking at `843-900-1976`.
- **AT-06**: Navigation and footer links on all existing site pages (`index.html`, `services.html`, `solutions.html`, `process.html`, `clients.html`, `contact.html`) link to the new brief tool.
