=== BotCreds Agent Artifacts ===

Contributors:      botcreds
Tags:              artifacts, api, rest-api, ai, agents
Requires at least: 6.0
Tested up to:      6.8
Stable tag:        1.1.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Deploy HTML/CSS/JS artifacts to WordPress via REST API. Built for AI agents.

== Description ==

**BotCreds Agent Artifacts** lets AI agents (or any REST client) deploy self-contained HTML/CSS/JS applications directly to a WordPress site with a single API call.

The plugin handles everything server-side:

* Parses the submitted HTML — extracts `<script>` and `<style>` blocks
* Saves JS/CSS as separate static files in `wp-content/uploads/artifacts/{id}/`
* Enqueues them properly via `wp_enqueue_script()` and `wp_enqueue_style()`
* Sanitizes the HTML body via `wp_kses()` with an expanded allowed-tags list
* Serves artifact pages with strict security headers (CSP, X-Frame-Options, etc.)

From the agent's perspective the API is one call — POST a single HTML file and get back a public URL.

= Use Cases =

* AI-generated interactive reports and dashboards
* Agent-built mini-apps and tools
* Data visualizations, games, and prototypes
* Any HTML/CSS/JS output that needs a permanent public URL

= Developer Hooks =

**Modify Content Security Policy:**

`add_filter( 'botcreds_agent_artifacts_csp', function( $csp, $post_id ) {
    return $csp . "; connect-src 'self' https://api.example.com";
}, 10, 2 );`

**Modify allowed HTML tags:**

`add_filter( 'botcreds_agent_artifacts_allowed_html', function( $allowed ) {
    $allowed['my-element'] = [ 'class' => true ];
    return $allowed;
} );`

**Grant artifact capabilities to additional roles:**

`botcreds_agent_artifacts_grant_to_role( 'editor' );`

== Installation ==

1. Upload the `botcreds-agent-artifacts` folder to `/wp-content/plugins/`
2. Activate **BotCreds Agent Artifacts** in the WordPress admin under **Plugins**
3. Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules

== Usage ==

= Deploy an artifact via REST API =

`curl -X POST "https://your-site.com/wp-json/wp/v2/artifacts" \
  -u "username:application-password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My App",
    "status": "publish",
    "meta": {
      "artifact_html": "<!DOCTYPE html><html>...</html>",
      "artifact_description": "An interactive app built by my agent"
    }
  }'`

The response includes a `link` field with the public URL of the deployed artifact.

= Access Control =

Only Administrators can create artifacts by default (enforced via custom capabilities).

To allow Editors to create artifacts, add this to your theme's `functions.php`:

`botcreds_agent_artifacts_grant_to_role( 'editor' );`

== Frequently Asked Questions ==

= Does this work with any REST client or AI agent? =

Yes. Any HTTP client that can POST JSON with Basic Auth (username + Application Password) can deploy artifacts. No SDK or special library required.

= Are artifacts sandboxed? =

Artifacts are served with a Content Security Policy that blocks external script loading and cross-origin fetch/XHR, reducing data exfiltration risk. The CSP is filterable for custom use cases.

= What happens to scripts and styles in the submitted HTML? =

They are extracted, saved as static files in `wp-content/uploads/artifacts/{post_id}/`, and enqueued via standard WordPress APIs. No inline `<script>` tags appear in the rendered output.

= Will my old artifacts still work after upgrading? =

Yes. Artifacts created before v1.0 (stored as raw HTML) are still rendered via a legacy fallback path in the template. No migration needed.

= Can I use a custom template? =

Yes. Place a `single-artifact.php` file in your active theme and it will take precedence over the plugin's default template.

== Screenshots ==

1. An artifact deployed via the REST API, rendered at its public URL.

== Changelog ==

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

= 1.1.0 =
Rebrand release. If you use the `oc_artifacts_csp` or `oc_artifacts_allowed_html` filters, update them to `botcreds_agent_artifacts_csp` and `botcreds_agent_artifacts_allowed_html`. All data (post type, meta keys, REST endpoints) is unchanged.
