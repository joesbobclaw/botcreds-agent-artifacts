# BotCreds Agent Artifacts

A WordPress plugin that lets AI agents deploy self-contained HTML/CSS/JS applications to a WordPress site via a single REST API call.

## How It Works

1. **POST a single HTML file** via the REST API
2. **Plugin parses the HTML** — extracts `<script>` and `<style>` blocks
3. **JS/CSS saved as static files** in `wp-content/uploads/artifacts/{id}/`
4. **Enqueued via WordPress** using `wp_enqueue_script()` / `wp_enqueue_style()`
5. **HTML body sanitized** with `wp_kses()` and an expanded allowed-tags list
6. **Served with security headers** — CSP, X-Frame-Options, Referrer-Policy

Developer experience is one API call. The parsing and asset handling happen server-side.

## Installation

1. Clone or download this repo
2. Upload the `botcreds-agent-artifacts` folder to `/wp-content/plugins/`
3. Activate **BotCreds Agent Artifacts** in WordPress admin
4. Go to **Settings → Permalinks** → **Save Changes** (flushes rewrite rules)

## Usage

### Deploy via REST API

```bash
curl -X POST "https://your-site.com/wp-json/wp/v2/artifacts" \
  -u "username:application-password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My Cool App",
    "status": "publish",
    "meta": {
      "artifact_html": "<!DOCTYPE html><html>...</html>",
      "artifact_description": "Built by my AI agent"
    }
  }'
```

The response `link` field is the public URL of the deployed artifact.

### Python example

```python
import urllib.request, json, base64

site  = "https://your-site.com"
creds = base64.b64encode(b"user:app-password").decode()

with open("my-app.html") as f:
    html = f.read()

data = json.dumps({
    "title": "My Cool App",
    "status": "publish",
    "meta": {
        "artifact_html": html,
        "artifact_description": "Built by my AI agent"
    }
}).encode()

req = urllib.request.Request(
    f"{site}/wp-json/wp/v2/artifacts",
    data=data,
    headers={
        "Authorization": f"Basic {creds}",
        "Content-Type": "application/json"
    }
)

result = json.loads(urllib.request.urlopen(req).read())
print(f"Deployed: {result['link']}")
```

## Security

### Access Control

Only Administrators can create artifacts by default. To allow Editors:

```php
botcreds_agent_artifacts_grant_to_role( 'editor' );
```

### Content Security Policy

Artifact pages are served with CSP headers that:
- ✅ Allow scripts from the artifact's upload directory
- ✅ Allow inline styles
- ❌ Block external script loading
- ❌ Block cross-origin fetch/XHR
- ❌ Block iframe embedding

### Customization Hooks

**Modify CSP:**

```php
add_filter( 'botcreds_agent_artifacts_csp', function( $csp, $post_id ) {
    return $csp . "; connect-src 'self' https://api.example.com";
}, 10, 2 );
```

**Modify allowed HTML tags:**

```php
add_filter( 'botcreds_agent_artifacts_allowed_html', function( $allowed ) {
    $allowed['my-element'] = [ 'class' => true ];
    return $allowed;
} );
```

## File Structure

```
botcreds-agent-artifacts/
├── botcreds-agent-artifacts.php   # Main plugin
├── single-artifact.php            # Render template
├── readme.txt                     # WordPress.org readme
├── README.md                      # This file
└── LICENSE

wp-content/uploads/artifacts/
└── {post_id}/
    ├── style-0.css
    ├── script-0.js
    └── ...
```

## Backwards Compatibility

Artifacts created before v1.0 (raw HTML stored directly) still render via a legacy fallback path. No migration needed.

If you used the old filter hooks from the OC Artifacts era, update them:
- `oc_artifacts_csp` → `botcreds_agent_artifacts_csp`
- `oc_artifacts_allowed_html` → `botcreds_agent_artifacts_allowed_html`

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)
