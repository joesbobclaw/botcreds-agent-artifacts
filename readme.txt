=== BotCreds Agent Artifacts ===

Contributors:      botcreds
Tags:              artifacts, api, rest-api, ai, agents
Requires at least: 6.0
Tested up to:      6.8
Stable tag:        1.3.1
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Deploy self-contained HTML/CSS/JS apps to WordPress via REST API. One call from any AI agent or CI pipeline — plugin handles the rest.

== Description ==

**BotCreds Agent Artifacts** gives AI agents a permanent home for their outputs.

Post a single HTML file to the REST API. The plugin parses it, extracts scripts and styles, saves them as static files, enqueues them properly via WordPress APIs, and serves the result at a clean public URL with strict security headers. No build tools. No infrastructure. One API call.

= How It Works =

1. POST raw HTML to `/wp-json/wp/v2/artifacts`
2. The plugin extracts `<script>` and `<style>` blocks and saves them as static files in `wp-content/uploads/artifacts/{id}/`
3. JS and CSS are enqueued via `wp_enqueue_script()` / `wp_enqueue_style()` — no inline scripts in rendered output
4. The HTML body is sanitized with `wp_kses()` and an expanded allowed-tags list (canvas, SVG, inputs, video, audio, data-* attributes)
5. The artifact is served at `yourdomain.com/artifacts/{slug}/` with a strict Content Security Policy

From the caller's perspective: POST HTML, get URL. Everything else happens server-side.

= Features =

**Deployment**

* Single REST API call — no SDK, no library, any HTTP client works
* Update artifacts in place — POST to `/wp-json/wp/v2/artifacts/{id}` and the public URL stays the same
* Optional `artifact_description` field for internal documentation
* Head content preservation — `<meta>` tags and other `<head>` elements from submitted HTML are preserved in output
* External script support — `<script src="...">` tags are registered as external dependencies and enqueued alongside local assets
* Asset cache busting — enqueued files are versioned with `filemtime()` so browsers fetch updates automatically
* Clean redeploys — old asset files are deleted before new ones are written

**Security**

* Content Security Policy on every artifact page — blocks external script injection and cross-origin data exfiltration by default
* Trusted CDN list out of the box: `cdn.jsdelivr.net`, `unpkg.com`, `cdnjs.cloudflare.com`, `esm.sh`, `cdn.skypack.dev` — scripts and styles load from these without any configuration
* Per-artifact API allowlist — artifacts that call external APIs declare their origins via an HTML pragma comment (`<!-- artifact:fetch https://api.example.com -->`) or a deploy-time meta field; the plugin adds them to `connect-src` automatically
* Additional security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
* Custom capability type — `artifact` capabilities are separate from standard post capabilities; only Administrators can create artifacts by default

**Developer Hooks**

* `botcreds_agent_artifacts_csp` — filter the full CSP header value for any artifact
* `botcreds_agent_artifacts_allowed_html` — filter the `wp_kses` allowed-tags array
* `botcreds_agent_artifacts_grant_to_role()` — helper to grant capabilities to additional roles
* Custom template — drop `single-artifact.php` in your active theme to replace the render template

= Use Cases =

**OpenClaw (AI personal assistant)**

OpenClaw agents can deploy interactive dashboards, daily digests, data visualizations, and mini-apps in a single tool call. Generate the HTML, POST it, get the URL — no manual steps, no context switching.

For artifacts that fetch live data, add a pragma comment to the HTML and the CSP is updated automatically:

`<!-- artifact:fetch https://api.openweathermap.org -->`

For recurring reports (daily digests, weekly summaries), store the artifact ID after the first deploy and update in place on subsequent runs. The URL never changes.

**Claude Code (terminal-based coding agent)**

Claude Code sessions can invoke a shell deploy script directly after generating output. Add a `scripts/deploy-artifact.sh` to your project and reference it in your `CLAUDE.md` — Claude will use it to ship outputs without leaving the terminal. No manual copy-paste, no browser switching.

**Codex (OpenAI coding agent)**

Same pattern as Claude Code. Add deployment instructions to your `AGENTS.md` and Codex can write HTML, call the deploy script, and report the live URL — all in one agent run.

**GitHub Actions (versioned project)**

For projects that build a static HTML output — dashboards, reports, documentation, changelogs — a GitHub Actions workflow can deploy to an artifact on every push to `main`. The artifact ID is stored as a repository variable so the public URL stays stable across all future deploys. Push → build → deploy → done.

= Example: Deploy via REST API =

`curl -X POST "https://your-site.com/wp-json/wp/v2/artifacts" \
  -u "username:application-password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My App",
    "status": "publish",
    "meta": {
      "artifact_html": "<!DOCTYPE html><html><body><h1>Hello.</h1></body></html>",
      "artifact_description": "Built by my AI agent"
    }
  }'`

The response `link` field is the public URL of the deployed artifact.

= Example: Artifact with Live Data =

Include the fetch pragma in your HTML — no configuration needed:

`<!-- artifact:fetch https://api.openweathermap.org -->
<!DOCTYPE html>
<html>
<body>
  <div id="weather"></div>
  <script>
    fetch('https://api.openweathermap.org/data/2.5/weather?q=Denver&appid=YOUR_KEY')
      .then(r => r.json())
      .then(d => document.getElementById('weather').textContent = d.weather[0].description);
  </script>
</body>
</html>`

= Example: GitHub Actions Deployment =

`name: Deploy Artifact
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: npm ci && npm run build
      - name: Deploy to Artifact
        env:
          WP_SITE: ${{ secrets.ARTIFACT_WP_SITE }}
          WP_USER: ${{ secrets.ARTIFACT_WP_USER }}
          WP_PASS: ${{ secrets.ARTIFACT_WP_PASS }}
          ARTIFACT_ID: ${{ vars.ARTIFACT_ID }}
        run: |
          PAYLOAD=$(jq -n --arg title "My Dashboard" --rawfile html dist/index.html \
            '{title: $title, status: "publish", meta: {artifact_html: $html}}')
          ENDPOINT="$WP_SITE/wp-json/wp/v2/artifacts"
          [ -n "$ARTIFACT_ID" ] && ENDPOINT="$ENDPOINT/$ARTIFACT_ID"
          curl -sf -X POST "$ENDPOINT" -u "$WP_USER:$WP_PASS" \
            -H "Content-Type: application/json" -d "$PAYLOAD" | jq -r '.link'`

== Installation ==

1. Upload the `botcreds-agent-artifacts` folder to `/wp-content/plugins/`
2. Activate **BotCreds Agent Artifacts** in the WordPress admin under **Plugins**
3. Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules
4. Create a WordPress **Application Password** for your agent user: **Users → Profile → Application Passwords**

== Frequently Asked Questions ==

= Does this work with any REST client or AI agent? =

Yes. Any HTTP client that can POST JSON with Basic Auth (username + Application Password) works. No SDK or special library required. curl, Python's urllib, Node's fetch — anything goes.

= Are artifacts sandboxed? =

Artifact pages are served with a Content Security Policy that blocks external script injection and cross-origin fetch/XHR by default. The CSP is filterable (`botcreds_agent_artifacts_csp`) for custom use cases, and per-artifact API origins can be declared via the `artifact:fetch` pragma or the `artifact_connect_src` meta field.

= What scripts and CDNs are allowed without configuration? =

The following CDNs are in the default `script-src` allowlist: `cdn.jsdelivr.net`, `unpkg.com`, `cdnjs.cloudflare.com`, `esm.sh`, `cdn.skypack.dev`. External modules loaded from these CDNs work in artifact HTML without any additional configuration.

= How do I allow my artifact to call an external API? =

Two options:

**Option 1 — HTML pragma (recommended):** Add a comment to your submitted HTML:
`<!-- artifact:fetch https://api.example.com -->`

The plugin strips the comment from output and adds the origin to `connect-src`.

**Option 2 — Deploy-time meta field:** Pass `artifact_connect_src` as an array:
`"meta": { "artifact_connect_src": ["https://api.example.com"] }`

Both options accept only `https://` origins. No wildcards, no `http://`.

= How do I update an artifact without changing its URL? =

POST to `/wp-json/wp/v2/artifacts/{id}` with the same fields. The existing asset files are cleared, new files are written, and the public URL (`/artifacts/{slug}/`) does not change.

= What HTML elements and attributes are allowed? =

The plugin allows a superset of `wp_kses_allowed_html('post')` including `<canvas>`, `<svg>`, `<path>`, `<input>`, `<button>`, `<select>`, `<option>`, `<label>`, `<video>`, and `<audio>` with their common attributes. `data-*` attributes are allowed on interactive elements. Additional tags and attributes can be added via the `botcreds_agent_artifacts_allowed_html` filter.

= Who can create artifacts? =

Only Administrators by default. To grant access to additional roles:

`botcreds_agent_artifacts_grant_to_role( 'editor' );`

= Can I use a custom template? =

Yes. Place `single-artifact.php` in your active theme and it takes precedence over the plugin's default template. The plugin template is used as a fallback.

= Will my old artifacts still work after upgrading? =

Yes. Artifacts created before v1.0 (stored as raw HTML) are still rendered via a legacy fallback path. No migration needed.

= How do I update the Content Security Policy? =

Use the `botcreds_agent_artifacts_csp` filter:

`add_filter( 'botcreds_agent_artifacts_csp', function( $csp, $post_id ) {
    return $csp . "; worker-src 'self'";
}, 10, 2 );`

== Screenshots ==

1. An artifact deployed via the REST API, rendered at its public URL.
2. The Artifacts list in WordPress admin.

== Changelog ==

= 1.3.1 =
* Bug fix: external `<script src="...">` nodes are now correctly removed from the DOM before the body is serialized, preventing duplicate rendering

= 1.3.0 =
* Per-artifact API allowlist: declare trusted external API origins via `<!-- artifact:fetch https://api.example.com -->` pragma in submitted HTML
* Per-artifact API allowlist: `artifact_connect_src` meta field for deploy-time origin declaration via REST
* Both methods are merged and deduplicated; only `https://` origins accepted (no wildcards, no http)
* CSP `connect-src` is now dynamically built from declared origins per artifact

= 1.2.0 =
* Trusted CDN allowlist in default CSP: `cdn.jsdelivr.net`, `unpkg.com`, `cdnjs.cloudflare.com`, `esm.sh`, `cdn.skypack.dev`
* External `<script src="...">` tags are now extracted and enqueued as external script dependencies
* Head content (`<head>` tags) from submitted HTML is preserved and injected into the artifact's `<head>`
* Asset cache busting: enqueued files are versioned with `filemtime()` for automatic browser cache invalidation
* Clean redeploys: old asset files are deleted before new files are written
* File operations use `WP_Filesystem` for proper WordPress filesystem abstraction

= 1.1.0 =
* Rebrand: OC Artifacts → BotCreds Agent Artifacts
* Updated plugin header (Plugin URI, Author, Text Domain)
* Renamed filter hooks: `botcreds_agent_artifacts_csp`, `botcreds_agent_artifacts_allowed_html`
* Renamed helper: `botcreds_agent_artifacts_grant_to_role()`
* Added `Requires at least` and `Requires PHP` headers
* Improved inline documentation and code style
* Post type slug, meta keys, and REST endpoints unchanged (drop-in compatible)

= 1.0.0 =
* Complete rewrite: HTML is now parsed server-side; scripts and styles are extracted and properly enqueued via WordPress APIs
* Strict Content Security Policy applied to all artifact pages
* Sanitization via `wp_kses()` with expanded allowed-tag list
* Backwards compatibility with pre-1.0 raw-HTML artifacts

= 0.3.0 =
* Security headers added (X-Frame-Options, Referrer-Policy)
* Custom capability type for fine-grained access control

= 0.2.0 =
* REST API endpoint for artifact creation
* Custom single template

= 0.1.0 =
* Initial release

== Upgrade Notice ==

= 1.3.1 =
Bug fix for external script handling. Update recommended.

= 1.3.0 =
New per-artifact API allowlist feature. Artifacts that call external APIs can now declare trusted origins via an HTML pragma comment rather than a server-side CSP filter.

= 1.1.0 =
Rebrand release. If you use the `oc_artifacts_csp` or `oc_artifacts_allowed_html` filters, update them to `botcreds_agent_artifacts_csp` and `botcreds_agent_artifacts_allowed_html`. All data (post type, meta keys, REST endpoints) is unchanged.
