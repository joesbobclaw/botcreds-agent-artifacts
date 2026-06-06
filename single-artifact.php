<?php
/**
 * Template for rendering BotCreds Agent Artifacts.
 * Uses properly enqueued scripts/styles via WordPress.
 *
 * @package BotCreds_Agent_Artifacts
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$botcreds_post_id = get_the_ID();
$botcreds_body    = get_post_meta( $botcreds_post_id, '_artifact_body',   true );
$botcreds_assets  = get_post_meta( $botcreds_post_id, '_artifact_assets', true );

// Fallback: legacy raw HTML (pre-1.0 artifacts).
if ( empty( $botcreds_body ) ) {
	$botcreds_raw_html = get_post_meta( $botcreds_post_id, 'artifact_html', true );
	if ( ! empty( $botcreds_raw_html ) ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $botcreds_raw_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
$botcreds_upload_dir    = wp_upload_dir();
$botcreds_artifacts_url = $botcreds_upload_dir['baseurl'] . '/artifacts/';

// Trusted CDN origins — scripts, styles, and fonts.
$botcreds_cdn_origins = implode( ' ', [
	'https://cdn.jsdelivr.net',
	'https://unpkg.com',
	'https://cdnjs.cloudflare.com',
	'https://esm.sh',
	'https://cdn.skypack.dev',
] );

// Per-artifact fetch allowlist — set via pragma or deploy-time meta.
$botcreds_connect_src       = get_post_meta( $botcreds_post_id, '_artifact_connect_src', true );
$botcreds_connect_src_value = "'self'";
if ( ! empty( $botcreds_connect_src ) && is_array( $botcreds_connect_src ) ) {
	$botcreds_safe_origins      = implode( ' ', array_map( 'esc_url_raw', $botcreds_connect_src ) );
	$botcreds_connect_src_value = "'self' {$botcreds_safe_origins}";
}

$botcreds_csp_parts = [
	"default-src 'self'",
	"script-src 'self' {$botcreds_artifacts_url} {$botcreds_cdn_origins}",
	"style-src 'self' 'unsafe-inline' {$botcreds_artifacts_url} {$botcreds_cdn_origins} https://fonts.googleapis.com",
	"img-src 'self' data: blob: https://pixel.wp.com https://stats.wordpress.com",
	"font-src 'self' data: https://fonts.gstatic.com {$botcreds_cdn_origins}",
	"connect-src {$botcreds_connect_src_value} https://pixel.wp.com https://stats.wordpress.com",
	"frame-src 'none'",
	"frame-ancestors 'self'",
	"form-action 'self'",
	"base-uri 'self'",
];

$botcreds_csp = implode( '; ', $botcreds_csp_parts );

/**
 * Filters the Content Security Policy header for artifact pages.
 *
 * @param string $botcreds_csp     The CSP header value.
 * @param int    $botcreds_post_id The artifact post ID.
 */
$botcreds_csp = apply_filters( 'botcreds_agent_artifacts_csp', $botcreds_csp, $botcreds_post_id );
header( "Content-Security-Policy: $botcreds_csp" );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( get_the_title() ); ?> &mdash; <?php bloginfo( 'name' ); ?></title>
	<?php
	// Output any preserved <head> content (meta tags, etc.).
	if ( ! empty( $botcreds_assets['head'] ) ) {
		$botcreds_head_content = preg_replace( '/<\/?head[^>]*>/i', '', $botcreds_assets['head'] );
		echo $botcreds_head_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		$botcreds_allowed_html = wp_kses_allowed_html( 'post' );

		$botcreds_allowed_html['canvas'] = [
			'id' => true, 'class' => true, 'width' => true, 'height' => true, 'style' => true,
		];
		$botcreds_allowed_html['svg'] = [
			'xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true,
			'class' => true, 'id' => true, 'style' => true,
		];
		$botcreds_allowed_html['path'] = [
			'd' => true, 'fill' => true, 'stroke' => true, 'class' => true,
		];
		$botcreds_allowed_html['input'] = [
			'type' => true, 'id' => true, 'class' => true, 'name' => true,
			'value' => true, 'placeholder' => true, 'disabled' => true,
			'readonly' => true, 'checked' => true, 'min' => true, 'max' => true,
			'step' => true, 'style' => true,
		];
		$botcreds_allowed_html['button'] = [
			'type' => true, 'id' => true, 'class' => true, 'disabled' => true, 'style' => true,
		];
		$botcreds_allowed_html['select'] = [
			'id' => true, 'class' => true, 'name' => true, 'style' => true,
		];
		$botcreds_allowed_html['option'] = [
			'value' => true, 'selected' => true,
		];
		$botcreds_allowed_html['label'] = [
			'for' => true, 'class' => true,
		];
		$botcreds_allowed_html['video'] = [
			'src' => true, 'controls' => true, 'autoplay' => true, 'loop' => true,
			'muted' => true, 'width' => true, 'height' => true, 'class' => true, 'id' => true,
		];
		$botcreds_allowed_html['audio'] = [
			'src' => true, 'controls' => true, 'autoplay' => true, 'loop' => true,
			'class' => true, 'id' => true,
		];

		// Allow data-* on common interactive elements.
		foreach ( [ 'div', 'span', 'button', 'input', 'a', 'canvas' ] as $botcreds_tag ) {
			if ( isset( $botcreds_allowed_html[ $botcreds_tag ] ) ) {
				$botcreds_allowed_html[ $botcreds_tag ]['data-*'] = true;
			}
		}

		/**
		 * Filters the allowed HTML tags for artifact body output.
		 *
		 * @param array $botcreds_allowed_html Allowed HTML tags and attributes.
		 */
		$botcreds_allowed_html = apply_filters( 'botcreds_agent_artifacts_allowed_html', $botcreds_allowed_html );

		echo wp_kses( $botcreds_body, $botcreds_allowed_html );
		?>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
