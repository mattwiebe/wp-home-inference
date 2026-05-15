<?php
/**
 * Plugin Name: MW Local AI Connector
 * Plugin URI: https://github.com/mattwiebe/ai-connector-for-local-ai
 * Description: AI providers for routing AI requests to your own machine.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 0.6.2
 * Author: Matt Wiebe
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: mw-local-ai-connector
 *
 * @package Mattwiebe\LocalAiConnector
 */

declare(strict_types=1);

namespace Mattwiebe\LocalAiConnector;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use Mattwiebe\LocalAiConnector\Provider\ActualComputerProvider;
use Mattwiebe\LocalAiConnector\Provider\LocalAiProvider;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Returns connector/provider definitions used across registration, settings,
 * model discovery, and admin UI rendering.
 *
 * @since 0.1.6
 *
 * @return array<string, array{
 *     slug: string,
 *     provider_class: class-string,
 *     name: string,
 *     description: string,
 *     endpoint_option: string,
 *     api_key_option: string,
 *     model_option: string,
 *     settings_group: string,
 *     admin_page: string,
 *     fixed_endpoint_url?: string,
 *     show_endpoint_field?: bool,
 *     connector_auth_method?: string,
 *     credentials_url?: string,
 *     use_custom_connector_ui?: bool,
 *     connector_logo_url?: string,
 *     endpoint_label: string,
 *     endpoint_description: string,
 *     endpoint_placeholder: string,
 *     api_key_description: string,
 *     model_description: string,
 *     model_invalid_message: string,
 *     setup: array<string, mixed>,
 *     info_card: array<string, mixed>
 * }>
 */
function provider_definitions(): array {
	static $definitions = null;

	if ( null !== $definitions ) {
		return $definitions;
	}

	$definitions = array(
		'mwlai' => array(
			'slug'                  => 'mwlai',
			'provider_class'        => LocalAiProvider::class,
			'name'                  => __( 'Local AI', 'mw-local-ai-connector' ),
			'description'           => __( 'Run AI inference on your own hardware using local models.', 'mw-local-ai-connector' ),
			'endpoint_option'       => 'mwlai_endpoint_url',
			'api_key_option'        => 'mwlai_api_key',
			'model_option'          => 'mwlai_model_id',
			'settings_group'        => 'mwlai',
			'admin_page'            => 'mwlai',
			'show_endpoint_field'   => true,
			'api_key_required'      => false,
			'connector_auth_method' => 'none',
			'use_custom_connector_ui' => true,
			'endpoint_label'        => __( 'Endpoint URL', 'mw-local-ai-connector' ),
			'endpoint_description'  => __( 'The URL of your Local AI proxy.', 'mw-local-ai-connector' ),
			'endpoint_placeholder'  => 'https://your-proxy.example.com',
			'api_key_description'   => __( 'Required for Tailscale Funnel or Cloudflare Tunnel. Leave blank for local-only proxy mode.', 'mw-local-ai-connector' ),
			'model_description'     => __( 'Loaded live from the proxy /v1/models endpoint. The default model is preferred for text generation unless an ability-specific model below overrides it.', 'mw-local-ai-connector' ),
			'model_invalid_message' => __( 'The selected model is not available from the Local AI proxy.', 'mw-local-ai-connector' ),
			'setup'                 => array(
				'heading'      => __( 'Setup', 'mw-local-ai-connector' ),
				'introduction' => __( 'Connect this WordPress site to one or more local inference servers (Ollama, llama.cpp, LM Studio, vLLM, etc.) running on your home computer.', 'mw-local-ai-connector' ),
				'steps'        => array(
					array(
						'heading'  => __( 'Step 1: Start your local inference server', 'mw-local-ai-connector' ),
						'body'     => __( 'If you haven\'t already, install and start a local inference server. For example, with Ollama:', 'mw-local-ai-connector' ),
						'commands' => array( 'ollama serve' ),
					),
					array(
						'heading' => __( 'Step 2: Choose local or tunnel access', 'mw-local-ai-connector' ),
						'body'    => __( 'The proxy can run locally only, or expose your local providers through Tailscale Funnel or Cloudflare Tunnel.', 'mw-local-ai-connector' ),
						'html'    => sprintf(
							/* translators: 1: Tailscale download URL. 2: tailscale up command. */
							__( 'For Tailscale Funnel, install Tailscale from %1$s and run %2$s to sign in.', 'mw-local-ai-connector' ),
							'<a href="https://tailscale.com/download" target="_blank" rel="noreferrer noopener">tailscale.com/download</a>',
							'<code>tailscale up</code>'
						),
					),
					array(
						'heading'  => __( 'Step 3: Install and start the Local AI proxy', 'mw-local-ai-connector' ),
						'body'     => __( 'On your home computer, install the published CLI and run the setup command:', 'mw-local-ai-connector' ),
						'commands' => array( 'npm install -g sloproxy && sloproxy init', 'sloproxy up', 'sloproxy install' ),
						'notes'    => array(
							__( 'This configures the proxy, scans for local providers, optionally starts a tunnel, and saves the connection details for future runs.', 'mw-local-ai-connector' ),
						),
					),
					array(
						'heading' => __( 'Step 4: Enter the connection details below', 'mw-local-ai-connector' ),
						'body'    => __( 'Copy the Endpoint URL shown by the proxy into the form below. For Tailscale Funnel or Cloudflare Tunnel, also copy the API Key.', 'mw-local-ai-connector' ),
					),
				),
			),
			'info_card'             => array(
				'heading'     => __( 'Server Info', 'mw-local-ai-connector' ),
				'description' => __( 'Your local proxy should be started with:', 'mw-local-ai-connector' ),
				'commands'    => array( 'sloproxy up' ),
				'notes'       => array(),
			),
		),
		'mwlai-actual-computer' => array(
			'slug'                  => 'mwlai-actual-computer',
			'provider_class'        => ActualComputerProvider::class,
			'name'                  => __( 'Actual Computer', 'mw-local-ai-connector' ),
			'description'           => __( 'Connect WordPress AI to an Actual Computer endpoint.', 'mw-local-ai-connector' ),
			'endpoint_option'       => 'mwlai_actual_computer_endpoint_url',
			'api_key_option'        => 'mwlai_actual_computer_api_key',
			'model_option'          => 'mwlai_actual_computer_model_id',
			'settings_group'        => 'mwlai_actual_computer',
			'admin_page'            => 'mwlai-actual-computer',
			'fixed_endpoint_url'    => 'https://api.actual.inc',
			'show_endpoint_field'   => false,
			'api_key_required'      => true,
			'connector_auth_method' => 'api_key',
			'credentials_url'       => 'https://actual.inc/console/api',
			'use_custom_connector_ui' => false,
			'connector_logo_url'    => plugins_url( 'assets/actual-computer.jpg', __FILE__ ),
			'endpoint_label'        => __( 'Base URL', 'mw-local-ai-connector' ),
			'endpoint_description'  => __( 'Actual Computer uses the fixed API base URL `https://api.actual.inc/v1`.', 'mw-local-ai-connector' ),
			'endpoint_placeholder'  => 'https://api.actual.inc',
			'api_key_description'   => __( 'The bearer token issued by Actual Computer.', 'mw-local-ai-connector' ),
			'model_description'     => __( 'Loaded live from the Actual Computer /v1/models endpoint. The selected model will be the one exposed by this connector.', 'mw-local-ai-connector' ),
			'model_invalid_message' => __( 'The selected model is not available from the Actual Computer endpoint.', 'mw-local-ai-connector' ),
			'setup'                 => array(
				'heading'      => __( 'Setup', 'mw-local-ai-connector' ),
				'introduction' => __( 'Connect this WordPress site to Actual Computer with your API key.', 'mw-local-ai-connector' ),
				'steps'        => array(
					array(
						'heading' => __( 'Step 1: Open the API console', 'mw-local-ai-connector' ),
						'body'    => __( 'Sign in to Actual Computer and create or copy your API key from the console.', 'mw-local-ai-connector' ),
						'html'    => '<a href="https://actual.inc/console/api" target="_blank" rel="noreferrer noopener">https://actual.inc/console/api</a>',
					),
					array(
						'heading' => __( 'Step 2: Paste your API key below', 'mw-local-ai-connector' ),
						'body'    => __( 'The connector uses the fixed Actual Computer endpoint `https://api.actual.inc/v1` automatically.', 'mw-local-ai-connector' ),
					),
					array(
						'heading' => __( 'Step 3: Save and choose a model', 'mw-local-ai-connector' ),
						'body'    => __( 'After saving your API key, choose the Actual Computer model you want WordPress to expose.', 'mw-local-ai-connector' ),
					),
				),
			),
			'info_card'             => array(),
		),
	);

	return $definitions;
}

/**
 * Returns a single provider definition.
 *
 * @since 0.1.6
 *
 * @param string $slug Provider slug.
 * @return array<string, mixed>
 */
function get_provider_definition( string $slug ): array {
	$definitions = provider_definitions();

	return $definitions[ $slug ] ?? array();
}

// ---------------------------------------------------------------------------
// Provider registration
// ---------------------------------------------------------------------------

/**
 * Registers configured providers with the AI Client.
 *
 * @since 0.1.0
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	foreach ( provider_definitions() as $provider ) {
		if ( ! $registry->hasProvider( $provider['provider_class'] ) ) {
			$registry->registerProvider( $provider['provider_class'] );
		}

		$api_key = trim( (string) get_option( $provider['api_key_option'], '' ) );
		if ( '' !== $api_key ) {
			$registry->setProviderRequestAuthentication(
				$provider['slug'],
				new ApiKeyRequestAuthentication( $api_key )
			);
		}
	}
}
add_action( 'init', __NAMESPACE__ . '\register_provider', 5 );

/**
 * Prepends the selected Local AI model to WordPress AI's default text model
 * preferences.
 *
 * Per-ability model settings can still override this default through the
 * WordPress AI developer model configuration options.
 *
 * @since 0.7.0
 *
 * @param array<int, array{string, string}> $preferred_models Preferred provider/model pairs.
 * @return array<int, array{string, string}>
 */
function prepend_default_local_ai_text_model( array $preferred_models ): array {
	$model_id = trim( (string) get_option( 'mwlai_model_id', '' ) );
	if ( '' === $model_id ) {
		return $preferred_models;
	}

	$filtered_models = array();
	foreach ( $preferred_models as $preferred_model ) {
		if (
			! is_array( $preferred_model )
			|| count( $preferred_model ) < 2
			|| 'mwlai' !== (string) $preferred_model[0]
			|| $model_id !== (string) $preferred_model[1]
		) {
			$filtered_models[] = $preferred_model;
		}
	}

	array_unshift( $filtered_models, array( 'mwlai', $model_id ) );

	return $filtered_models;
}
add_filter( 'wpai_preferred_text_models', __NAMESPACE__ . '\prepend_default_local_ai_text_model' );

/**
 * Allows configured provider requests to bypass wp_safe_remote_request() URL
 * validation when the target matches a saved provider endpoint.
 *
 * @since 0.1.0
 *
 * @param array<string, mixed> $args Parsed HTTP request args.
 * @param string               $url  Request URL.
 * @return array<string, mixed>
 */
function allow_local_ai_safe_remote_requests( array $args, string $url ): array {
	if ( should_allow_managed_provider_request( $url ) ) {
		$args['reject_unsafe_urls'] = false;
	}

	return $args;
}
add_filter( 'http_request_args', __NAMESPACE__ . '\allow_local_ai_safe_remote_requests', 10, 2 );

/**
 * Whether a URL targets any configured managed provider endpoint.
 *
 * @since 0.1.6
 *
 * @param string $url Request URL.
 * @return bool
 */
function should_allow_managed_provider_request( string $url ): bool {
	foreach ( provider_definitions() as $provider ) {
		if ( should_allow_provider_request( $url, $provider['slug'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether a URL targets the configured Local AI endpoint.
 *
 * @since 0.1.0
 *
 * @param string $url Request URL.
 * @return bool
 */
function should_allow_local_ai_request( string $url ): bool {
	return should_allow_provider_request( $url, 'mwlai' );
}

/**
 * Whether a URL targets the configured Actual Computer endpoint.
 *
 * @since 0.1.6
 *
 * @param string $url Request URL.
 * @return bool
 */
function should_allow_actual_computer_request( string $url ): bool {
	return should_allow_provider_request( $url, 'mwlai-actual-computer' );
}

/**
 * Whether a URL targets a specific provider endpoint.
 *
 * @since 0.1.6
 *
 * @param string $url  Request URL.
 * @param string $slug Provider slug.
 * @return bool
 */
function should_allow_provider_request( string $url, string $slug ): bool {
	$provider = get_provider_definition( $slug );
	if ( empty( $provider ) ) {
		return false;
	}

	$endpoint_url = get_provider_endpoint_url( $provider );
	if ( '' === $endpoint_url ) {
		return false;
	}

	$normalized_endpoint = trailingslashit( untrailingslashit( $endpoint_url ) );
	$normalized_url      = trailingslashit( untrailingslashit( $url ) );

	return str_starts_with( $normalized_url, $normalized_endpoint );
}

// ---------------------------------------------------------------------------
// Connector registration
// ---------------------------------------------------------------------------

/**
 * Registers the connector entries for managed providers.
 *
 * @since 0.1.0
 *
 * @param \WP_Connector_Registry $registry The connector registry.
 */
function register_connector( $registry ): void {
	foreach ( provider_definitions() as $provider ) {
		$slug = $provider['slug'];

		if ( $registry->is_registered( $slug ) ) {
			$connector = $registry->unregister( $slug );
		} else {
			$connector = array(
				'name'        => $provider['name'],
				'description' => $provider['description'],
				'type'        => 'ai_provider',
			);
		}

		$connector['authentication'] = array(
			'method' => $provider['connector_auth_method'],
		);

		if ( 'api_key' === $provider['connector_auth_method'] && ! empty( $provider['credentials_url'] ) ) {
			$connector['authentication']['credentials_url'] = $provider['credentials_url'];
			$connector['authentication']['setting_name']    = $provider['api_key_option'];
		}

		if ( ! empty( $provider['connector_logo_url'] ) ) {
			$connector['logo_url'] = $provider['connector_logo_url'];
		}

		$registry->register( $slug, $connector );
	}
}
add_action( 'wp_connectors_init', __NAMESPACE__ . '\register_connector' );

// ---------------------------------------------------------------------------
// Connectors UI — custom render component
// ---------------------------------------------------------------------------

/**
 * Enqueues the custom connectors UI script on the Connectors admin page.
 *
 * @since 0.1.0
 *
 * @param string $hook_suffix The current admin page hook.
 */
function enqueue_connectors_script( string $hook_suffix ): void {
	$screen = get_current_screen();
	if ( ! $screen || 'options-connectors' !== $screen->id ) {
		return;
	}

	wp_register_script_module(
		'managed-connectors',
		plugins_url( 'assets/connector.js', __FILE__ ),
		array(
			array(
				'id'     => '@wordpress/connectors',
				'import' => 'static',
			),
		)
	);

	wp_enqueue_script_module( 'managed-connectors' );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_connectors_script' );

/**
 * Provides setup URLs and connection status to the connector script module.
 *
 * @since 0.1.6
 *
 * @param array<string, mixed> $data Existing data.
 * @return array<string, mixed>
 */
function connector_script_module_data( array $data ): array {
	$data['connectors'] = array();

	foreach ( provider_definitions() as $provider ) {
		if ( empty( $provider['use_custom_connector_ui'] ) ) {
			continue;
		}

		$endpoint_url = get_provider_endpoint_url( $provider );
		$api_key      = trim( (string) get_option( $provider['api_key_option'], '' ) );

		$data['connectors'][ $provider['slug'] ] = array(
			'name'        => $provider['name'],
			'description' => $provider['description'],
			'setupUrl'    => admin_url( 'options-general.php?page=' . $provider['admin_page'] ),
			'isConnected' => '' !== $api_key && '' !== $endpoint_url,
		);
	}

	return $data;
}
add_filter( 'script_module_data_managed-connectors', __NAMESPACE__ . '\connector_script_module_data' );

// ---------------------------------------------------------------------------
// Admin settings page assets
// ---------------------------------------------------------------------------

/**
 * Returns the admin page hook suffixes used by managed providers.
 *
 * @since 0.3.0
 *
 * @return list<string>
 */
function get_admin_page_hooks(): array {
	$hooks = array();

	foreach ( provider_definitions() as $provider ) {
		$hooks[] = 'settings_page_' . $provider['admin_page'];
	}

	return $hooks;
}

/**
 * Enqueues styles and scripts for managed provider settings pages.
 *
 * @since 0.3.0
 *
 * @param string $hook_suffix The current admin page hook.
 */
function enqueue_admin_settings_assets( string $hook_suffix ): void {
	if ( ! in_array( $hook_suffix, get_admin_page_hooks(), true ) ) {
		return;
	}

	$plugin_file = __FILE__;
	$version     = plugin_version();

	wp_enqueue_style(
		'mwlai-connector-admin',
		plugins_url( 'assets/admin-settings.css', $plugin_file ),
		array(),
		$version
	);

	wp_enqueue_script(
		'mwlai-connector-admin',
		plugins_url( 'assets/admin-settings.js', $plugin_file ),
		array(),
		$version,
		array( 'in_footer' => true )
	);

	wp_localize_script(
		'mwlai-connector-admin',
		'mwlaiAdminSettings',
		array(
			'copiedLabel' => __( 'Copied!', 'mw-local-ai-connector' ),
			'failedLabel' => __( 'Copy failed', 'mw-local-ai-connector' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_admin_settings_assets' );

/**
 * Returns the plugin version from its header.
 *
 * @since 0.3.0
 *
 * @return string
 */
function plugin_version(): string {
	static $version = null;

	if ( null !== $version ) {
		return $version;
	}

	if ( ! function_exists( 'get_file_data' ) ) {
		$version = '';
		return $version;
	}

	$data    = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
	$version = isset( $data['Version'] ) ? (string) $data['Version'] : '';

	return $version;
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

/**
 * Registers settings for all managed providers.
 *
 * @since 0.1.0
 */
function register_settings(): void {
	$local_ai = get_provider_definition( 'mwlai' );
	$actual   = get_provider_definition( 'mwlai-actual-computer' );

	register_setting(
		$local_ai['settings_group'],
		$local_ai['endpoint_option'],
		array(
			'type'              => 'string',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_endpoint_url',
			'label'             => __( 'Local AI Endpoint URL', 'mw-local-ai-connector' ),
			'description'       => __( 'The URL of your local inference proxy (for example `http://your-home-ip:13531`).', 'mw-local-ai-connector' ),
			'default'           => '',
			'show_in_rest'      => true,
		)
	);

	register_setting(
		$local_ai['settings_group'],
		$local_ai['api_key_option'],
		array(
			'type'              => 'string',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_api_key',
			'label'             => __( 'API Key', 'mw-local-ai-connector' ),
			'description'       => __( 'The shared secret shown when you start a tunneled local proxy. Leave blank for local-only mode.', 'mw-local-ai-connector' ),
			'default'           => '',
			'show_in_rest'      => true,
		)
	);

	register_setting(
		$local_ai['settings_group'],
		$local_ai['model_option'],
		array(
			'type'              => 'string',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_local_ai_model_id',
			'label'             => __( 'Default Model', 'mw-local-ai-connector' ),
			'description'       => __( 'The default model to prefer from the Local AI proxy.', 'mw-local-ai-connector' ),
			'default'           => '',
			'show_in_rest'      => true,
		)
	);

	register_setting(
		$actual['settings_group'],
		$actual['api_key_option'],
		array(
			'type'              => 'string',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_api_key',
			'label'             => __( 'API Key', 'mw-local-ai-connector' ),
			'description'       => __( 'The bearer token issued by Actual Computer.', 'mw-local-ai-connector' ),
			'default'           => '',
			'show_in_rest'      => true,
		)
	);

	register_setting(
		$actual['settings_group'],
		$actual['model_option'],
		array(
			'type'              => 'string',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_actual_computer_model_id',
			'label'             => __( 'Model', 'mw-local-ai-connector' ),
			'description'       => __( 'The model to use from Actual Computer.', 'mw-local-ai-connector' ),
			'default'           => '',
			'show_in_rest'      => true,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register_settings', 20 );

/**
 * Returns whether the current request is the Local AI settings admin screen.
 *
 * @since 0.7.0
 *
 * @return bool Whether this is the Local AI settings admin screen.
 */
function is_local_ai_settings_admin_screen(): bool {
	if ( ! is_admin() ) {
		return false;
	}

	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( $screen && 'settings_page_mwlai' === $screen->id ) {
			return true;
		}
	}

	$page = '';
	if ( isset( $_GET['page'] ) ) {
		$raw_page = wp_unslash( $_GET['page'] );
		$page     = is_scalar( $raw_page ) ? sanitize_key( $raw_page ) : '';
	}
	if ( 'mwlai' !== $page ) {
		return false;
	}

	global $pagenow;
	return ! isset( $pagenow ) || 'options-general.php' === $pagenow;
}

/**
 * Caches WordPress AI feature metadata as features are registered.
 *
 * @since 0.7.0
 *
 * @param object $registry WordPress AI feature registry.
 */
function cache_wordpress_ai_feature_metadata( $registry ): void {
	if ( ! is_local_ai_settings_admin_screen() ) {
		return;
	}

	if ( ! is_object( $registry ) || ! method_exists( $registry, 'get_all_features' ) ) {
		return;
	}

	$features = $registry->get_all_features();
	if ( ! is_array( $features ) ) {
		return;
	}

	$metadata = array();
	foreach ( $features as $feature ) {
		if ( ! is_object( $feature ) || ! method_exists( $feature, 'get_id' ) ) {
			continue;
		}

		$feature_id = (string) $feature::get_id();
		if ( '' === $feature_id ) {
			continue;
		}

		$metadata[ $feature_id ] = array(
			'label'      => method_exists( $feature, 'get_label' ) ? (string) $feature->get_label() : $feature_id,
			'capability' => method_exists( $feature, 'get_capability' ) ? (string) $feature->get_capability() : 'text_generation',
		);
	}

	$GLOBALS['mwlai_wordpress_ai_feature_metadata'] = $metadata;
}
add_action( 'wpai_register_features', __NAMESPACE__ . '\cache_wordpress_ai_feature_metadata', PHP_INT_MAX );

/**
 * Returns cached WordPress AI feature metadata.
 *
 * @since 0.7.0
 *
 * @return array<string, array{label?: string, capability?: string}>
 */
function get_wordpress_ai_feature_metadata(): array {
	$metadata = $GLOBALS['mwlai_wordpress_ai_feature_metadata'] ?? array();
	if ( ! is_array( $metadata ) ) {
		$metadata = array();
	}

	/**
	 * Filters cached WordPress AI feature metadata.
	 *
	 * @since 0.7.0
	 *
	 * @param array<string, array{label?: string, capability?: string}> $metadata Feature metadata keyed by feature ID.
	 */
	return (array) apply_filters( 'mwlai_wordpress_ai_feature_metadata', $metadata );
}

/**
 * Returns whether WordPress AI registered a developer model setting for a feature.
 *
 * @since 0.7.0
 *
 * @param string $feature_id WordPress AI feature ID.
 * @return bool Whether the developer model config setting is registered.
 */
function is_wordpress_ai_developer_model_config_registered( string $feature_id ): bool {
	global $wp_registered_settings;

	return isset( $wp_registered_settings[ "wpai_feature_{$feature_id}_field_developer" ] );
}

/**
 * Returns the WordPress AI feature ID controlled by a registered ability.
 *
 * WordPress AI stores developer model overrides per feature, not per ability.
 * Ability Explorer can list abilities directly; this derives the matching
 * feature dynamically for abilities whose `ai/{feature-id}` slug matches a
 * registered WordPress AI feature.
 *
 * @since 0.7.0
 *
 * @param string $ability_slug Ability slug, for example `ai/title-generation`.
 * @return string Feature ID, or an empty string when no developer model config is supported.
 */
function get_wordpress_ai_feature_id_for_ability( string $ability_slug ): string {
	$feature_id = '';

	if ( 0 === strpos( $ability_slug, 'ai/' ) ) {
		$candidate = substr( $ability_slug, 3 );
		if ( '' !== $candidate && sanitize_key( $candidate ) === $candidate ) {
			$metadata = get_wordpress_ai_feature_metadata();
			if ( isset( $metadata[ $candidate ] ) || is_wordpress_ai_developer_model_config_registered( $candidate ) ) {
				$feature_id = $candidate;
			}
		}
	}

	/**
	 * Filters the WordPress AI feature ID associated with an ability.
	 *
	 * @since 0.7.0
	 *
	 * @param string $feature_id   Feature ID, or an empty string when unsupported.
	 * @param string $ability_slug Ability slug.
	 */
	return (string) apply_filters( 'mwlai_wordpress_ai_feature_id_for_ability', $feature_id, $ability_slug );
}

/**
 * Returns whether a JSON schema has media/image-oriented model markers.
 *
 * @since 0.7.0
 *
 * @param array<string, mixed> $schema Ability JSON schema.
 * @return bool Whether the schema appears to require image/media model support.
 */
function wordpress_ai_ability_schema_has_media_markers( array $schema ): bool {
	$media_keys = array(
		'attachment_id',
		'image',
		'images',
		'image_id',
		'image_ids',
		'image_meta',
		'image_url',
		'mime_type',
		'reference',
	);

	foreach ( $schema as $key => $value ) {
		$key = strtolower( (string) $key );
		if ( in_array( $key, $media_keys, true ) ) {
			return true;
		}

		if ( is_array( $value ) && wordpress_ai_ability_schema_has_media_markers( $value ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns whether a WordPress AI feature can use Local AI text model choices.
 *
 * @since 0.7.0
 *
 * @param string $feature_id WordPress AI feature ID.
 * @param object $ability    Registered WordPress Ability object.
 * @return bool Whether to show Local AI text model preferences for the feature.
 */
function wordpress_ai_feature_supports_local_ai_text_models( string $feature_id, $ability ): bool {
	$metadata   = get_wordpress_ai_feature_metadata();
	$capability = $metadata[ $feature_id ]['capability'] ?? null;

	if ( is_string( $capability ) && '' !== $capability ) {
		$supports = 'text_generation' === $capability;
	} elseif ( is_object( $ability ) && method_exists( $ability, 'get_input_schema' ) && method_exists( $ability, 'get_output_schema' ) ) {
		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();
		$supports      = is_array( $input_schema ) && is_array( $output_schema )
			&& ! wordpress_ai_ability_schema_has_media_markers( $input_schema )
			&& ! wordpress_ai_ability_schema_has_media_markers( $output_schema );
	} else {
		$supports = true;
	}

	/**
	 * Filters whether a WordPress AI feature should show Local AI text model preferences.
	 *
	 * @since 0.7.0
	 *
	 * @param bool   $supports   Whether Local AI text model preferences are supported.
	 * @param string $feature_id WordPress AI feature ID.
	 * @param object $ability    Registered WordPress Ability object.
	 */
	return (bool) apply_filters( 'mwlai_wordpress_ai_feature_supports_local_ai_text_models', $supports, $feature_id, $ability );
}

/**
 * Returns AI ability targets that support WordPress AI developer model config.
 *
 * @since 0.7.0
 *
 * @return list<array{
 *     ability_slug: string,
 *     feature_id: string,
 *     label: string,
 *     description: string,
 *     category: string
 * }>
 */
function get_wordpress_ai_ability_model_targets(): array {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array();
	}

	$abilities = wp_get_abilities();
	if ( ! is_array( $abilities ) || empty( $abilities ) ) {
		return array();
	}

	$targets = array();
	foreach ( $abilities as $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) ) {
			continue;
		}

		$ability_slug = (string) $ability->get_name();
		$feature_id   = get_wordpress_ai_feature_id_for_ability( $ability_slug );
		if ( '' === $feature_id ) {
			continue;
		}

		if ( ! wordpress_ai_feature_supports_local_ai_text_models( $feature_id, $ability ) ) {
			continue;
		}

		$targets[] = array(
			'ability_slug' => $ability_slug,
			'feature_id'   => $feature_id,
			'label'        => method_exists( $ability, 'get_label' ) ? (string) $ability->get_label() : $ability_slug,
			'description'  => method_exists( $ability, 'get_description' ) ? (string) $ability->get_description() : '',
			'category'     => method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : '',
		);
	}

	usort(
		$targets,
		static function ( array $a, array $b ): int {
			return strcasecmp( $a['label'], $b['label'] );
		}
	);

	return $targets;
}

/**
 * Sanitizes submitted model preferences for WordPress AI feature overrides.
 *
 * @since 0.7.0
 *
 * @param mixed        $value           Raw submitted model preferences.
 * @param list<string> $valid_model_ids Model IDs returned by the Local AI proxy.
 * @return array<string, array{provider: string, model: string}|string> Feature IDs keyed to selected provider/model configs. Empty string clears a preference.
 */
function sanitize_ability_model_preferences( $value, array $valid_model_ids ): array {
	if ( ! is_array( $value ) ) {
		return array();
	}

	$preferences = array();
	foreach ( $value as $feature_id => $model_id ) {
		if ( ! is_scalar( $feature_id ) || ! is_scalar( $model_id ) ) {
			continue;
		}

		$raw_feature_id = trim( (string) $feature_id );
		$feature_id     = sanitize_key( $raw_feature_id );
		if ( '' === $feature_id || $feature_id !== $raw_feature_id ) {
			continue;
		}

		$model_id = sanitize_text_field( trim( (string) $model_id ) );
		if ( '' === $model_id ) {
			$preferences[ $feature_id ] = '';
			continue;
		}

		$model_parts = explode( '|', $model_id, 2 );
		if ( 2 !== count( $model_parts ) ) {
			continue;
		}

		$provider_slug = sanitize_key( $model_parts[0] );
		$model_id      = sanitize_text_field( trim( $model_parts[1] ) );
		if ( '' === $provider_slug || '' === $model_id ) {
			continue;
		}

		if ( 'mwlai' === $provider_slug && in_array( $model_id, $valid_model_ids, true ) ) {
			$preferences[ $feature_id ] = array(
				'provider' => 'mwlai',
				'model'    => $model_id,
			);
			continue;
		}

		if ( 'mwlai' !== $provider_slug ) {
			$preferences[ $feature_id ] = array(
				'provider' => $provider_slug,
				'model'    => $model_id,
			);
		}
	}

	return $preferences;
}

/**
 * Sanitizes a stored endpoint URL.
 *
 * @since 0.3.0
 *
 * @param mixed $value Raw submitted value.
 * @return string
 */
function sanitize_endpoint_url( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	return esc_url_raw( trim( (string) $value ) );
}

/**
 * Sanitizes the stored API key without altering its internal characters.
 *
 * @since 0.1.0
 *
 * @param mixed $value Raw submitted API key value.
 * @return string
 */
function sanitize_api_key( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	return sanitize_text_field( trim( (string) $value ) );
}

/**
 * Sanitizes the selected Local AI model ID.
 *
 * @since 0.1.0
 *
 * @param mixed $value Raw submitted model ID.
 * @return string
 */
function sanitize_local_ai_model_id( $value ): string {
	return sanitize_provider_model_id( $value, 'mwlai' );
}

/**
 * Sanitizes the selected Actual Computer model ID.
 *
 * @since 0.1.6
 *
 * @param mixed $value Raw submitted model ID.
 * @return string
 */
function sanitize_actual_computer_model_id( $value ): string {
	return sanitize_provider_model_id( $value, 'mwlai-actual-computer' );
}

/**
 * Sanitizes the selected model ID for a managed provider.
 *
 * @since 0.1.6
 *
 * @param mixed  $value Raw submitted model ID.
 * @param string $slug  Provider slug.
 * @return string
 */
function sanitize_provider_model_id( $value, string $slug ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$model_id = sanitize_text_field( trim( (string) $value ) );
	if ( '' === $model_id ) {
		return '';
	}

	$provider   = get_provider_definition( $slug );
	$connection = get_saved_connection_details_for_provider( $slug );

	if ( empty( $provider ) || '' === $connection['endpoint_url'] || ( ! empty( $provider['api_key_required'] ) && '' === $connection['api_key'] ) ) {
		return $model_id;
	}

	$models = fetch_proxy_models( $connection['endpoint_url'], $connection['api_key'] );
	if ( is_wp_error( $models ) ) {
		return $model_id;
	}

	if ( in_array( $model_id, wp_list_pluck( $models, 'id' ), true ) ) {
		return $model_id;
	}

	add_settings_error(
		$provider['model_option'],
		$provider['model_option'] . '_invalid',
		$provider['model_invalid_message']
	);

	return '';
}

/**
 * Gets the saved Local AI connection details.
 *
 * @since 0.1.0
 *
 * @return array{endpoint_url: string, api_key: string}
 */
function get_submitted_or_saved_local_ai_connection_details(): array {
	return get_saved_connection_details_for_provider( 'mwlai' );
}

/**
 * Gets the saved Actual Computer connection details.
 *
 * @since 0.1.6
 *
 * @return array{endpoint_url: string, api_key: string}
 */
function get_submitted_or_saved_actual_computer_connection_details(): array {
	return get_saved_connection_details_for_provider( 'mwlai-actual-computer' );
}

/**
 * Gets saved connection details for a provider.
 *
 * @since 0.1.6
 *
 * @param string $slug Provider slug.
 * @return array{endpoint_url: string, api_key: string}
 */
function get_saved_connection_details_for_provider( string $slug ): array {
	$provider = get_provider_definition( $slug );
	if ( empty( $provider ) ) {
		return array(
			'endpoint_url' => '',
			'api_key'      => '',
		);
	}

	$endpoint_url = get_provider_endpoint_url( $provider );
	$api_key      = (string) get_option( $provider['api_key_option'], '' );

	return array(
		'endpoint_url' => $endpoint_url,
		'api_key'      => $api_key,
	);
}

/**
 * Gets the effective endpoint URL for a provider definition.
 *
 * @since 0.1.6
 *
 * @param array<string, mixed> $provider Provider definition.
 * @return string
 */
function get_provider_endpoint_url( array $provider ): string {
	if ( ! empty( $provider['fixed_endpoint_url'] ) ) {
		return untrailingslashit( (string) $provider['fixed_endpoint_url'] );
	}

	return trim( (string) get_option( $provider['endpoint_option'], '' ) );
}

/**
 * Fetches available models from a provider's OpenAI-compatible `/v1/models`
 * endpoint.
 *
 * @since 0.1.0
 *
 * @param string $endpoint_url Provider base URL.
 * @param string $api_key      Provider API key.
 * @return array<int, array{id: string, owned_by?: string}>|\WP_Error
 */
function fetch_proxy_models( string $endpoint_url, string $api_key ) {
	$endpoint_url = untrailingslashit( trim( $endpoint_url ) );
	$api_key      = trim( $api_key );

	if ( '' === $endpoint_url ) {
		return new \WP_Error(
			'mwlai_missing_connection',
			__( 'Save the endpoint URL before loading models.', 'mw-local-ai-connector' )
		);
	}

	$headers = array(
		'Accept' => 'application/json',
	);
	if ( '' !== $api_key ) {
		$headers['Authorization'] = 'Bearer ' . $api_key;
	}

	$response = wp_remote_get(
		$endpoint_url . '/v1/models',
		array(
			'timeout' => 10,
			'headers' => $headers,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	if ( $status_code < 200 || $status_code >= 300 ) {
		return new \WP_Error(
			'mwlai_models_http_error',
			sprintf(
				/* translators: %d: HTTP status code from the provider. */
				__( 'The provider returned HTTP %d when loading models.', 'mw-local-ai-connector' ),
				$status_code
			)
		);
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
		return new \WP_Error(
			'mwlai_models_invalid_response',
			__( 'The provider returned an invalid models response.', 'mw-local-ai-connector' )
		);
	}

	$models = array();
	foreach ( $data['data'] as $model ) {
		if ( ! is_array( $model ) || empty( $model['id'] ) || ! is_string( $model['id'] ) ) {
			continue;
		}

		$models[] = array(
			'id'       => $model['id'],
			'owned_by' => isset( $model['owned_by'] ) && is_string( $model['owned_by'] ) ? $model['owned_by'] : '',
		);
	}

	if ( empty( $models ) ) {
		return new \WP_Error(
			'mwlai_models_empty',
			__( 'No models were returned by the provider.', 'mw-local-ai-connector' )
		);
	}

	return $models;
}

/**
 * Handles saving Local AI model preferences for WordPress AI abilities.
 *
 * @since 0.7.0
 */
function handle_save_ability_model_preferences(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage Local AI settings.', 'mw-local-ai-connector' ) );
	}

	check_admin_referer( 'mwlai_ability_model_preferences' );

	$provider      = get_provider_definition( 'mwlai' );
	$connection    = get_saved_connection_details_for_provider( 'mwlai' );
	$redirect_url  = add_query_arg(
		array(
			'page'             => $provider['admin_page'],
			'settings-updated' => 'true',
		),
		admin_url( 'options-general.php' )
	);
	$models        = fetch_proxy_models( $connection['endpoint_url'], $connection['api_key'] );
	$setting_group = 'mwlai_ability_model_preferences';

	if ( is_wp_error( $models ) ) {
		add_settings_error(
			$setting_group,
			'mwlai_ability_models_unavailable',
			$models->get_error_message()
		);
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	$valid_model_ids = wp_list_pluck( $models, 'id' );
	$raw_preferences = isset( $_POST['mwlai_ability_model_preferences'] )
		? wp_unslash( $_POST['mwlai_ability_model_preferences'] )
		: array();
	$preferences     = sanitize_ability_model_preferences( $raw_preferences, $valid_model_ids );
	$targets         = get_wordpress_ai_ability_model_targets();

	foreach ( $targets as $target ) {
		$feature_id  = $target['feature_id'];
		$option_name = 'wpai_feature_' . $feature_id . '_field_developer';
		$preference  = $preferences[ $feature_id ] ?? null;
		$config      = get_option( $option_name, array() );
		$provider    = is_array( $config ) ? (string) ( $config['provider'] ?? '' ) : '';

		if ( '' === $preference ) {
			delete_option( $option_name );
			continue;
		}

		if ( ! is_array( $preference ) || empty( $preference['provider'] ) || empty( $preference['model'] ) ) {
			continue;
		}

		if ( 'mwlai' !== $preference['provider'] && $provider === $preference['provider'] ) {
			continue;
		}

		update_option( $option_name, $preference );
	}

	add_settings_error(
		$setting_group,
		'mwlai_ability_models_saved',
		__( 'Saved WordPress AI ability model preferences.', 'mw-local-ai-connector' ),
		'updated'
	);
	set_transient( 'settings_errors', get_settings_errors(), 30 );

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_mwlai_save_ability_model_preferences', __NAMESPACE__ . '\handle_save_ability_model_preferences' );

// ---------------------------------------------------------------------------
// Admin pages
// ---------------------------------------------------------------------------

/**
 * Adds provider setup pages under Settings.
 *
 * @since 0.1.0
 */
function add_admin_menu(): void {
	foreach ( provider_definitions() as $provider ) {
		add_options_page(
			$provider['name'],
			$provider['name'],
			'manage_options',
			$provider['admin_page'],
			static function () use ( $provider ): void {
				render_provider_admin_page( $provider['slug'] );
			}
		);
	}
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_admin_menu' );

/**
 * Renders the Local AI settings page.
 *
 * @since 0.1.0
 */
function render_local_ai_admin_page(): void {
	render_provider_admin_page( 'mwlai' );
}

/**
 * Renders the settings page for a managed provider.
 *
 * @since 0.1.6
 *
 * @param string $slug Provider slug.
 */
function render_provider_admin_page( string $slug ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$provider = get_provider_definition( $slug );
	if ( empty( $provider ) ) {
		return;
	}

	$endpoint_url  = get_provider_endpoint_url( $provider );
	$api_key       = (string) get_option( $provider['api_key_option'], '' );
	$model_id      = (string) get_option( $provider['model_option'], '' );
	$is_configured = ! empty( $provider['api_key_required'] )
		? '' !== trim( $api_key )
		: '' !== trim( $endpoint_url );
	$models        = $is_configured ? fetch_proxy_models( $endpoint_url, $api_key ) : new \WP_Error( 'provider_not_configured', '' );

	?>
	<div class="wrap">
		<h1><?php echo esc_html( $provider['name'] ); ?></h1>
		<?php settings_errors(); ?>

		<?php if ( ! $is_configured ) : ?>
			<div class="card" style="max-width: 720px;">
				<h2><?php echo esc_html( $provider['setup']['heading'] ); ?></h2>
				<p><?php echo esc_html( $provider['setup']['introduction'] ); ?></p>
				<?php foreach ( $provider['setup']['steps'] as $step ) : ?>
					<h3><?php echo esc_html( $step['heading'] ); ?></h3>
					<?php if ( ! empty( $step['body'] ) ) : ?>
						<p><?php echo esc_html( $step['body'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $step['html'] ) ) : ?>
						<p><?php echo wp_kses_post( $step['html'] ); ?></p>
					<?php endif; ?>
					<?php foreach ( $step['commands'] ?? array() as $command ) : ?>
						<?php render_copyable_command( $command ); ?>
					<?php endforeach; ?>
					<?php foreach ( $step['notes'] ?? array() as $note ) : ?>
						<p class="description"><?php echo esc_html( $note ); ?></p>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
			<br>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( $provider['settings_group'] ); ?>
			<table class="form-table" role="presentation">
				<?php if ( ! empty( $provider['show_endpoint_field'] ) ) : ?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $provider['endpoint_option'] ); ?>"><?php echo esc_html( $provider['endpoint_label'] ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="<?php echo esc_attr( $provider['endpoint_option'] ); ?>"
								name="<?php echo esc_attr( $provider['endpoint_option'] ); ?>"
								value="<?php echo esc_attr( $endpoint_url ); ?>"
								class="regular-text"
								placeholder="<?php echo esc_attr( $provider['endpoint_placeholder'] ); ?>"
							>
							<p class="description"><?php echo esc_html( $provider['endpoint_description'] ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( $provider['api_key_option'] ); ?>"><?php esc_html_e( 'API Key', 'mw-local-ai-connector' ); ?></label>
					</th>
					<td>
						<input
							type="password"
							id="<?php echo esc_attr( $provider['api_key_option'] ); ?>"
							name="<?php echo esc_attr( $provider['api_key_option'] ); ?>"
							value="<?php echo esc_attr( $api_key ); ?>"
							class="regular-text"
							autocomplete="off"
						>
						<p class="description"><?php echo esc_html( $provider['api_key_description'] ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( $provider['model_option'] ); ?>">
							<?php echo esc_html( 'mwlai' === $slug ? __( 'Default model', 'mw-local-ai-connector' ) : __( 'Model', 'mw-local-ai-connector' ) ); ?>
						</label>
					</th>
					<td>
						<select
							id="<?php echo esc_attr( $provider['model_option'] ); ?>"
							name="<?php echo esc_attr( $provider['model_option'] ); ?>"
							class="regular-text"
							<?php disabled( ! $is_configured || is_wp_error( $models ) ); ?>
						>
							<option value=""><?php esc_html_e( 'Automatic (WordPress AI defaults)', 'mw-local-ai-connector' ); ?></option>
							<?php if ( ! is_wp_error( $models ) ) : ?>
								<?php foreach ( $models as $model ) : ?>
									<option value="<?php echo esc_attr( $model['id'] ); ?>" <?php selected( $model_id, $model['id'] ); ?>>
										<?php echo esc_html( $model['id'] ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
						<?php if ( ! $is_configured ) : ?>
							<p class="description"><?php echo esc_html( ! empty( $provider['api_key_required'] ) ? __( 'Save the API key first to load available models.', 'mw-local-ai-connector' ) : __( 'Save the endpoint URL first to load available models.', 'mw-local-ai-connector' ) ); ?></p>
						<?php elseif ( is_wp_error( $models ) ) : ?>
							<p class="description"><?php echo esc_html( $models->get_error_message() ); ?></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html( $provider['model_description'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<?php if ( 'mwlai' === $slug && $is_configured && ! is_wp_error( $models ) ) : ?>
			<?php render_ability_model_preferences( $models ); ?>
		<?php endif; ?>

		<?php if ( $is_configured && ! empty( $provider['info_card'] ) ) : ?>
			<div class="card" style="max-width: 720px;">
				<h2><?php echo esc_html( $provider['info_card']['heading'] ); ?></h2>
				<p class="description"><?php echo esc_html( $provider['info_card']['description'] ); ?></p>
				<?php foreach ( $provider['info_card']['commands'] as $command ) : ?>
					<?php render_copyable_command( $command ); ?>
				<?php endforeach; ?>
				<?php foreach ( $provider['info_card']['notes'] as $note ) : ?>
					<p class="description"><?php echo esc_html( $note ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renders per-ability model preferences for WordPress AI.
 *
 * @since 0.7.0
 *
 * @param list<array{id: string, owned_by?: string}> $models Available Local AI models.
 */
function render_ability_model_preferences( array $models ): void {
	$targets = get_wordpress_ai_ability_model_targets();

	?>
	<hr>
	<h2><?php esc_html_e( 'WordPress AI ability model preferences', 'mw-local-ai-connector' ); ?></h2>
	<p>
		<?php esc_html_e( 'Choose which Local AI model WordPress AI should prefer for each model-configurable ability. Leave an ability on Automatic to use the default model preference order.', 'mw-local-ai-connector' ); ?>
	</p>

	<?php if ( ! function_exists( 'wp_get_abilities' ) ) : ?>
		<p class="description"><?php esc_html_e( 'The WordPress Abilities API is not available, so ability preferences cannot be shown yet.', 'mw-local-ai-connector' ); ?></p>
		<?php return; ?>
	<?php endif; ?>

	<?php if ( empty( $targets ) ) : ?>
		<p class="description"><?php esc_html_e( 'No WordPress AI abilities with developer model configuration are currently registered.', 'mw-local-ai-connector' ); ?></p>
		<?php return; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="mwlai_save_ability_model_preferences">
		<?php wp_nonce_field( 'mwlai_ability_model_preferences' ); ?>

		<table class="widefat striped mwlai-ability-models">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Ability', 'mw-local-ai-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Category', 'mw-local-ai-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Preferred local model', 'mw-local-ai-connector' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $targets as $target ) : ?>
					<?php
					$option_name = 'wpai_feature_' . $target['feature_id'] . '_field_developer';
					$config      = get_option( $option_name, array() );
					$provider    = is_array( $config ) ? (string) ( $config['provider'] ?? '' ) : '';
					$model_id    = is_array( $config ) ? (string) ( $config['model'] ?? '' ) : '';
					$selected    = '' !== $provider && '' !== $model_id ? $provider . '|' . $model_id : '';
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $target['label'] ); ?></strong>
							<code><?php echo esc_html( $target['ability_slug'] ); ?></code>
							<?php if ( '' !== $target['description'] ) : ?>
								<p class="description"><?php echo esc_html( $target['description'] ); ?></p>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $target['category'] ?: __( 'Other', 'mw-local-ai-connector' ) ); ?></td>
						<td>
							<select name="mwlai_ability_model_preferences[<?php echo esc_attr( $target['feature_id'] ); ?>]">
								<option value=""><?php esc_html_e( 'Automatic', 'mw-local-ai-connector' ); ?></option>
								<?php if ( '' !== $provider && 'mwlai' !== $provider && '' !== $model_id ) : ?>
									<option value="<?php echo esc_attr( $provider . '|' . $model_id ); ?>" <?php selected( $selected, $provider . '|' . $model_id ); ?>>
										<?php
										printf(
											/* translators: 1: provider slug. 2: model ID. */
											esc_html__( 'Existing: %1$s / %2$s', 'mw-local-ai-connector' ),
											esc_html( $provider ),
											esc_html( $model_id )
										);
										?>
									</option>
								<?php endif; ?>
								<?php foreach ( $models as $model ) : ?>
									<option value="<?php echo esc_attr( 'mwlai|' . $model['id'] ); ?>" <?php selected( $selected, 'mwlai|' . $model['id'] ); ?>>
										<?php echo esc_html( $model['id'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save ability model preferences', 'mw-local-ai-connector' ) ); ?>
	</form>
	<?php
}

/**
 * Renders a command block with a copy-to-clipboard button.
 *
 * @since 0.1.0
 *
 * @param string $command The command text to display.
 */
function render_copyable_command( string $command ): void {
	?>
	<pre class="mwlai-cmd"><code><?php echo esc_html( $command ); ?></code><button type="button" class="mwlai-copy"><?php esc_html_e( 'Copy', 'mw-local-ai-connector' ); ?></button></pre>
	<?php
}
