<?php
/**
 * Template for rendering BotCreds Agent Artifacts.
 * Uses properly enqueued scripts/styles via WordPress.
 *
 * @package BCAA
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template file; variables are scoped to this include context, not truly global.

$bcaa_post_id = get_the_ID();
$bcaa_body    = get_post_meta( $bcaa_post_id, '_artifact_body',   true );
$bcaa_assets  = get_post_meta( $bcaa_post_id, '_artifact_assets', true );

// Fallback: legacy raw HTML (pre-1.0 artifacts).
if ( empty( $bcaa_body ) ) {
	$bcaa_raw_html = get_post_meta( $bcaa_post_id, 'artifact_html', true );
	if ( ! empty( $bcaa_raw_html ) ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $bcaa_raw_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
$bcaa_upload_dir    = wp_upload_dir();
$bcaa_artifacts_url = $bcaa_upload_dir['baseurl'] . '/artifacts/';

// Trusted CDN origins — scripts, styles, and fonts.
$bcaa_cdn_origins = implode( ' ', [
	'https://cdn.jsdelivr.net',
	'https://unpkg.com',
	'https://cdnjs.cloudflare.com',
	'https://esm.sh',
	'https://cdn.skypack.dev',
] );

// Per-artifact fetch allowlist — set via pragma or deploy-time meta.
$bcaa_connect_src       = get_post_meta( $bcaa_post_id, '_artifact_connect_src', true );
$bcaa_connect_src_value = "'self'";
if ( ! empty( $bcaa_connect_src ) && is_array( $bcaa_connect_src ) ) {
	$bcaa_safe_origins      = implode( ' ', array_map( 'esc_url_raw', $bcaa_connect_src ) );
	$bcaa_connect_src_value = "'self' {$bcaa_safe_origins}";
}

$bcaa_csp_parts = [
	"default-src 'self'",
	"script-src 'self' {$bcaa_artifacts_url} {$bcaa_cdn_origins}",
	"style-src 'self' 'unsafe-inline' {$bcaa_artifacts_url} {$bcaa_cdn_origins} https://fonts.googleapis.com",
	"img-src 'self' data: blob: https://pixel.wp.com https://stats.wordpress.com",
	"font-src 'self' data: https://fonts.gstatic.com {$bcaa_cdn_origins}",
	"connect-src {$bcaa_connect_src_value} https://pixel.wp.com https://stats.wordpress.com",
	"frame-src 'none'",
	"frame-ancestors 'self'",
	"form-action 'self'",
	"base-uri 'self'",
];

$bcaa_csp = implode( '; ', $bcaa_csp_parts );

/**
 * Filters the Content Security Policy header for artifact pages.
 *
 * @param string $bcaa_csp     The CSP header value.
 * @param int    $bcaa_post_id The artifact post ID.
 */
$bcaa_csp = apply_filters( 'botcreds_agent_artifacts_csp', $bcaa_csp, $bcaa_post_id );
header( "Content-Security-Policy: $bcaa_csp" );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( get_the_title() ); ?> &mdash; <?php bloginfo( 'name' ); ?></title>
	<?php
	// Output any preserved <head> content (meta tags, etc.).
	if ( ! empty( $bcaa_assets['head'] ) ) {
		$bcaa_head_content = preg_replace( '/<\/?head[^>]*>/i', '', $bcaa_assets['head'] );
		// Sanitize head content — allow only safe, non-executable head elements.
		$bcaa_head_allowed = [
			'meta'  => [ 'name' => true, 'content' => true, 'charset' => true, 'http-equiv' => true, 'property' => true ],
			'link'  => [ 'rel' => true, 'href' => true, 'type' => true, 'media' => true, 'crossorigin' => true ],
			'title' => [],
			'base'  => [ 'href' => true, 'target' => true ],
		];
		echo wp_kses( $bcaa_head_content, $bcaa_head_allowed );
	}

	// WordPress head — includes enqueued styles.
	wp_head();
	?>
</head>
<body <?php body_class( 'artifact-page' ); ?>>
	<?php wp_body_open(); ?>

	<div class="artifact-container">
		<?php
		// Expanded allowed-tags list for interactive artifact content.
		$bcaa_allowed_html = wp_kses_allowed_html( 'post' );

		$bcaa_allowed_html['canvas'] = [
			'id' => true, 'class' => true, 'width' => true, 'height' => true, 'style' => true,
		];
		$bcaa_allowed_html['svg'] = [
			'xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true,
			'class' => true, 'id' => true, 'style' => true,
		];
		$bcaa_allowed_html['path'] = [
			'd' => true, 'fill' => true, 'stroke' => true, 'class' => true,
		];
		$bcaa_allowed_html['input'] = [
			'type' => true, 'id' => true, 'class' => true, 'name' => true,
			'value' => true, 'placeholder' => true, 'disabled' => true,
			'readonly' => true, 'checked' => true, 'min' => true, 'max' => true,
			'step' => true, 'style' => true,
		];
		$bcaa_allowed_html['button'] = [
			'type' => true, 'id' => true, 'class' => true, 'disabled' => true, 'style' => true,
		];
		$bcaa_allowed_html['select'] = [
			'id' => true, 'class' => true, 'name' => true, 'style' => true,
		];
		$bcaa_allowed_html['option'] = [
			'value' => true, 'selected' => true,
		];
		$bcaa_allowed_html['label'] = [
			'for' => true, 'class' => true,
		];
		$bcaa_allowed_html['video'] = [
			'src' => true, 'controls' => true, 'autoplay' => true, 'loop' => true,
			'muted' => true, 'width' => true, 'height' => true, 'class' => true, 'id' => true,
		];
		$bcaa_allowed_html['audio'] = [
			'src' => true, 'controls' => true, 'autoplay' => true, 'loop' => true,
			'class' => true, 'id' => true,
		];

		// Allow data-* on common interactive elements.
		foreach ( [ 'div', 'span', 'button', 'input', 'a', 'canvas' ] as $bcaa_tag ) {
			if ( isset( $bcaa_allowed_html[ $bcaa_tag ] ) ) {
				$bcaa_allowed_html[ $bcaa_tag ]['data-*'] = true;
			}
		}

		/**
		 * Filters the allowed HTML tags for artifact body output.
		 *
		 * @param array $bcaa_allowed_html Allowed HTML tags and attributes.
		 */
		$bcaa_allowed_html = apply_filters( 'botcreds_agent_artifacts_allowed_html', $bcaa_allowed_html );

		echo wp_kses( $bcaa_body, $bcaa_allowed_html );
		?>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
