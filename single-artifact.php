<?php
/**
 * Template for rendering BotCreds Agent Artifacts.
 * Uses properly enqueued scripts/styles via WordPress.
 *
 * @package BotCreds_Agent_Artifacts
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$post_id = get_the_ID();
$body    = get_post_meta( $post_id, '_artifact_body',   true );
$assets  = get_post_meta( $post_id, '_artifact_assets', true );

// Fallback: legacy raw HTML (pre-1.0 artifacts).
if ( empty( $body ) ) {
	$raw_html = get_post_meta( $post_id, 'artifact_html', true );
	if ( ! empty( $raw_html ) ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
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

$csp_parts = [
	"default-src 'self'",
	"script-src 'self' {$artifacts_url}",
	"style-src 'self' 'unsafe-inline' {$artifacts_url}",
	"img-src 'self' data: blob:",
	"font-src 'self' data:",
	"connect-src 'self'",
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
