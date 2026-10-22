# Deploying Wellness1

Static site. Nginx serves it; there is no backend. The order form POSTs
directly from the browser to a Google Apps Script web app, and the visitor IP
comes from ipify, so nothing server-side has to run.

## Option A — Coolify (recommended)

No SSH needed; everything happens in the Coolify dashboard.

1. **Projects → New → Resource → Public/Private Repository.**
2. Point it at `rajeshsharma88/wellness1`. The repo is private, so authorise
   Coolify's GitHub App (Settings → Sources) or add a deploy key first.
3. Build Pack: **Dockerfile**. Branch: `main`. Port: `80` (Coolify reads
   `EXPOSE 80`).
4. Set the domain under **Domains**, e.g. `https://example.com`. Coolify's
   proxy requests the Let's Encrypt certificate automatically once DNS
   resolves to the server.
5. **Deploy.** Enable auto-deploy so `git push` ships to production.

## Option B — the srv1982925 VPS (201.18.193.164)

This host already runs n8n, Postgres, Redis and Ollama behind Traefik v3, which
owns ports 80 and 443 in host network mode. Do **not** install another reverse
proxy or publish port 80 here; it will collide. `docker-compose.yml` carries
Traefik labels instead, matching the pattern the n8n service uses, so the
existing `letsencrypt` resolver issues the certificate.

Deployed at `/docker/wellness1`, alongside the other compose projects:

```bash
cd /docker/wellness1
docker compose up -d --build
```

To update, sync the files up and rebuild:

```bash
rsync -avz --delete --exclude '.git' --exclude '.DS_Store' \
  ./ root@201.18.193.164:/docker/wellness1/
ssh root@201.18.193.164 'cd /docker/wellness1 && docker compose up -d --build'
```

Requires an `A` record for `wellness` → `201.18.193.164` on `aarogya.cloud`
(Hostinger hPanel; the zone uses dns-parking.com nameservers). Traefik's HTTP
challenge cannot issue a certificate until that resolves.

## Before going live

- Three images referenced by `index.html` are missing from the repo and will
  render broken: `assets/call.webp`, `assets/what.webp` and
  `assets/images/call-gif.gif`. Source them from the original site.
- Submit a real test order and confirm the row lands in the Google Sheet. The
  form uses `mode: "no-cors"`, so the browser reports success and redirects to
  `thankyou.html` even if Apps Script rejected the request.
- Do not deploy over FTP or a web file manager. `npm/bootstrap%405.3.0-alpha1`
  has a literal `%` in its directory name and many clients rewrite it, which
  breaks every Bootstrap style on the page. Git and rsync copy it intact.
