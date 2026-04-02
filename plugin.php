<?php
/**
 * Plugin Name: Home Inference
 * Plugin URI: https://github.com/mattwiebe/wp-home-inference
 * Description: AI provider that routes inference to local models running on your home computer.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 0.1.0
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
use WordPress\HomeInference\Provider\HomeInferenceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/src/autoload.php';

// ---------------------------------------------------------------------------
// Provider registration
// ---------------------------------------------------------------------------

/**
 * Registers the Home Inference provider with the AI Client.
 *
 * @since 0.1.0
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	// Only register if an endpoint URL has been configured.
	$endpoint = get_option( 'home_inference_endpoint_url', '' );
	if ( empty( $endpoint ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( HomeInferenceProvider::class ) ) {
		return;
	}

	$registry->registerProvider( HomeInferenceProvider::class );

	// Pass the stored API key to the provider.  We handle this ourselves
	// because the connector is registered with method "none" (the Connectors
	// UI doesn't manage the key — our own setup page does).
	$api_key = get_option( 'connectors_ai_home_inference_api_key', '' );
	if ( '' !== $api_key ) {
		$registry->setProviderRequestAuthentication(
			'home-inference',
			new ApiKeyRequestAuthentication( $api_key )
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

// ---------------------------------------------------------------------------
// Connector registration
// ---------------------------------------------------------------------------

/**
 * Registers the connector entry for Home Inference so it appears in the
 * Connectors admin UI with the correct setting name for the API key.
 *
 * @since 0.1.0
 *
 * @param \WP_Connector_Registry $registry The connector registry.
 */
function register_connector( $registry ): void {
	if ( $registry->is_registered( 'home-inference' ) ) {
		$connector = $registry->unregister( 'home-inference' );
	} else {
		$connector = array(
			'name'        => __( 'Home Inference', 'wp-home-inference' ),
			'description' => __( 'Run AI inference on your own hardware using local models.', 'wp-home-inference' ),
			'type'        => 'ai_provider',
		);
	}

	$connector['authentication'] = array(
		'method' => 'none',
	);

	$registry->register( 'home-inference', $connector );
}
add_action( 'wp_connectors_init', __NAMESPACE__ . '\\register_connector' );

// ---------------------------------------------------------------------------
// Connectors UI — custom render component
// ---------------------------------------------------------------------------

/**
 * Enqueues a script module on the Connectors admin page that overrides the
 * default connector UI for Home Inference with a link to our setup page.
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
		'home-inference-connector',
		plugins_url( 'assets/connector.js', __FILE__ ),
		array(
			array(
				'id'     => '@wordpress/connectors',
				'import' => 'static',
			),
		)
	);

	wp_enqueue_script_module( 'home-inference-connector' );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_connectors_script' );

/**
 * Provides data to the connector script module.
 *
 * @since 0.1.0
 *
 * @param array $data Existing data.
 * @return array Data with setup URL and connection status.
 */
function connector_script_module_data( array $data ): array {
	$endpoint_url = get_option( 'home_inference_endpoint_url', '' );
	$api_key      = get_option( 'connectors_ai_home_inference_api_key', '' );

	$data['setupUrl']    = admin_url( 'options-general.php?page=home-inference' );
	$data['isConnected'] = ! empty( $endpoint_url ) && ! empty( $api_key );

	return $data;
}
add_filter( 'script_module_data_home-inference-connector', __NAMESPACE__ . '\\connector_script_module_data' );

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

/**
 * Registers settings for the Home Inference provider.
 *
 * @since 0.1.0
 */
function register_settings(): void {
	register_setting(
		'home_inference',
		'home_inference_endpoint_url',
		array(
			'type'              => 'string',
			'label'             => __( 'Home Inference Endpoint URL', 'wp-home-inference' ),
			'description'       => __( 'The URL of your local inference proxy (e.g. http://your-home-ip:13531).', 'wp-home-inference' ),
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'esc_url_raw',
		)
	);

	register_setting(
		'home_inference',
		'connectors_ai_home_inference_api_key',
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
		'home_inference',
		'home_inference_model_id',
		array(
			'type'              => 'string',
			'label'             => __( 'Model', 'wp-home-inference' ),
			'description'       => __( 'The model to use from the Home Inference proxy.', 'wp-home-inference' ),
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_model_id',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_settings', 20 );

/**
 * Sanitizes the stored API key without altering its internal characters.
 *
 * Secrets should be treated as opaque values, so this only trims surrounding
 * whitespace and rejects non-scalar input.
 *
 * @since 0.1.0
 *
 * @param mixed $value Raw submitted API key value.
 * @return string Sanitized API key.
 */
function sanitize_api_key( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	return trim( (string) $value );
}

/**
 * Sanitizes the selected model ID.
 *
 * If the current proxy configuration is available, the model must exist in the
 * live /v1/models response. Otherwise the raw trimmed value is kept so the
 * setting can be saved together with new connection details.
 *
 * @since 0.1.0
 *
 * @param mixed $value Raw submitted model ID.
 * @return string Sanitized model ID.
 */
function sanitize_model_id( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$model_id = trim( (string) $value );
	if ( '' === $model_id ) {
		return '';
	}

	$connection = get_submitted_or_saved_connection_details();
	if ( '' === $connection['endpoint_url'] || '' === $connection['api_key'] ) {
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
		'home_inference_model_id',
		'home_inference_model_id_invalid',
		__( 'The selected model is not available from the Home Inference proxy.', 'wp-home-inference' )
	);

	return '';
}

/**
 * Gets the endpoint URL and API key from the current request or saved options.
 *
 * @since 0.1.0
 *
 * @return array{endpoint_url: string, api_key: string}
 */
function get_submitted_or_saved_connection_details(): array {
	$endpoint_url = get_option( 'home_inference_endpoint_url', '' );
	$api_key      = get_option( 'connectors_ai_home_inference_api_key', '' );

	if ( isset( $_POST['home_inference_endpoint_url'] ) ) {
		$endpoint_url = esc_url_raw( wp_unslash( (string) $_POST['home_inference_endpoint_url'] ) );
	}

	if ( isset( $_POST['connectors_ai_home_inference_api_key'] ) ) {
		$api_key = sanitize_api_key( wp_unslash( $_POST['connectors_ai_home_inference_api_key'] ) );
	}

	return array(
		'endpoint_url' => $endpoint_url,
		'api_key'      => $api_key,
	);
}

/**
 * Fetches available models from the Home Inference proxy's /v1/models endpoint.
 *
 * @since 0.1.0
 *
 * @param string $endpoint_url Proxy base URL.
 * @param string $api_key      Proxy API key.
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
				/* translators: %d: HTTP status code from the proxy. */
				__( 'The Home Inference proxy returned HTTP %d when loading models.', 'wp-home-inference' ),
				$status_code
			)
		);
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
		return new \WP_Error(
			'home_inference_models_invalid_response',
			__( 'The Home Inference proxy returned an invalid models response.', 'wp-home-inference' )
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
			__( 'No models were returned by the Home Inference proxy.', 'wp-home-inference' )
		);
	}

	return $models;
}

// ---------------------------------------------------------------------------
// Admin page
// ---------------------------------------------------------------------------

/**
 * Adds the Home Inference setup page under Settings.
 *
 * @since 0.1.0
 */
function add_admin_menu(): void {
	add_options_page(
		__( 'Home Inference', 'wp-home-inference' ),
		__( 'Home Inference', 'wp-home-inference' ),
		'manage_options',
		'home-inference',
		__NAMESPACE__ . '\\render_admin_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_menu' );

/**
 * Renders the admin setup page.
 *
 * @since 0.1.0
 */
function render_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$endpoint_url  = get_option( 'home_inference_endpoint_url', '' );
	$api_key       = get_option( 'connectors_ai_home_inference_api_key', '' );
	$model_id      = get_option( 'home_inference_model_id', '' );
	$is_configured = ! empty( $endpoint_url ) && ! empty( $api_key );
	$models        = $is_configured ? fetch_proxy_models( $endpoint_url, $api_key ) : new \WP_Error( 'home_inference_not_configured', '' );

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
		<h1><?php esc_html_e( 'Home Inference', 'wp-home-inference' ); ?></h1>

		<?php if ( ! $is_configured ) : ?>

		<div class="card" style="max-width: 720px;">
			<h2><?php esc_html_e( 'Setup', 'wp-home-inference' ); ?></h2>
			<p><?php esc_html_e( 'Connect this WordPress site to a local inference server (Ollama, llama.cpp, LM Studio, vLLM, etc.) running on your home computer.', 'wp-home-inference' ); ?></p>

			<h3><?php esc_html_e( 'Step 1: Start your local inference server', 'wp-home-inference' ); ?></h3>
			<p><?php esc_html_e( 'If you haven\'t already, install and start a local inference server. For example, with Ollama:', 'wp-home-inference' ); ?></p>
			<?php render_copyable_command( 'ollama serve' ); ?>

			<h3><?php esc_html_e( 'Step 2: Install Tailscale', 'wp-home-inference' ); ?></h3>
			<p><?php esc_html_e( 'The proxy uses Tailscale Funnel to securely expose your local server to the internet — no port forwarding needed.', 'wp-home-inference' ); ?></p>
			<p>
				<?php
				printf(
					/* translators: %s: Tailscale download URL */
					esc_html__( 'Install Tailscale from %s and run `tailscale up` to sign in.', 'wp-home-inference' ),
					'<a href="https://tailscale.com/download" target="_blank">tailscale.com/download</a>'
				);
				?>
			</p>

			<h3><?php esc_html_e( 'Step 3: Start the Home Inference proxy', 'wp-home-inference' ); ?></h3>
			<p><?php esc_html_e( 'On your home computer, copy the local/ directory from this plugin and run:', 'wp-home-inference' ); ?></p>
			<?php render_copyable_command( 'node server.mjs' ); ?>
			<p class="description"><?php esc_html_e( 'The proxy will auto-detect your inference backend, start a Tailscale Funnel, and display the public URL and API key.', 'wp-home-inference' ); ?></p>

			<h3><?php esc_html_e( 'Step 4: Enter the connection details below', 'wp-home-inference' ); ?></h3>
			<p><?php esc_html_e( 'Copy the Endpoint URL and API Key shown by the proxy into the form below.', 'wp-home-inference' ); ?></p>
		</div>
		<br>

		<?php endif; ?>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'home_inference' );
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="home_inference_endpoint_url"><?php esc_html_e( 'Endpoint URL', 'wp-home-inference' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="home_inference_endpoint_url"
							name="home_inference_endpoint_url"
							value="<?php echo esc_attr( $endpoint_url ); ?>"
							class="regular-text"
							placeholder="https://your-machine.tail1234.ts.net"
						>
						<p class="description"><?php esc_html_e( 'The URL of your Home Inference proxy.', 'wp-home-inference' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="connectors_ai_home_inference_api_key"><?php esc_html_e( 'API Key', 'wp-home-inference' ); ?></label>
					</th>
					<td>
						<input
							type="password"
							id="connectors_ai_home_inference_api_key"
							name="connectors_ai_home_inference_api_key"
							value="<?php echo esc_attr( $api_key ); ?>"
							class="regular-text"
							autocomplete="off"
						>
						<p class="description"><?php esc_html_e( 'The API key shown when you started the proxy.', 'wp-home-inference' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="home_inference_model_id"><?php esc_html_e( 'Model', 'wp-home-inference' ); ?></label>
					</th>
					<td>
						<select
							id="home_inference_model_id"
							name="home_inference_model_id"
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
							<p class="description"><?php esc_html_e( 'Loaded live from the proxy /v1/models endpoint. The selected model will be the one exposed by this connector.', 'wp-home-inference' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<?php if ( $is_configured ) : ?>
		<div class="card" style="max-width: 720px;">
			<h2><?php esc_html_e( 'Server Info', 'wp-home-inference' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Your local proxy should be started with:', 'wp-home-inference' ); ?></p>
			<?php render_copyable_command( 'node server.mjs' ); ?>
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
