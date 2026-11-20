# SiteGround CI/CD Deployment Guide

This repository includes a GitHub Actions workflow (`.github/workflows/deploy.yml`) that automatically synchronizes code to your SiteGround web server upon every push to `main`.

---

## 1. Required GitHub Repository Secrets

Navigate to your GitHub repository:
**Settings** → **Secrets and variables** → **Actions** → **New repository secret**.

Add the following secrets:

| Secret Name | Value Description | Example / Default |
|---|---|---|
| `SITEGROUND_HOST` | SiteGround server IP or domain | `grandstranddigital.com` or server IP |
| `SITEGROUND_USERNAME` | SSH username created in SiteGround Site Tools | `uXXX-yyyyy` |
| `SITEGROUND_SSH_KEY` | Private SSH key (full text with headers) | `-----BEGIN RSA PRIVATE KEY----- ... -----END RSA PRIVATE KEY-----` |
| `SITEGROUND_SSH_PASSPHRASE` | Passphrase for the SSH key | |
| `SITEGROUND_KNOWN_HOSTS` | *(Recommended)* Pinned server host key — see section 4 | output of `ssh-keyscan` |
| `SITEGROUND_PORT` | *(Optional)* SSH port | `18765` (SiteGround standard) |
| `SITEGROUND_TARGET_PATH` | *(Optional)* Remote directory path | `/home/customer/www/grandstranddigital.com/public_html/` |

---

## 2. Generating the SSH Key in SiteGround

1. Log in to your **SiteGround Client Area** and navigate to **Site Tools** for `grandstranddigital.com`.
2. Go to **Devs** → **SSH Keys Manager**.
3. Under **Create New SSH Key**:
   - **Key Name**: `github-actions-deploy`
   - **Password**: Generate or create a strong passphrase.
   - Click **Create**.
4. Once created, look under **Manage SSH Keys**:
   - Click the **three vertical dots (Actions)** next to your key → choose **Private Key**.
   - Copy the entire private key text.
   - Paste this as the value for `SITEGROUND_SSH_KEY` in GitHub Secrets.
5. In the same **Actions** menu, select **SSH Credentials** to view your exact **Hostname/IP**, **Port** (`18765`), and **Username**.

> [!NOTE]
> If your SiteGround plan requires IP whitelisting for SSH, you can leave SSH access open or grant access from the Site Tools SSH Keys Manager.

---

## 3. How the Workflow Operates

- **Automatic Trigger**: Any commit pushed to the `main` branch automatically triggers the sync.
- **Manual Trigger**: You can also manually trigger a deployment at any time from GitHub by going to the **Actions** tab → **Deploy to SiteGround** → **Run workflow**.
- **Excluded Files** (never uploaded, never deleted on the server): `/.git/`, `/.github/`, `/docs/`, `/api/config.php` (your live credentials), `/svg-logo-mockup-lab.html`, log files, `/.well-known/`, `/cgi-bin/`, `.user.ini`.
- **Prune (manual only)**: *Run workflow* has a **prune** checkbox. When ticked, rsync runs with `--delete` and removes every server file in `public_html` that is not in the repo (old WordPress files, `wp-content`, etc.). **Download a backup of `public_html` (Site Tools → Security → Backups) before using it.** Normal pushes never delete anything.

---

## 4. Pin the server's SSH host key (recommended)

Without a pinned key the workflow trusts whatever host answers on first connect. From your own computer run:

```bash
ssh-keyscan -p 18765 YOUR_SITEGROUND_HOST
```

Check the fingerprint matches SiteGround's (Site Tools → Devs → SSH Keys Manager), then paste the output into the `SITEGROUND_KNOWN_HOSTS` secret.

---

## 5. Server-side configuration (`api/config.php`)

Copy `api/config.example.php` to `api/config.php` on the server (File Manager or SSH) and fill in:

- `api_key` — OpenAI key. Also set a **monthly spend limit** in the OpenAI dashboard (Settings → Limits).
- `turnstile_secret_key` and **`'require_turnstile' => true`** — makes the API refuse requests if the key is ever missing.
- `notification_email` / `notification_from` — where leads are emailed. `notification_from` should be a mailbox on the domain so SPF/DKIM pass.
- Contact-form leads are also appended to `gsd-leads.jsonl` one directory **above** `public_html` (override with `lead_log_path`), so a failed email never loses a lead.

---

## 6. Retiring the old WordPress install

The root `.htaccess` already blocks `wp-admin`, `wp-login.php`, `xmlrpc.php`, `wp-content`, etc. To remove the files entirely:

1. Take a full backup in Site Tools.
2. Run the workflow manually with **prune** ticked (or delete the `wp-*` files, `xmlrpc.php`, `index.php`, `license.txt`, `readme.html` and `php_errorlog` in File Manager).
3. Delete the WordPress database and its DB user in Site Tools → Site → MySQL.
4. Change the old WordPress admin password anywhere it was reused.
