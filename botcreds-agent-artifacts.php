<?php
/**
 * Plugin Name:       BotCreds Agent Artifacts
 * Plugin URI:        https://botcreds.com/agent-artifacts
 * Description:       Deploy HTML/CSS/JS artifacts to WordPress via REST API. Built for AI agents.
 * Version:           1.1.0
 * Author:            BotCreds
 * Author URI:        https://botcreds.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       botcreds-agent-artifacts
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BOTCREDS_ARTIFACTS_VERSION', '1.1.0' );

class BotCreds_Agent_Artifacts {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init',               [ $this, 'register_post_type' ] );
		add_action( 'rest_api_init',      [ $this, 'register_rest_fields' ] );
		add_filter( 'template_include',   [ $this, 'template_include' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_artifact_assets' ] );
	}

	/**
	 * Register the Artifact post type with custom capabilities.
	 */
	public function register_post_type() {
		register_post_type( 'artifact', [
			'labels' => [
				'name'          => __( 'Artifacts',        'botcreds-agent-artifacts' ),
				'singular_name' => __( 'Artifact',         'botcreds-agent-artifacts' ),
				'add_new'       => __( 'Add New Artifact', 'botcreds-agent-artifacts' ),
				'edit_item'     => __( 'Edit Artifact',    'botcreds-agent-artifacts' ),
				'view_item'     => __( 'View Artifact',    'botcreds-agent-artifacts' ),
				'all_items'     => __( 'All Artifacts',    'botcreds-agent-artifacts' ),
				'not_found'     => __( 'No artifacts found.', 'botcreds-agent-artifacts' ),
			],
			'public'          => true,
			'show_in_rest'    => true,
			'rest_base'       => 'artifacts',
			'supports'        => [ 'title', 'custom-fields' ],
			'has_archive'     => true,
			'rewrite'         => [ 'slug' => 'artifacts' ],
			'menu_icon'       => 'dashicons-art',
			'capability_type' => 'artifact',
			'map_meta_cap'    => true,
		] );

		// Public meta fields
		register_post_meta( 'artifact', 'artifact_html', [
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => [ 'schema' => [ 'type' => 'string' ] ],
			'auth_callback' => fn() => current_user_can( 'edit_artifacts' ),
		] );

		register_post_meta( 'artifact', 'artifact_description', [
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
		] );

		// Internal processed-asset meta
		register_post_meta( 'artifact', '_artifact_assets', [
			'type'         => 'object',
			'single'       => true,
			'show_in_rest' => false,
		] );

		register_post_meta( 'artifact', '_artifact_body', [
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => false,
		] );
	}

	/**
	 * Hook into REST API to process HTML on save.
	 */
	public function register_rest_fields() {
		add_action( 'rest_after_insert_artifact', [ $this, 'process_artifact_html' ], 10, 2 );
	}

	/**
	 * Process the HTML after artifact is saved via REST API.
	 */
	public function process_artifact_html( $post, $request ) {
		$raw_html = get_post_meta( $post->ID, 'artifact_html', true );
		if ( empty( $raw_html ) ) return;

		$parsed = $this->parse_html( $raw_html, $post->ID );
		update_post_meta( $post->ID, '_artifact_body',   $parsed['body'] );
		update_post_meta( $post->ID, '_artifact_assets', $parsed['assets'] );
	}

	/**
	 * Parse HTML and extract scripts/styles to separate files.
	 */
	public function parse_html( $html, $post_id ) {
		$assets = [
			'styles'  => [],
			'scripts' => [],
			'head'    => '',
		];

		$upload_dir   = wp_upload_dir();
		$artifact_dir = $upload_dir['basedir'] . '/artifacts/' . $post_id;
		$artifact_url = $upload_dir['baseurl'] . '/artifacts/' . $post_id;

		if ( ! file_exists( $artifact_dir ) ) {
			wp_mkdir_p( $artifact_dir );
		}

		// Clear old files via WP_Filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		foreach ( glob( $artifact_dir . '/*' ) as $file ) {
			if ( is_file( $file ) ) wp_delete_file( $file );
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Extract <style> tags.
		$style_index = 0;
		foreach ( $xpath->query( '//style' ) as $style ) {
			$css = $style->textContent;
			if ( trim( $css ) ) {
				$filename = "style-{$style_index}.css";
				$wp_filesystem->put_contents( $artifact_dir . '/' . $filename, $css, FS_CHMOD_FILE );
				$assets['styles'][] = [
					'handle' => "artifact-{$post_id}-style-{$style_index}",
					'url'    => $artifact_url . '/' . $filename,
				];
				$style_index++;
			}
			$style->parentNode->removeChild( $style );
		}

		// Collect <script> nodes before removing.
		$script_nodes = [];
		foreach ( $xpath->query( '//script' ) as $script ) {
			$script_nodes[] = $script;
		}

		$script_index = 0;
		foreach ( $script_nodes as $script ) {
			$src = $script->getAttribute( 'src' );
			if ( $src ) {
				$assets['scripts'][] = [
					'handle'   => "artifact-{$post_id}-ext-{$script_index}",
					'url'      => esc_url_raw( $src ),
					'external' => true,
				];
			} else {
				$js = $script->textContent;
				if ( trim( $js ) ) {
					$filename = "script-{$script_index}.js";
					$wp_filesystem->put_contents( $artifact_dir . '/' . $filename, $js, FS_CHMOD_FILE );
					$assets['scripts'][] = [
						'handle'   => "artifact-{$post_id}-script-{$script_index}",
						'url'      => $artifact_url . '/' . $filename,
						'external' => false,
					];
				}
			}
			$script_index++;
			$script->parentNode->removeChild( $script );
		}

		// Extract <head> content.
		$heads = $xpath->query( '//head' );
		if ( $heads->length > 0 ) {
			$head             = $heads->item( 0 );
			$assets['head']   = $dom->saveHTML( $head );
			$head->parentNode->removeChild( $head );
		}

		// Remaining body content.
		$bodies    = $xpath->query( '//body' );
		$body_html = '';
		if ( $bodies->length > 0 ) {
			$body = $bodies->item( 0 );
			foreach ( $body->childNodes as $child ) {
				$body_html .= $dom->saveHTML( $child );
			}
		} else {
			$body_html = $dom->saveHTML();
		}

		$body_html = preg_replace( '/^<\?xml[^>]*\?>/', '', $body_html );
		$body_html = preg_replace( '/<\/?html[^>]*>/',  '', $body_html );
		$body_html = preg_replace( '/<\/?body[^>]*>/',  '', $body_html );

		return [
			'body'   => trim( $body_html ),
			'assets' => $assets,
		];
	}

	/**
	 * Enqueue artifact assets on singular artifact pages.
	 */
	public function enqueue_artifact_assets() {
		if ( ! is_singular( 'artifact' ) ) return;

		$post_id = get_the_ID();
		$assets  = get_post_meta( $post_id, '_artifact_assets', true );

		if ( empty( $assets ) ) return;

		foreach ( $assets['styles'] ?? [] as $style ) {
			wp_enqueue_style(
				$style['handle'],
				$style['url'],
				[],
				filemtime( $this->url_to_path( $style['url'] ) ) ?: BOTCREDS_ARTIFACTS_VERSION
			);
		}

		foreach ( $assets['scripts'] ?? [] as $script ) {
			wp_enqueue_script(
				$script['handle'],
				$script['url'],
				[],
				$script['external'] ? null : ( filemtime( $this->url_to_path( $script['url'] ) ) ?: BOTCREDS_ARTIFACTS_VERSION ),
				true
			);
		}
	}

	/**
	 * Convert a file URL to a filesystem path.
	 */
	private function url_to_path( $url ) {
		$upload_dir = wp_upload_dir();
		return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
	}

	/**
	 * Use the plugin's custom template for artifact singular views.
	 */
	public function template_include( $template ) {
		if ( is_singular( 'artifact' ) ) {
			$custom = locate_template( 'single-artifact.php' );
			if ( $custom ) return $custom;
			return plugin_dir_path( __FILE__ ) . 'single-artifact.php';
		}
		return $template;
	}
}

// Boot.
BotCreds_Agent_Artifacts::instance();

// Activation: grant artifact capabilities to Administrators.
register_activation_hook( __FILE__, function () {
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( [ 'edit', 'edit_others', 'publish', 'read_private', 'delete', 'delete_others', 'edit_published', 'delete_published' ] as $cap ) {
			$admin->add_cap( $cap . '_artifacts' );
		}
	}
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

/**
 * Grant artifact capabilities to an additional role.
 *
 * @param string $role_name WordPress role slug.
 */
function botcreds_agent_artifacts_grant_to_role( $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) {
		foreach ( [ 'edit', 'edit_others', 'publish', 'read_private', 'delete', 'delete_others', 'edit_published', 'delete_published' ] as $cap ) {
			$role->add_cap( $cap . '_artifacts' );
		}
	}
}
