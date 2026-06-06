# BotCreds Agent Artifacts

A WordPress plugin that lets AI agents (and any REST client) deploy self-contained HTML/CSS/JS applications to a WordPress site with a single API call.

The plugin handles all the messy parts: HTML parsing, asset extraction, proper WordPress enqueueing, and strict security headers. From the agent's perspective, it's one POST request and a public URL comes back.

---

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [API Reference](#api-reference)
- [Use Cases](#use-cases)
  - [OpenClaw](#openclaw)
  - [Claude Code](#claude-code)
  - [Codex](#codex)
  - [GitHub Actions (versioned project)](#github-actions-versioned-project)
- [Security](#security)
- [Developer Reference](#developer-reference)
- [File Structure](#file-structure)
- [Backwards Compatibility](#backwards-compatibility)

---

## Features

### Core

- **Custom post type** — `artifact` with its own archive at `/artifacts/`
- **Single REST API call** — POST HTML, get a public URL back. No setup beyond installation.
- **HTML processing pipeline** — `<script>` and `<style>` tags are extracted from submitted HTML and saved as static files in `wp-content/uploads/artifacts/{id}/`. They are then enqueued via `wp_enqueue_script()` and `wp_enqueue_style()` — no inline scripts in the rendered page.
- **External script support** — `<script src="...">` tags with `src` attributes are registered as external dependencies and enqueued alongside local assets.
- **Head content preservation** — `<head>` meta tags and other head content from the submitted HTML are preserved and injected into the artifact's `<head>`.
- **Asset cache busting** — Enqueued assets are versioned with `filemtime()`, so browsers automatically fetch updated files on redeploy.
- **Clean redeploys** — Old asset files are deleted before new ones are written, preventing stale file accumulation.
- **Artifact description** — Optional `artifact_description` meta field for internal documentation.

### Security

- **Strict Content Security Policy** — Applied to every artifact page:
  - Scripts allowed only from the artifact's upload directory and a curated CDN list
  - Styles: same origins plus `unsafe-inline` (required for many CSS-in-JS patterns)
  - Images: `self`, `data:`, `blob:`, WordPress telemetry domains
  - Fonts: `self`, `data:`, Google Fonts, CDN list
  - `frame-src 'none'` — no iframes inside artifacts
  - `frame-ancestors 'self'` — artifact can't be embedded in external frames
  - `form-action 'self'`
- **Trusted CDN list** — The following CDNs are allowed for scripts, styles, and fonts without any configuration: `cdn.jsdelivr.net`, `unpkg.com`, `cdnjs.cloudflare.com`, `esm.sh`, `cdn.skypack.dev`
- **Per-artifact API allowlist** — Artifacts can declare trusted external APIs for `fetch`/`XHR` via an HTML pragma comment or a deploy-time meta field (see [Per-Artifact API Allowlist](#per-artifact-api-allowlist))
- **Additional security headers** — `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`
- **HTML sanitization** — Body content is run through `wp_kses()` with an expanded allowed-tags list that covers interactive elements (`<canvas>`, `<svg>`, `<input>`, `<button>`, `<select>`, `<video>`, `<audio>`, `data-*` attributes)
- **Custom capability type** — `artifact` capabilities are separate from standard WordPress post capabilities. Only Administrators can create artifacts by default.

### Developer Hooks

- `botcreds_agent_artifacts_csp` — filter the full CSP header string for any artifact
- `botcreds_agent_artifacts_allowed_html` — filter the `wp_kses` allowed-tags array
- `botcreds_agent_artifacts_grant_to_role()` — helper to grant artifact capabilities to additional roles
- Custom template override — drop `single-artifact.php` in your active theme to replace the plugin's render template entirely

---

## Installation

1. Clone or download this repo
2. Upload the `botcreds-agent-artifacts` folder to `/wp-content/plugins/`
3. Activate **BotCreds Agent Artifacts** in the WordPress admin under **Plugins**
4. Go to **Settings → Permalinks** → **Save Changes** (flushes rewrite rules so `/artifacts/` works)
5. Create a WordPress **Application Password** for your agent user: **Users → Profile → Application Passwords**

---

## Quick Start

```bash
curl -X POST "https://your-site.com/wp-json/wp/v2/artifacts" \
  -u "username:your-application-password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Hello World",
    "status": "publish",
    "meta": {
      "artifact_html": "<!DOCTYPE html><html><body><h1>It works.</h1></body></html>"
    }
  }'
```

The response `link` field contains the public URL. Done.

---

## API Reference

### Create an artifact

`POST /wp-json/wp/v2/artifacts`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | yes | Artifact title. Used as the page `<title>`. |
| `status` | string | yes | Set to `"publish"` to make it publicly accessible. |
| `meta.artifact_html` | string | yes | The full HTML to deploy. Scripts and styles are extracted server-side. |
| `meta.artifact_description` | string | no | Optional description for internal documentation. |
| `meta.artifact_connect_src` | array | no | Explicit list of `https://` origins to allow in `connect-src`. See [Per-Artifact API Allowlist](#per-artifact-api-allowlist). |

**Response:** Standard WP REST post object. The `link` field is the public URL.

### Update an artifact

`POST /wp-json/wp/v2/artifacts/{id}`

Same fields as create. Existing asset files are cleared and replaced. The public URL does not change.

### Per-Artifact API Allowlist

Artifacts that need to call external APIs (for live data, weather, stock prices, etc.) must declare those origins so the CSP `connect-src` directive allows the requests. There are two ways to do this:

**Option 1 — HTML pragma (recommended for self-contained artifacts):**

Add a comment anywhere in the submitted HTML:

```html
<!-- artifact:fetch https://api.openweathermap.org https://api.example.com -->
```

The plugin strips the comment from rendered output and adds the origins to `connect-src`. Only `https://` origins are accepted — no wildcards, no `http://`.

**Option 2 — Deploy-time meta field:**

Pass `artifact_connect_src` as an array in the `meta` object:

```json
{
  "meta": {
    "artifact_html": "...",
    "artifact_connect_src": ["https://api.openweathermap.org", "https://api.example.com"]
  }
}
```

Both methods can be used together; origins are merged and deduplicated.

---

## Use Cases

### OpenClaw

OpenClaw is the primary deployment target this plugin was built for. The agent generates HTML and ships it in one tool call.

**Basic deploy from an agent tool call:**

```bash
curl -s -X POST "https://wearebob.blog/wp-json/wp/v2/artifacts" \
  -u "bob:$(cat ~/.openclaw/wordpress_credentials.json | jq -r '.password')" \
  -H "Content-Type: application/json" \
  -d "$(jq -n \
    --arg title "Daily Digest — $(date +%Y-%m-%d)" \
    --rawfile html /tmp/digest.html \
    '{title: $title, status: "publish", meta: {artifact_html: $html}}')" \
  | jq -r '.link'
```

**With an external API (live data artifact):**

Include the fetch pragma in the HTML you generate, and the CSP is automatically updated:

```html
<!-- artifact:fetch https://api.openweathermap.org -->
<!DOCTYPE html>
<html>
<head><title>Weather Dashboard</title></head>
<body>
  <div id="weather"></div>
  <script>
    fetch('https://api.openweathermap.org/data/2.5/weather?q=Denver&appid=YOUR_KEY')
      .then(r => r.json())
      .then(data => {
        document.getElementById('weather').textContent =
          `${data.name}: ${Math.round(data.main.temp - 273.15)}°C`;
      });
  </script>
</body>
</html>
```

**Updating an existing artifact (for recurring reports):**

Store the artifact ID after the first deploy and update it on subsequent runs:

```bash
# First deploy — save the ID
ARTIFACT_ID=$(curl -s -X POST "$WP_SITE/wp-json/wp/v2/artifacts" \
  -u "$WP_USER:$WP_PASS" -H "Content-Type: application/json" \
  -d "$PAYLOAD" | jq -r '.id')

# Subsequent deploys — update in place, URL stays the same
curl -s -X POST "$WP_SITE/wp-json/wp/v2/artifacts/$ARTIFACT_ID" \
  -u "$WP_USER:$WP_PASS" -H "Content-Type: application/json" \
  -d "$PAYLOAD"
```

**Python helper (works inside any Python tool call):**

```python
import urllib.request, json, base64

def deploy_artifact(site, user, password, title, html, description="", artifact_id=None):
    """Deploy or update a WordPress artifact. Returns (id, url)."""
    creds = base64.b64encode(f"{user}:{password}".encode()).decode()
    payload = json.dumps({
        "title": title,
        "status": "publish",
        "meta": {
            "artifact_html": html,
            "artifact_description": description,
        }
    }).encode()

    endpoint = f"{site}/wp-json/wp/v2/artifacts"
    if artifact_id:
        endpoint += f"/{artifact_id}"

    req = urllib.request.Request(
        endpoint,
        data=payload,
        headers={
            "Authorization": f"Basic {creds}",
            "Content-Type": "application/json",
        }
    )
    result = json.loads(urllib.request.urlopen(req).read())
    return result["id"], result["link"]
```

---

### Claude Code

Claude Code runs in your terminal and can invoke shell commands directly. The pattern: generate HTML in the session, write it to a temp file, deploy with curl, get the URL back.

**Add a deploy script to your project:**

Create `scripts/deploy-artifact.sh` in your repo:

```bash
#!/usr/bin/env bash
# Deploy a built HTML file to a WordPress artifact.
# Usage: ./scripts/deploy-artifact.sh <title> <html-file> [artifact-id]

set -euo pipefail

TITLE="${1:?Usage: deploy-artifact.sh <title> <html-file> [artifact-id]}"
HTML_FILE="${2:?}"
ARTIFACT_ID="${3:-}"

WP_SITE="${ARTIFACT_WP_SITE:?Set ARTIFACT_WP_SITE}"
WP_USER="${ARTIFACT_WP_USER:?Set ARTIFACT_WP_USER}"
WP_PASS="${ARTIFACT_WP_PASS:?Set ARTIFACT_WP_PASS}"

PAYLOAD=$(jq -n \
  --arg title   "$TITLE" \
  --rawfile html "$HTML_FILE" \
  '{title: $title, status: "publish", meta: {artifact_html: $html}}')

ENDPOINT="$WP_SITE/wp-json/wp/v2/artifacts"
[ -n "$ARTIFACT_ID" ] && ENDPOINT="$ENDPOINT/$ARTIFACT_ID"

RESPONSE=$(curl -s -X POST "$ENDPOINT" \
  -u "$WP_USER:$WP_PASS" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")

echo "ID:  $(echo "$RESPONSE" | jq -r '.id')"
echo "URL: $(echo "$RESPONSE" | jq -r '.link')"
```

**Add to your CLAUDE.md or project README so Claude Code knows how to deploy:**

```markdown
## Deploying artifacts

To deploy an HTML file as a live artifact:

```bash
export ARTIFACT_WP_SITE="https://your-site.com"
export ARTIFACT_WP_USER="your-username"
export ARTIFACT_WP_PASS="your-app-password"
./scripts/deploy-artifact.sh "My App Title" path/to/output.html
```

To update an existing artifact (keeps the same URL):
```bash
./scripts/deploy-artifact.sh "My App Title" path/to/output.html 42
```
```

**In a Claude Code session**, Claude can generate output to `dist/index.html` and then run the deploy script — no context switching, no manual copy-paste. The URL comes back in the terminal.

---

### Codex

OpenAI Codex CLI is a coding agent that runs tasks in your project directory. Wire it up with the same deploy script pattern.

**In your `AGENTS.md` or system context:**

```markdown
## Artifact deployment

When you produce a complete HTML application or report, deploy it:

1. Write the output to `dist/index.html`
2. Run: `./scripts/deploy-artifact.sh "<title>" dist/index.html`
3. Report the returned URL in your response

Environment variables are already set. Do not include secrets in code.
```

**Inline deploy without a script (for one-off tasks):**

```python
# codex can run this inline with the exec tool
import subprocess, json, os

def deploy(title, html):
    import base64, urllib.request
    site = os.environ["ARTIFACT_WP_SITE"]
    auth = base64.b64encode(
        f"{os.environ['ARTIFACT_WP_USER']}:{os.environ['ARTIFACT_WP_PASS']}".encode()
    ).decode()
    payload = json.dumps({
        "title": title, "status": "publish",
        "meta": {"artifact_html": html}
    }).encode()
    req = urllib.request.Request(
        f"{site}/wp-json/wp/v2/artifacts",
        data=payload,
        headers={"Authorization": f"Basic {auth}", "Content-Type": "application/json"}
    )
    result = json.loads(urllib.request.urlopen(req).read())
    return result["link"]
```

**Tip:** Set `ARTIFACT_WP_SITE`, `ARTIFACT_WP_USER`, and `ARTIFACT_WP_PASS` in your shell environment or `.env` file. Never hardcode credentials.

---

### GitHub Actions (versioned project)

For projects that build a static HTML output (dashboards, reports, docs, changelogs), GitHub Actions can deploy directly to an artifact on every push. The artifact ID is stored as a repository variable so the public URL stays stable across all future deploys.

**First-time setup:**
1. Add these secrets to your repository: `ARTIFACT_WP_SITE`, `ARTIFACT_WP_USER`, `ARTIFACT_WP_PASS`
2. Run the workflow once — it will print the artifact ID in the job logs
3. Add the artifact ID as a repository variable named `ARTIFACT_ID`

**`.github/workflows/deploy-artifact.yml`:**

```yaml
name: Deploy Artifact

on:
  push:
    branches: [main]
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Set up Node
        uses: actions/setup-node@v4
        with:
          node-version: 20

      - name: Install dependencies
        run: npm ci

      - name: Build
        run: npm run build
        # Expects a single-file HTML output at dist/index.html

      - name: Deploy to Artifact
        env:
          WP_SITE: ${{ secrets.ARTIFACT_WP_SITE }}
          WP_USER: ${{ secrets.ARTIFACT_WP_USER }}
          WP_PASS: ${{ secrets.ARTIFACT_WP_PASS }}
          ARTIFACT_ID: ${{ vars.ARTIFACT_ID }}
        run: |
          PAYLOAD=$(jq -n \
            --arg title   "My Dashboard" \
            --rawfile html dist/index.html \
            --arg desc    "Deployed from commit ${{ github.sha }} on $(date -u +%Y-%m-%dT%H:%M:%SZ)" \
            '{
              title: $title,
              status: "publish",
              meta: { artifact_html: $html, artifact_description: $desc }
            }')

          if [ -n "$ARTIFACT_ID" ]; then
            echo "Updating existing artifact $ARTIFACT_ID..."
            ENDPOINT="$WP_SITE/wp-json/wp/v2/artifacts/$ARTIFACT_ID"
          else
            echo "Creating new artifact..."
            ENDPOINT="$WP_SITE/wp-json/wp/v2/artifacts"
          fi

          RESPONSE=$(curl -sf -X POST "$ENDPOINT" \
            -u "$WP_USER:$WP_PASS" \
            -H "Content-Type: application/json" \
            -d "$PAYLOAD")

          ID=$(echo "$RESPONSE" | jq -r '.id')
          URL=$(echo "$RESPONSE" | jq -r '.link')

          echo "Artifact ID: $ID"
          echo "Public URL:  $URL"

          if [ -z "$ARTIFACT_ID" ]; then
            echo ""
            echo "⚠️  First deploy complete. Set ARTIFACT_ID=$ID as a repository variable"
            echo "   to lock in this URL for all future deploys."
          fi
```

**What this gives you:**
- Push to `main` → artifact updates automatically within seconds
- The public URL never changes (as long as `ARTIFACT_ID` is set)
- `artifact_description` records the commit SHA and deploy timestamp for traceability
- Workflow is idempotent — safe to re-run

**For projects with a build step that produces multiple files**, use a bundler (Vite, esbuild, Parcel) configured to produce a single `index.html` with inlined assets, or use a tool like `html-inline` to merge the output before deploying.

**Variant: deploy on tag instead of push:**

```yaml
on:
  push:
    tags: ['v*']
```

This lets you control when the live artifact updates — only on explicit releases.

---

## Security

### Access Control

Only Administrators can create or edit artifacts by default. To extend access to additional roles:

```php
// In your theme's functions.php or a custom plugin
botcreds_agent_artifacts_grant_to_role( 'editor' );
```

For programmatic access, create an **Application Password** for a dedicated agent user with the Administrator role. Do not use your main admin password.

### Content Security Policy

The default CSP applied to every artifact:

```
default-src 'self';
script-src 'self' {uploads}/artifacts/ cdn.jsdelivr.net unpkg.com cdnjs.cloudflare.com esm.sh cdn.skypack.dev;
style-src 'self' 'unsafe-inline' {uploads}/artifacts/ {cdns} fonts.googleapis.com;
img-src 'self' data: blob: pixel.wp.com stats.wordpress.com;
font-src 'self' data: fonts.gstatic.com {cdns};
connect-src 'self' [artifact-declared-origins] pixel.wp.com stats.wordpress.com;
frame-src 'none';
frame-ancestors 'self';
form-action 'self';
base-uri 'self'
```

To modify the CSP for a specific artifact or site-wide:

```php
add_filter( 'botcreds_agent_artifacts_csp', function( $csp, $post_id ) {
    // Add a specific origin for one artifact
    if ( $post_id === 42 ) {
        $csp .= "; connect-src 'self' https://api.myservice.com";
    }
    return $csp;
}, 10, 2 );
```

### Allowed HTML Tags

The plugin allows a superset of `wp_kses_allowed_html('post')` including:

| Element | Allowed attributes |
|---------|--------------------|
| `<canvas>` | `id`, `class`, `width`, `height`, `style` |
| `<svg>` | `xmlns`, `viewbox`, `width`, `height`, `class`, `id`, `style` |
| `<path>` | `d`, `fill`, `stroke`, `class` |
| `<input>` | `type`, `id`, `class`, `name`, `value`, `placeholder`, `disabled`, `readonly`, `checked`, `min`, `max`, `step`, `style` |
| `<button>` | `type`, `id`, `class`, `disabled`, `style` |
| `<select>` | `id`, `class`, `name`, `style` |
| `<option>` | `value`, `selected` |
| `<label>` | `for`, `class` |
| `<video>` | `src`, `controls`, `autoplay`, `loop`, `muted`, `width`, `height`, `class`, `id` |
| `<audio>` | `src`, `controls`, `autoplay`, `loop`, `class`, `id` |

`data-*` attributes are allowed on `div`, `span`, `button`, `input`, `a`, and `canvas`.

To add additional tags or attributes:

```php
add_filter( 'botcreds_agent_artifacts_allowed_html', function( $allowed ) {
    $allowed['details'] = [ 'open' => true, 'class' => true ];
    $allowed['summary'] = [ 'class' => true ];
    return $allowed;
} );
```

---

## Developer Reference

### Hooks

| Hook | Type | Arguments | Description |
|------|------|-----------|-------------|
| `botcreds_agent_artifacts_csp` | filter | `$csp` (string), `$post_id` (int) | Modify the full CSP header value for an artifact page. |
| `botcreds_agent_artifacts_allowed_html` | filter | `$allowed` (array) | Modify the `wp_kses` allowed-tags array for body sanitization. |

### Functions

```php
botcreds_agent_artifacts_grant_to_role( string $role_name ): void
```

Grants all artifact capabilities (`edit_artifacts`, `publish_artifacts`, `delete_artifacts`, etc.) to the specified WordPress role.

### Custom Template

Place `single-artifact.php` in your active theme directory to override the plugin's default render template. The plugin's template is used as a fallback when no theme template exists.

### Meta Fields

| Meta key | Type | REST | Description |
|----------|------|------|-------------|
| `artifact_html` | string | read/write | The raw HTML submitted at deploy time. |
| `artifact_description` | string | read/write | Optional description. |
| `artifact_connect_src` | array | read/write | Explicit `https://` origins to add to `connect-src`. |
| `_artifact_body` | string | private | Processed HTML body (scripts/styles removed). |
| `_artifact_assets` | object | private | Extracted asset manifest (handles, URLs, external flag). |
| `_artifact_connect_src` | array | private | Merged + sanitized connect-src origins. |

---

## File Structure

```
botcreds-agent-artifacts/
├── botcreds-agent-artifacts.php   # Main plugin — post type, REST hooks, HTML parser, asset enqueuer
├── single-artifact.php            # Render template — security headers, CSP, body output
├── readme.txt                     # WordPress.org readme
├── README.md                      # This file
└── LICENSE

wp-content/uploads/artifacts/
└── {post_id}/
    ├── style-0.css                # Extracted <style> blocks
    ├── style-1.css
    ├── script-0.js                # Extracted <script> blocks
    └── script-1.js
```

---

## Backwards Compatibility

Artifacts created before v1.0 (stored as raw HTML in `artifact_html`) still render via a legacy fallback path in the template. No migration needed.

If you used the old filter hooks from the OC Artifacts era, update them:
- `oc_artifacts_csp` → `botcreds_agent_artifacts_csp`
- `oc_artifacts_allowed_html` → `botcreds_agent_artifacts_allowed_html`

All post type slugs, meta keys, and REST endpoints are unchanged from v1.0 onward.

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)
