<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}

$GLOBALS['home_inference_test_options']        = array();
$GLOBALS['home_inference_test_remote_response'] = null;
$GLOBALS['home_inference_test_settings_errors'] = array();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ): void {}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ): void {}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( ...$args ): void {}
}

if ( ! function_exists( 'add_options_page' ) ) {
	function add_options_page( ...$args ): void {}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		return $GLOBALS['home_inference_test_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $name, $value ): void {
		$GLOBALS['home_inference_test_options'][ $name ] = $value;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( array $input, string $field ): array {
		return array_map(
			static function ( $item ) use ( $field ) {
				return is_array( $item ) ? ( $item[ $field ] ?? null ) : null;
			},
			$input
		);
	}
}

if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( string $setting, string $code, string $message ): void {
		$GLOBALS['home_inference_test_settings_errors'][] = array(
			'setting' => $setting,
			'code'    => $code,
			'message' => $message,
		);
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return trim( $url );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/' );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( string $url, array $args = array() ) {
		$response = $GLOBALS['home_inference_test_remote_response'];

		if ( is_callable( $response ) ) {
			return $response( $url, $args );
		}

		return $response;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ): int {
		return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ): string {
		return isset( $response['body'] ) ? (string) $response['body'] : '';
	}
}

require_once __DIR__ . '/../../plugin.php';
