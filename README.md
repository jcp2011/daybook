# Daybook

A self-contained PHP application for managing dated instructions. Supports plain text and rich text entries, archiving, inline editing, and sorting.

## Requirements

- PHP 8.1 or later with the `pdo_sqlite` extension enabled
- A writable `data/` directory (created automatically on first run)

No framework, no Composer, no build step required.

## Setup

```bash
# Clone the repository
git clone https://github.com/jcp2011/daybook.git
cd daybook

# Serve with the built-in PHP server
php -S localhost:8080
```

Open `http://localhost:8080` in a browser.

The SQLite database is created automatically at `data/instructions.db` on the first request.

## Features

- Add instructions with a date/time and a plain or rich text description
- Rich text editor (Quill.js, fully local — no CDN) with:
  - Bold, italic, strikethrough
  - Text colour, background colour, font size
  - Ordered and unordered lists
  - Hyperlinks (http, https, mailto — unsafe schemes are stripped on save)
  - Full Unicode emoji picker (emoji-picker-element, fully local — no CDN) with search and categories
- Edit active instructions inline (archived instructions are read-only)
- Archive and restore instructions (archived entries record the archival date/time)
- Delete instructions permanently
- Sort by date ascending or descending (click the Date column header)
- Timestamps (archived date, default date input) use the server's local timezone, detected automatically from the OS
- Custom logo: place `assets/logo.png` to display it in the header

## Project Structure

```
.
+-- docker/                          # Docker image and Apache configuration
|   +-- Dockerfile                   #   Runtime image (Apache + PHP + mod_auth_gssapi)
|   +-- apache.conf                  #   VirtualHost with Kerberos GSSAPI + security headers
|   +-- security.conf                #   Global: ServerTokens Prod, ServerSignature Off
|   +-- php-security.ini             #   PHP hardening: expose_php=Off, session settings
+-- public/                          # Apache DocumentRoot (web-accessible files only)
|   +-- api/
|   |   +-- rows.php                 #   JSON/HTML endpoint for the auto-refresh polling
|   +-- assets/
|   |   +-- emoji-picker/            #   emoji-picker-element web component (local copy)
|   |   |   +-- picker.js            #     web component implementation
|   |   |   +-- database.js          #     IndexedDB cache layer (patched for plain HTTP)
|   |   |   +-- emoji-picker-element.js  # entry-point re-export
|   |   |   +-- en/emojibase/data.json   # emoji dataset - English
|   |   |   +-- fr/emojibase/data.json   # emoji dataset - French
|   |   |   +-- i18n/fr.js           #     UI translations - French
|   |   +-- fonts/                   #   Self-hosted web fonts
|   |   |   +-- NotoColorEmoji.0.woff2   # Noto Color Emoji - unicode subsets 0-9
|   |   |   +-- ...
|   |   +-- app.css                  #   Application stylesheet
|   |   +-- quill.js                 #   Quill 1.3.7 (local copy)
|   |   +-- quill.snow.css           #   Quill Snow theme (local copy)
|   +-- index.php                    #   Entry point and UI
+-- src/                             # PHP source classes (not web-accessible)
|   +-- Auth/
|   |   +-- Authenticator.php        #   Kerberos SSO trust + LDAPS bind + group check
|   +-- Exception/
|   |   +-- AuthenticationException.php  # LDAP connection/config failures
|   |   +-- AuthorizationException.php   # Authenticated but not in required AD group
|   +-- Env.php                      #   Minimal .env file loader
|   +-- functions.php                #   Database and HTML utility functions
+-- templates/                       # PHP templates (not web-accessible)
|   +-- login.php                    #   Standalone login form (no external asset deps)
+-- tests/
|   +-- Unit/
|   |   +-- AuthenticatorTest.php
|   |   +-- EnvTest.php
|   |   +-- FunctionsTest.php
|   +-- bootstrap.php
+-- tools/
|   +-- download-emoji-picker.sh     # Download/update emoji-picker-element assets
|   +-- download-fonts.sh            # Download/update Noto Color Emoji font
|   +-- php-cs-fixer.phar
|   +-- phpstan.phar
|   +-- phpunit.phar
|   +-- SHA256SUMS
+-- data/                            # SQLite database (git-ignored)
+-- .dockerignore
+-- .env.example                     # Template for .env (copy and fill in values)
+-- .php-cs-fixer.php
+-- CHANGELOG.md
+-- docker-compose.yml
+-- docker-stack.yml
+-- phpstan.neon
+-- phpunit.xml
```

## Development

All tooling runs from local PHARs in `tools/` — no global installation needed.

### Code style

```bash
php tools/php-cs-fixer.phar fix --config=.php-cs-fixer.php
```

### Static analysis

```bash
php tools/phpstan.phar analyse --memory-limit=512M
```

### Tests

```bash
php tools/phpunit.phar
```

## Updating the emoji picker

Run the download script from a machine with internet access:

```bash
bash tools/download-emoji-picker.sh
```

The script uses only `curl`, `tar`, and `python3` — no npm or Node.js required. It:

1. Fetches the latest versions of `emoji-picker-element` and `emoji-picker-element-data` from the npm registry
2. Extracts only the files needed (`picker.js`, `database.js`, `index.js`, data and i18n files)
3. Automatically patches `database.js` with a fallback hash so the picker works on plain HTTP (non-localhost IP addresses where `crypto.subtle` is unavailable)

Commit the updated `assets/emoji-picker/` afterwards to keep the repository deployable on air-gapped machines.

To add or remove languages, edit the `LANGUAGES` variable at the top of the script.

### Note on the database.js patch

`database.js` ships from npm without a `crypto.subtle` fallback. The download script patches `jsonChecksum()` automatically. If after an update you see "Could not load emoji" and `TypeError: Cannot read properties of undefined (reading 'digest')` in the browser console, the patch did not apply cleanly (upstream changed the function). Re-apply it manually: add a guard `if (typeof crypto !== 'undefined' && crypto.subtle)` around the `crypto.subtle.digest()` call and add a djb2 integer hash as the else branch.

## Updating the Noto Color Emoji font

Emoji rendering varies significantly across operating systems — Windows in
particular displays emoji quite differently from macOS or Linux. To ensure a
consistent appearance everywhere, the application uses the
[Noto Color Emoji](https://fonts.google.com/noto/specimen/Noto+Color+Emoji)
font, self-hosted under `assets/fonts/`.

The font is split into 10 unicode-range subsets (totalling ~2 MB). The browser
only downloads the subset(s) it actually needs for the emoji characters present
on the page.

Run the download script from a machine with internet access:

```bash
bash tools/download-fonts.sh
```

The script uses only `curl` — no npm or Node.js required. It fetches the
current woff2 subsets directly from Google Fonts and saves them to
`assets/fonts/`. Commit the updated files afterwards to keep the repository
deployable on air-gapped machines.

## Logo

Place a file named `logo.png` inside `assets/` to display your logo in the top-right corner of the header. The file is git-ignored so it stays local to each deployment.

## Deployment with Docker

The Docker image bundles Apache, `mod_auth_gssapi`, PHP 8.3, and all required
extensions. App files are mounted as a read-only volume at runtime — no COPY of
application code is baked into the image. Updating the application is a `git pull`
on the host; no image rebuild required.

### Build and transfer to an air-gapped machine

```bash
# --- Internet-connected build machine ---
cd docker && docker build -t daybook:1.0 .
docker save daybook:1.0 | gzip > daybook-1.0.tar.gz
sha256sum daybook-1.0.tar.gz > daybook-1.0.tar.gz.sha256
# Copy both files to USB drive.

# --- Air-gapped target machine ---
sha256sum -c daybook-1.0.tar.gz.sha256          # verify integrity before loading
docker load < /media/usb/daybook-1.0.tar.gz
git clone <repo-on-usb> daybook && cd daybook   # or: git pull
cp .env.example .env                            # fill in LDAP values (see Authentication below)
# Copy daybook.keytab from the AD administrator to ./daybook.keytab
docker-compose up -d
```

### Updating

```bash
git pull
docker-compose restart   # picks up the updated app files from the volume mount
```

### Docker Swarm deployment

Use `docker-stack.yml` instead of `docker-compose.yml` for Swarm:

```bash
docker stack deploy -c docker-stack.yml daybook
```

`docker-stack.yml` adds the following Swarm-specific features on top of the
standard deployment:

| Feature | Configuration |
|---|---|
| Capability hardening | `cap_drop: ALL` + minimal `cap_add` (CHOWN, DAC_OVERRIDE, NET_BIND_SERVICE, SETGID, SETUID) |
| CPU limit | 1.0 core (0.25 reserved) |
| Memory limit | 256 MB (128 MB reserved) |
| PID limit | 100 |
| Healthcheck | HTTP probe on `http://localhost/` every 30 s |
| Restart policy | On failure, max 3 attempts, 5 s delay |
| Rolling update | Start new container before stopping old (`start-first`), auto-rollback on failure |
| Rollback | Stop old then start previous (`stop-first`), pause on rollback failure |

Resource limits (`cpus`, `memory`, `pids`) should be tuned to match the
available capacity of the target node. The values above are conservative
defaults suitable for a small internal application.

### Security configuration files

`docker/security.conf` and `docker/php-security.ini` are mounted as read-only
volumes at runtime (see `docker-compose.yml`). They can be edited on the host
and applied with `docker-compose restart` — no image rebuild required.

#### `session.cookie_secure` in `docker/php-security.ini`

This setting controls whether the browser sends the session cookie only over
HTTPS connections.

- **Set to `1` (default)** when Daybook is behind a TLS-terminating reverse
  proxy such as Traefik. TLS is handled by the proxy; PHP itself receives plain
  HTTP from it. The `Secure` flag is still correct because the
  browser-to-proxy leg is HTTPS, and you want the browser to refuse to send
  the session cookie over a plain HTTP connection.

- **Set to `0`** only when running without any TLS at all (isolated development
  environment, no proxy). In this case the `Secure` flag would prevent the
  browser from sending the cookie entirely, breaking the session.

To apply security patches to the image itself (OS packages, PHP), rebuild and reload:

```bash
cd docker && docker build -t daybook:1.1 .
docker save daybook:1.1 | gzip > daybook-1.1.tar.gz
# Transfer to target, then:
docker load < /media/usb/daybook-1.1.tar.gz
# Edit docker-compose.yml: change image: daybook:1.1
docker-compose up -d
```

## Authentication

Authentication is controlled by the `AUTH_ENABLED` key in `.env`.

| Value | Behaviour |
|---|---|
| `true` (default) | Full Kerberos SSO + LDAPS fallback enforced. |
| `false` | Authentication disabled. All requests are treated as local. Use only for single-user or development deployments. |

### Authentication flow

1. **Kerberos SSO (on LAN with a Kerberos ticket):** Apache performs SPNEGO
   negotiation. On success, it sets `REMOTE_USER` to the plain username (the
   `@REALM` suffix is stripped by `GssapiLocalName On`). PHP verifies that the
   user belongs to `LDAP_REQUIRED_GROUP` via LDAPS before granting access.

2. **LDAPS form login (no Kerberos ticket):** When SPNEGO negotiation fails
   (VPN, non-domain client), the request passes through and PHP displays a
   username/password form. Credentials are validated against LDAPS, and group
   membership is verified before a session is created.

Group membership is always verified on both paths using the
`LDAP_MATCHING_RULE_IN_CHAIN` OID (`1.2.840.113556.1.4.1941`), which resolves
nested AD group membership recursively.

### Environment variables

Copy `.env.example` to `.env` and fill in the values:

```ini
DAYBOOK_FQDN=daybook.company.com  # Single source of truth for the hostname

AUTH_ENABLED=true

LDAP_HOST=dc.company.com       # Domain Controller FQDN or IP
LDAP_PORT=636                  # LDAPS port (636); never use plain LDAP (389)
LDAP_DOMAIN=company.com        # AD domain, used to build user@domain bind DNs
LDAP_BASE_DN=DC=company,DC=com # Search base for group membership queries

# Service account used to search AD for group membership.
# Use a dedicated account with no other privileges.
LDAP_SERVICE_DN=CN=svc-daybook,OU=ServiceAccounts,DC=company,DC=com
LDAP_SERVICE_PASSWORD=change-me

# Full DN of the AD group whose members may access Daybook.
# Nested membership is resolved automatically.
LDAP_REQUIRED_GROUP=CN=Daybook-Users,OU=Groups,DC=company,DC=com
```

`DAYBOOK_FQDN` is the single value that must match across three places:

| Where | Value |
|---|---|
| `DAYBOOK_FQDN` in `.env` | `daybook.company.com` |
| `ktpass /princ` (keytab generation) | `HTTP/daybook.company.com@COMPANY.COM` |
| Docker `hostname:` in compose files | resolved from `${DAYBOOK_FQDN}` automatically |

Docker Compose reads `DAYBOOK_FQDN` from `.env` and passes it into the container via `environment:`. Apache 2.4 resolves `${DAYBOOK_FQDN}` natively in `apache.conf` at startup. No image rebuild is required when the FQDN changes - update `.env` and restart the container.

### Generating the Kerberos keytab on the Domain Controller

Run the following on the DC as a Domain Administrator. The service account
(`svc-daybook`) must exist in AD before running this command.

```bat
ktpass /princ HTTP/daybook.company.com@COMPANY.COM ^
       /mapuser svc-daybook@COMPANY.COM ^
       /crypto AES256-SHA1 ^
       /ptype KRB5_NT_PRINCIPAL ^
       /pass <ServiceAccountPassword> ^
       /kvno 0 ^
       /out daybook.keytab
```

**Parameter reference:**

| Parameter | Value | Notes |
|---|---|---|
| `/princ` | `HTTP/FQDN@REALM` | `HTTP` must be uppercase. FQDN must match the hostname the browser uses, `ServerName` in `apache.conf`, and `GssapiAcceptorName` in `apache.conf`. The container hostname no longer needs to match thanks to `GssapiAcceptorName`. |
| `/mapuser` | `svc-daybook@COMPANY.COM` | Attaches the SPN to this account. |
| `/crypto` | `AES256-SHA1` | **Recommended.** Requires "This account supports Kerberos AES 256 bit encryption" checked on the service account in AD. |
| | `AES128-SHA1` | Fallback when some clients cannot negotiate AES256. |
| | `RC4-HMAC` | **Forbidden.** RC4 is cryptographically broken (CVE-2022-37966 and earlier). Do not use. |
| `/ptype` | `KRB5_NT_PRINCIPAL` | Standard principal type for service accounts. |
| `/kvno` | `0` | Lets AD auto-assign the key version number. Must be incremented each time the service account password changes. Mismatch between keytab kvno and AD kvno causes GSSAPI failure. |
| `/out` | `daybook.keytab` | Output file path on the DC. |

**After generation:**

```bash
# Verify the keytab contents:
klist -kt daybook.keytab

# Transfer to the Docker host via a secure channel (not email, not unencrypted share).
# Place it at ./daybook.keytab in the project root.
# Ownership and permissions (root:www-data 440) are set automatically by the
# container entrypoint on every start - no manual chmod/chown needed.
```

**Keytab rotation:** when the service account password is rotated in AD, re-run
`ktpass` with `/kvno` incremented by 1 (or use `/kvno 0` to auto-assign) and
replace `./daybook.keytab` on the host. Restart the container to reload it.

### AD CA certificate

The LDAPS connection to the Domain Controller is verified against the AD root
CA certificate. The certificate must be placed at `./ad-ca.crt` in the project
root before starting the container.

**Obtaining the certificate from the Domain Controller (Windows):**

```bat
:: Run on the DC or any domain-joined machine as a Domain Admin.
:: Exports the root CA certificate in PEM (Base-64) format.
certutil -ca.cert ad-ca.cer
```

Then transfer `ad-ca.cer` to the Docker host and rename it to `ad-ca.crt`.

Alternatively, retrieve it over the network from any Linux machine:

```bash
openssl s_client -connect dc.company.com:636 -showcerts < /dev/null 2>/dev/null \
  | openssl x509 -out ad-ca.crt
```

Place the file at `./ad-ca.crt` in the project root. Ownership and permissions
(`root:www-data 440`) are set automatically by the container entrypoint on every
start - no manual `chmod`/`chown` needed.

The file path inside the container is controlled by `LDAP_CA_CERT` in `.env`
(default: `/run/secrets/ad-ca.crt`, matching the volume mount in
`docker-compose.yml`).

### Browser GPO for silent SSO

Without a Group Policy, Chromium-based browsers (Edge, Chrome) will not send
Kerberos tokens automatically. The user sees a native Windows credential dialog
pre-filled with their domain account - Kerberos is still used, but SSO is not
silent.

To enable fully transparent SSO (no prompt), deploy the following GPO to all
domain-joined client machines:

**Option 1 - Chromium HTTP authentication policy (recommended)**

Applies to Edge and Chrome independently of the Internet Explorer zone model.

| Setting | Path |
|---|---|
| Microsoft Edge | Computer Configuration > Administrative Templates > Microsoft Edge > HTTP Authentication > **Authentication server allowlist** |
| Google Chrome | Computer Configuration > Administrative Templates > Google > Google Chrome > HTTP Authentication > **Authentication server allowlist** |

Set the value to the FQDN of the Daybook server (e.g. `daybook.company.com`).
Multiple entries are comma-separated.

**Option 2 - IE/Edge Intranet Zone assignment**

Adds the site to Windows' "Local Intranet" zone, which triggers automatic
Kerberos negotiation in all browsers that respect the zone model.

| Setting | Path |
|---|---|
| Intranet Zone | Computer Configuration > Administrative Templates > Windows Components > Internet Explorer > Internet Control Panel > Security Page > **Site to Zone Assignment List** |

Add an entry: value `daybook.company.com`, zone `1` (Local Intranet).

Both options achieve the same result. Option 1 is more portable on machines
where the IE zone model is locked down or unavailable.

### GssapiAcceptorName and Docker Swarm

`docker/apache.conf` contains:

```apache
GssapiAcceptorName HTTP/${DAYBOOK_FQDN}
```

This directive explicitly names the SPN that `mod_auth_gssapi` looks up in the
keytab, decoupling it from the container's actual hostname. Without it, Apache
would derive the SPN from the container hostname, which Swarm may set to a
random value.

`${DAYBOOK_FQDN}` is resolved natively by Apache 2.4 from the container
environment, which Docker Compose sets via the `environment:` key. The three
values that must still match each other are:

| Where | Value |
|---|---|
| `DAYBOOK_FQDN` in `.env` | `daybook.company.com` |
| `ktpass /princ` (keytab generation) | `HTTP/daybook.company.com@COMPANY.COM` |
| `ServerName` / `GssapiAcceptorName` in `docker/apache.conf` | resolved from `${DAYBOOK_FQDN}` at startup |

The container hostname is irrelevant and can be left unset.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full history of changes.
