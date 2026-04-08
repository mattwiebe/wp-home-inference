<?php
/**
 * Plugin Name: Home Inference
 * Plugin URI: https://github.com/mattwiebe/wp-home-inference
 * Description: AI providers for routing inference to your own machine or to an Actual Computer endpoint.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 0.1.5
 * Author: Matt
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: wp-home-inference
 *
 * @package WordPress\HomeInference
 */

declare(strict_types=1);

namespace WordPress\HomeInference;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\HomeInference\Provider\ActualComputerProvider;
use WordPress\HomeInference\Provider\HomeInferenceProvider;

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
		'home-inference' => array(
			'slug'                  => 'home-inference',
			'provider_class'        => HomeInferenceProvider::class,
			'name'                  => __( 'Home Inference', 'wp-home-inference' ),
			'description'           => __( 'Run AI inference on your own hardware using local models.', 'wp-home-inference' ),
			'endpoint_option'       => 'home_inference_endpoint_url',
			'api_key_option'        => 'connectors_ai_home_inference_api_key',
			'model_option'          => 'home_inference_model_id',
			'settings_group'        => 'home_inference',
			'admin_page'            => 'home-inference',
			'show_endpoint_field'   => true,
			'connector_auth_method' => 'none',
			'use_custom_connector_ui' => true,
			'endpoint_label'        => __( 'Endpoint URL', 'wp-home-inference' ),
			'endpoint_description'  => __( 'The URL of your Home Inference proxy.', 'wp-home-inference' ),
			'endpoint_placeholder'  => 'https://your-machine.tail1234.ts.net',
			'api_key_description'   => __( 'The API key shown when you started the proxy.', 'wp-home-inference' ),
			'model_description'     => __( 'Loaded live from the proxy /v1/models endpoint. The selected model will be the one exposed by this connector.', 'wp-home-inference' ),
			'model_invalid_message' => __( 'The selected model is not available from the Home Inference proxy.', 'wp-home-inference' ),
			'setup'                 => array(
				'heading'      => __( 'Setup', 'wp-home-inference' ),
				'introduction' => __( 'Connect this WordPress site to a local inference server (Ollama, llama.cpp, LM Studio, vLLM, etc.) running on your home computer.', 'wp-home-inference' ),
				'steps'        => array(
					array(
						'heading'  => __( 'Step 1: Start your local inference server', 'wp-home-inference' ),
						'body'     => __( 'If you haven\'t already, install and start a local inference server. For example, with Ollama:', 'wp-home-inference' ),
						'commands' => array( 'ollama serve' ),
					),
					array(
						'heading' => __( 'Step 2: Install Tailscale', 'wp-home-inference' ),
						'body'    => __( 'The proxy uses Tailscale Funnel to securely expose your local server to the internet, without port forwarding.', 'wp-home-inference' ),
						'html'    => sprintf(
							/* translators: %s: Tailscale download URL. */
							wp_kses_post( __( 'Install Tailscale from %s and run `tailscale up` to sign in.', 'wp-home-inference' ) ),
							'<a href="https://tailscale.com/download" target="_blank" rel="noreferrer noopener">tailscale.com/download</a>'
						),
					),
					array(
						'heading'  => __( 'Step 3: Install and start the Home Inference proxy', 'wp-home-inference' ),
						'body'     => __( 'On your home computer, install the published CLI and run the setup command:', 'wp-home-inference' ),
						'commands' => array( 'npm install -g @mattwiebe/wp-home-inference && wphi init', 'wphi up', 'wphi install' ),
						'notes'    => array(
							__( 'This configures the proxy, auto-detects your local backend, optionally starts Tailscale Funnel, and saves the connection details for future runs.', 'wp-home-inference' ),
							__( 'If you are working from this repo instead of a global npm install, the equivalent commands are `npm run init`, `npm run up`, and `npm run service:install`.', 'wp-home-inference' ),
							__( 'On macOS, the LaunchAgent can be managed later with `wphi start`, `wphi stop`, and `wphi uninstall`.', 'wp-home-inference' ),
						),
					),
					array(
						'heading' => __( 'Step 4: Enter the connection details below', 'wp-home-inference' ),
						'body'    => __( 'Copy the Endpoint URL and API Key shown by the proxy into the form below.', 'wp-home-inference' ),
					),
				),
			),
			'info_card'             => array(
				'heading'     => __( 'Server Info', 'wp-home-inference' ),
				'description' => __( 'Your local proxy should be started with:', 'wp-home-inference' ),
				'commands'    => array( 'wphi up' ),
				'notes'       => array(
					__( 'Local development from this repo can also use `npm run up`.', 'wp-home-inference' ),
				),
			),
		),
		'actual-computer' => array(
			'slug'                  => 'actual-computer',
			'provider_class'        => ActualComputerProvider::class,
			'name'                  => __( 'Actual Computer', 'wp-home-inference' ),
			'description'           => __( 'Connect WordPress AI to an Actual Computer endpoint.', 'wp-home-inference' ),
			'endpoint_option'       => 'actual_computer_endpoint_url',
			'api_key_option'        => 'connectors_ai_actual_computer_api_key',
			'model_option'          => 'actual_computer_model_id',
			'settings_group'        => 'actual_computer',
			'admin_page'            => 'actual-computer',
			'fixed_endpoint_url'    => 'https://api.actual.inc',
			'show_endpoint_field'   => false,
			'connector_auth_method' => 'api_key',
			'credentials_url'       => 'https://actual.inc/console/api',
			'use_custom_connector_ui' => false,
			'connector_logo_url'    => plugins_url( 'assets/actual-computer.jpg', __FILE__ ),
			'endpoint_label'        => __( 'Base URL', 'wp-home-inference' ),
			'endpoint_description'  => __( 'Actual Computer uses the fixed API base URL `https://api.actual.inc/v1`.', 'wp-home-inference' ),
			'endpoint_placeholder'  => 'https://api.actual.inc',
			'api_key_description'   => __( 'The bearer token issued by Actual Computer.', 'wp-home-inference' ),
			'model_description'     => __( 'Loaded live from the Actual Computer /v1/models endpoint. The selected model will be the one exposed by this connector.', 'wp-home-inference' ),
			'model_invalid_message' => __( 'The selected model is not available from the Actual Computer endpoint.', 'wp-home-inference' ),
			'setup'                 => array(
				'heading'      => __( 'Setup', 'wp-home-inference' ),
				'introduction' => __( 'Connect this WordPress site to Actual Computer with your API key.', 'wp-home-inference' ),
				'steps'        => array(
					array(
						'heading' => __( 'Step 1: Open the API console', 'wp-home-inference' ),
						'body'    => __( 'Sign in to Actual Computer and create or copy your API key from the console.', 'wp-home-inference' ),
						'html'    => '<a href="https://actual.inc/console/api" target="_blank" rel="noreferrer noopener">https://actual.inc/console/api</a>',
					),
					array(
						'heading' => __( 'Step 2: Paste your API key below', 'wp-home-inference' ),
						'body'    => __( 'The connector uses the fixed Actual Computer endpoint `https://api.actual.inc/v1` automatically.', 'wp-home-inference' ),
					),
					array(
						'heading' => __( 'Step 3: Save and choose a model', 'wp-home-inference' ),
						'body'    => __( 'After saving your API key, choose the Actual Computer model you want WordPress to expose.', 'wp-home-inference' ),
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
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

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
function allow_home_inference_safe_remote_requests( array $args, string $url ): array {
	if ( should_allow_managed_provider_request( $url ) ) {
		$args['reject_unsafe_urls'] = false;
	}

	return $args;
}
add_filter( 'http_request_args', __NAMESPACE__ . '\\allow_home_inference_safe_remote_requests', 10, 2 );

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
 * Whether a URL targets the configured Home Inference endpoint.
 *
 * @since 0.1.0
 *
 * @param string $url Request URL.
 * @return bool
 */
function should_allow_home_inference_request( string $url ): bool {
	return should_allow_provider_request( $url, 'home-inference' );
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
	return should_allow_provider_request( $url, 'actual-computer' );
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
add_action( 'wp_connectors_init', __NAMESPACE__ . '\\register_connector' );

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
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_connectors_script' );

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
add_filter( 'script_module_data_managed-connectors', __NAMESPACE__ . '\\connector_script_module_data' );

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

/**
 * Registers settings for all managed providers.
 *
 * @since 0.1.0
 */
function register_settings(): void {
	$home_inference = get_provider_definition( 'home-inference' );
	$actual         = get_provider_definition( 'actual-computer' );

	register_setting(
		$home_inference['settings_group'],
		$home_inference['endpoint_option'],
		array(
			'type'              => 'string',
			'label'             => __( 'Home Inference Endpoint URL', 'wp-home-inference' ),
			'description'       => __( 'The URL of your local inference proxy (for example `http://your-home-ip:13531`).', 'wp-home-inference' ),
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'esc_url_raw',
		)
	);

	register_setting(
		$home_inference['settings_group'],
		$home_inference['api_key_option'],
		array(
			'type'              => 'string',
			'label'             => __( 'API Key', 'wp-home-inference' ),
			'description'       => __( 'The shared secret shown when you start the local proxy.', 'wp-home-inference' ),
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_api_key',
		)
	);

	register_setting(
		$home_inference['settings_group'],
		$home_inference['model_option'],
		array(
			'type'              => 'string',
			'label'             => __( 'Model', 'wp-home-inference' ),
			'description'       => __( 'The model to use from the Home Inference proxy.', 'wp-home-inference' ),
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_model_id',
		)
	);

	register_setting(
		$actual['settings_group'],
		$actual['api_key_option'],
		array(
			'type'              => 'string',
			'label'             => __( 'API Key', 'wp-home-inference' ),
			'description'       => __( 'The bearer token issued by Actual Computer.', 'wp-home-inference' ),
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_api_key',
		)
	);

	register_setting(
		$actual['settings_group'],
		$actual['model_option'],
		array(
			'type'              => 'string',
			'label'             => __( 'Model', 'wp-home-inference' ),
			'description'       => __( 'The model to use from Actual Computer.', 'wp-home-inference' ),
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_actual_computer_model_id',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_settings', 20 );

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

	return trim( (string) $value );
}

/**
 * Sanitizes the selected Home Inference model ID.
 *
 * @since 0.1.0
 *
 * @param mixed $value Raw submitted model ID.
 * @return string
 */
function sanitize_model_id( $value ): string {
	return sanitize_provider_model_id( $value, 'home-inference' );
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
	return sanitize_provider_model_id( $value, 'actual-computer' );
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

	$model_id = trim( (string) $value );
	if ( '' === $model_id ) {
		return '';
	}

	$provider   = get_provider_definition( $slug );
	$connection = get_submitted_or_saved_connection_details_for_provider( $slug );

	if ( empty( $provider ) || '' === $connection['endpoint_url'] || '' === $connection['api_key'] ) {
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
 * Gets the Home Inference connection details from the current request or saved
 * options.
 *
 * @since 0.1.0
 *
 * @return array{endpoint_url: string, api_key: string}
 */
function get_submitted_or_saved_connection_details(): array {
	return get_submitted_or_saved_connection_details_for_provider( 'home-inference' );
}

/**
 * Gets the Actual Computer connection details from the current request or saved
 * options.
 *
 * @since 0.1.6
 *
 * @return array{endpoint_url: string, api_key: string}
 */
function get_submitted_or_saved_actual_computer_connection_details(): array {
	return get_submitted_or_saved_connection_details_for_provider( 'actual-computer' );
}

/**
 * Gets connection details from the current request or saved options.
 *
 * @since 0.1.6
 *
 * @param string $slug Provider slug.
 * @return array{endpoint_url: string, api_key: string}
 */
function get_submitted_or_saved_connection_details_for_provider( string $slug ): array {
	$provider = get_provider_definition( $slug );
	if ( empty( $provider ) ) {
		return array(
			'endpoint_url' => '',
			'api_key'      => '',
		);
	}

	$endpoint_url = get_provider_endpoint_url( $provider );
	$api_key      = (string) get_option( $provider['api_key_option'], '' );

	if ( ! empty( $provider['show_endpoint_field'] ) && isset( $_POST[ $provider['endpoint_option'] ] ) ) {
		$endpoint_url = esc_url_raw( wp_unslash( (string) $_POST[ $provider['endpoint_option'] ] ) );
	}

	if ( isset( $_POST[ $provider['api_key_option'] ] ) ) {
		$api_key = sanitize_api_key( wp_unslash( $_POST[ $provider['api_key_option'] ] ) );
	}

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

	if ( '' === $endpoint_url || '' === $api_key ) {
		return new \WP_Error(
			'home_inference_missing_connection',
			__( 'Save the endpoint URL and API key before loading models.', 'wp-home-inference' )
		);
	}

	$response = wp_remote_get(
		$endpoint_url . '/v1/models',
		array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Accept'        => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	if ( $status_code < 200 || $status_code >= 300 ) {
		return new \WP_Error(
			'home_inference_models_http_error',
			sprintf(
				/* translators: %d: HTTP status code from the provider. */
				__( 'The provider returned HTTP %d when loading models.', 'wp-home-inference' ),
				$status_code
			)
		);
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
		return new \WP_Error(
			'home_inference_models_invalid_response',
			__( 'The provider returned an invalid models response.', 'wp-home-inference' )
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
			'home_inference_models_empty',
			__( 'No models were returned by the provider.', 'wp-home-inference' )
		);
	}

	return $models;
}

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
add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_menu' );

/**
 * Backward-compatible wrapper for the original Home Inference settings page.
 *
 * @since 0.1.0
 */
function render_admin_page(): void {
	render_provider_admin_page( 'home-inference' );
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
	$is_configured = '' !== trim( $api_key );
	$models        = $is_configured ? fetch_proxy_models( $endpoint_url, $api_key ) : new \WP_Error( 'provider_not_configured', '' );

	?>
	<style>
		.home-inference-cmd {
			position: relative;
			background: #f0f0f0;
			padding: 12px 60px 12px 12px;
			overflow-x: auto;
			margin: 0;
			font-size: 13px;
		}
		.home-inference-copy {
			position: absolute;
			top: 6px;
			right: 6px;
			cursor: pointer;
			background: none;
			border: 1px solid #8c8f94;
			border-radius: 2px;
			padding: 2px 8px;
			font-size: 12px;
			color: #2271b1;
			line-height: 1.6;
		}
		.home-inference-copy:hover {
			border-color: #2271b1;
			color: #135e96;
		}
	</style>
	<div class="wrap">
		<h1><?php echo esc_html( $provider['name'] ); ?></h1>

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
						<label for="<?php echo esc_attr( $provider['api_key_option'] ); ?>"><?php esc_html_e( 'API Key', 'wp-home-inference' ); ?></label>
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
						<label for="<?php echo esc_attr( $provider['model_option'] ); ?>"><?php esc_html_e( 'Model', 'wp-home-inference' ); ?></label>
					</th>
					<td>
						<select
							id="<?php echo esc_attr( $provider['model_option'] ); ?>"
							name="<?php echo esc_attr( $provider['model_option'] ); ?>"
							class="regular-text"
							<?php disabled( ! $is_configured || is_wp_error( $models ) ); ?>
						>
							<option value=""><?php esc_html_e( 'Automatic (first compatible model)', 'wp-home-inference' ); ?></option>
							<?php if ( ! is_wp_error( $models ) ) : ?>
								<?php foreach ( $models as $model ) : ?>
									<option value="<?php echo esc_attr( $model['id'] ); ?>" <?php selected( $model_id, $model['id'] ); ?>>
										<?php echo esc_html( $model['id'] ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
						<?php if ( ! $is_configured ) : ?>
							<p class="description"><?php esc_html_e( 'Save the endpoint URL and API key first to load available models.', 'wp-home-inference' ); ?></p>
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
	<script>
	document.querySelectorAll( '.home-inference-copy' ).forEach( function( btn ) {
		btn.addEventListener( 'click', function() {
			var text = this.parentNode.querySelector( 'code' ).textContent;
			navigator.clipboard.writeText( text ).then( function() {
				var orig = btn.textContent;
				btn.textContent = <?php echo wp_json_encode( __( 'Copied!', 'wp-home-inference' ) ); ?>;
				setTimeout( function() { btn.textContent = orig; }, 2000 );
			} );
		} );
	} );
	</script>
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
	<pre class="home-inference-cmd"><code><?php echo esc_html( $command ); ?></code><button type="button" class="home-inference-copy"><?php esc_html_e( 'Copy', 'wp-home-inference' ); ?></button></pre>
	<?php
}
