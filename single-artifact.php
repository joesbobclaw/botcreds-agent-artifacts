<?php
/**
 * Template for rendering BotCreds Agent Artifacts.
 * Uses properly enqueued scripts/styles via WordPress.
 *
 * @package BotCreds_Agent_Artifacts
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Returns the BotCreds branding bar HTML + inline CSS.
 *
 * @param string $title  Artifact title.
 * @return string HTML string (not escaped — caller is responsible for context).
 */
function botcreds_artifacts_branding_bar( $title ) {
	$back_url  = home_url( '/artifacts/' );
	$title_esc = esc_html( $title );
	return '<style>
.botcreds-bar{position:fixed;top:0;left:0;right:0;height:44px;background:#fff;border-bottom:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.07);display:flex;align-items:center;padding:0 20px;gap:14px;z-index:9999;font-family:-apple-system,BlinkMacSystemFont,"Inter","Segoe UI",sans-serif;font-size:13px;line-height:1;box-sizing:border-box}
.botcreds-bar__logo{display:flex;align-items:center;gap:6px;color:#1e1b4b;font-weight:700;font-size:14px;text-decoration:none;white-space:nowrap;letter-spacing:-.01em}
.botcreds-bar__logo:hover{color:#4f46e5}
.botcreds-bar__icon{font-size:16px;line-height:1}
.botcreds-bar__title{flex:1;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px}
.botcreds-bar__back{color:#4f46e5;text-decoration:none;white-space:nowrap;font-size:12px;font-weight:500;padding:5px 10px;border:1px solid #e0e7ff;border-radius:6px;background:#f5f3ff;transition:background .15s,border-color .15s}
.botcreds-bar__back:hover{background:#ede9fe;border-color:#c4b5fd}
@media(max-width:480px){.botcreds-bar__title{display:none}.botcreds-bar__back{font-size:11px;padding:4px 8px}}
</style>
<nav class="botcreds-bar" aria-label="BotCreds">
<a class="botcreds-bar__logo" href="https://botcreds.com" target="_blank" rel="noopener"><span class="botcreds-bar__icon" aria-hidden="true">\xf0\x9f\xa4\x96</span><span class="botcreds-bar__name">BotCreds</span></a>
<span class="botcreds-bar__title">' . $title_esc . '</span>
<a class="botcreds-bar__back" href="' . esc_url( $back_url ) . '"><span aria-hidden="true">\xe2\x86\x90</span> All Artifacts</a>
</nav>';
}

$post_id = get_the_ID();
$body    = get_post_meta( $post_id, '_artifact_body',   true );
$assets  = get_post_meta( $post_id, '_artifact_assets', true );

// Fallback: legacy raw HTML (pre-1.0 artifacts).
if ( empty( $body ) ) {
	$raw_html = get_post_meta( $post_id, 'artifact_html', true );
	if ( ! empty( $raw_html ) ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		// Inject BotCreds branding bar into legacy HTML artifacts.
		$branding_bar = botcreds_artifacts_branding_bar( get_the_title() );
		if ( stripos( $raw_html, '<body' ) !== false ) {
			$raw_html = preg_replace( '/(<body[^>]*>)/i', '$1' . $branding_bar, $raw_html, 1 );
		} else {
			$raw_html = $branding_bar . $raw_html;
		}
		echo $raw_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	get_header();
	echo '<div class="artifact-empty" style="padding:2rem;text-align:center;">';
	echo '<h1>' . esc_html( get_the_title() ) . '</h1>';
	echo '<p>' . esc_html__( 'This artifact has no content yet.', 'botcreds-agent-artifacts' ) . '</p>';
	echo '</div>';
	get_footer();
	exit;
}

// Security headers.
header( 'X-Content-Type-Options: nosniff' );
header( 'X-Frame-Options: SAMEORIGIN' );
header( 'Referrer-Policy: strict-origin-when-cross-origin' );

// CSP — scripts are served as static files, so we can be strict.
$upload_dir    = wp_upload_dir();
$artifacts_url = $upload_dir['baseurl'] . '/artifacts/';

// Trusted CDN origins — scripts, styles, and fonts.
$cdn_origins = implode( ' ', [
	'https://cdn.jsdelivr.net',
	'https://unpkg.com',
	'https://cdnjs.cloudflare.com',
	'https://esm.sh',
	'https://cdn.skypack.dev',
] );

// Per-artifact fetch allowlist — set via pragma or deploy-time meta.
$artifact_connect_src = get_post_meta( $post_id, '_artifact_connect_src', true );
$connect_src_value    = "'self'";
if ( ! empty( $artifact_connect_src ) && is_array( $artifact_connect_src ) ) {
	$safe_origins      = implode( ' ', array_map( 'esc_url_raw', $artifact_connect_src ) );
	$connect_src_value = "'self' {$safe_origins}";
}

$csp_parts = [
	"default-src 'self'",
	"script-src 'self' {$artifacts_url} {$cdn_origins}",
	"style-src 'self' 'unsafe-inline' {$artifacts_url} {$cdn_origins} https://fonts.googleapis.com",
	"img-src 'self' data: blob: https://pixel.wp.com https://stats.wordpress.com",
	"font-src 'self' data: https://fonts.gstatic.com {$cdn_origins}",
	"connect-src {$connect_src_value} https://pixel.wp.com https://stats.wordpress.com",
	"frame-src 'none'",
	"frame-ancestors 'self'",
	"form-action 'self'",
	"base-uri 'self'",
];

$csp = implode( '; ', $csp_parts );

/**
 * Filters the Content Security Policy header for artifact pages.
 *
 * @param string $csp     The CSP header value.
 * @param int    $post_id The artifact post ID.
 */
$csp = apply_filters( 'botcreds_agent_artifacts_csp', $csp, $post_id );
header( "Content-Security-Policy: $csp" );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( get_the_title() ); ?> &mdash; <?php bloginfo( 'name' ); ?></title>
	<?php
	// Output any preserved <head> content (meta tags, etc.).
	if ( ! empty( $assets['head'] ) ) {
		$head_content = preg_replace( '/<\/?head[^>]*>/i', '', $assets['head'] );
		echo $head_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// WordPress head — includes enqueued styles.
	wp_head();
	?>
</head>
<body <?php body_class( 'artifact-page' ); ?>>
	<?php wp_body_open(); ?>

	<?php echo botcreds_artifacts_branding_bar( get_the_title() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="artifact-container">
		<?php
		// Expanded allowed-tags list for interactive artifact content.
		$allowed_html = wp_kses_allowed_html( 'post' );

		$allowed_html['canvas'] = [
			'id' => true, 'class' => true, 'width' => true, 'height' => true, 'style' => true,
		];
		$allowed_html['svg'] = [
			'xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true,
			'class' => true, 'id' => true, 'style' => true,
		];
		$allowed_html['path'] = [
			'd' => true, 'fill' => true, 'stroke' => true, 'class' => true,
		];
		$allowed_html['input'] = [
			'type' => true, 'id' => true, 'class' => true, 'name' => true,
			'value' => true, 'placeholder' => true, 'disabled' => true,
			'readonly' => true, 'checked' => true, 'min' => true, 'max' => true,
			'step' => true, 'style' => true,
		];
		$allowed_html['button'] = [
			'type' => true, 'id' => true, 'class' => true, 'disabled' => true, 'style' => true,
		];
		$allowed_html['select'] = [
			'id' => true, 'class' => true, 'name' => true, 'style' => true,
		];
		$allowed_html['option'] = [
			'value' => true, 'selected' => true,
		];
		$allowed_html['label'] = [
			'for' => true, 'class' => true,
		];
		$allowed_html['video'] = [
			'src' => true, 'controls' => true, 'autoplay' => true, 'loop' => true,
			'muted' => true, 'width' => true, 'height' => true, 'class' => true, 'id' => true,
		];
		$allowed_html['audio'] = [
			'src' => true, 'controls' => true, 'autoplay' => true, 'loop' => true,
			'class' => true, 'id' => true,
		];

		// Allow data-* on common interactive elements.
		foreach ( [ 'div', 'span', 'button', 'input', 'a', 'canvas' ] as $tag ) {
			if ( isset( $allowed_html[ $tag ] ) ) {
				$allowed_html[ $tag ]['data-*'] = true;
			}
		}

		/**
		 * Filters the allowed HTML tags for artifact body output.
		 *
		 * @param array $allowed_html Allowed HTML tags and attributes.
		 */
		$allowed_html = apply_filters( 'botcreds_agent_artifacts_allowed_html', $allowed_html );

		echo wp_kses( $body, $allowed_html );
		?>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
