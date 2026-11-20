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
- **Excluded Files**: The workflow automatically excludes non-production assets and sensitive local files:
  - `/.git/` and `/.github/`
  - `/docs/`
  - `/php_errorlog`
  - `/api/config.php` (preserves your live server API credentials without overwriting)
