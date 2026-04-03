<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function WordPress\HomeInference\fetch_proxy_models;
use function WordPress\HomeInference\sanitize_api_key;
use function WordPress\HomeInference\sanitize_model_id;

final class PluginFunctionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['home_inference_test_options'] = array(
			'home_inference_endpoint_url'          => 'https://proxy.example.test',
			'connectors_ai_home_inference_api_key' => 'secret-token',
		);
		$GLOBALS['home_inference_test_settings_errors'] = array();
		$GLOBALS['home_inference_test_remote_response'] = null;
		$_POST = array();
	}

	public function test_sanitize_api_key_trims_outer_whitespace_only(): void {
		$raw = " \t  token:abc+/=  \n";

		$this->assertSame( 'token:abc+/=', sanitize_api_key( $raw ) );
	}

	public function test_fetch_proxy_models_returns_ids_from_valid_response(): void {
		$GLOBALS['home_inference_test_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'data' => array(
						array( 'id' => 'llama3.2', 'owned_by' => 'ollama' ),
						array( 'id' => 'qwen2.5' ),
					),
				)
			),
		);

		$models = fetch_proxy_models( 'https://proxy.example.test', 'secret-token' );

		$this->assertFalse( is_wp_error( $models ) );
		$this->assertSame( array( 'llama3.2', 'qwen2.5' ), wp_list_pluck( $models, 'id' ) );
	}

	public function test_fetch_proxy_models_returns_wp_error_for_invalid_response(): void {
		$GLOBALS['home_inference_test_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array( 'unexpected' => array() ) ),
		);

		$models = fetch_proxy_models( 'https://proxy.example.test', 'secret-token' );

		$this->assertTrue( is_wp_error( $models ) );
		$this->assertSame( 'home_inference_models_invalid_response', $models->get_error_code() );
	}

	public function test_sanitize_model_id_accepts_live_model(): void {
		$GLOBALS['home_inference_test_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'data' => array(
						array( 'id' => 'llama3.2' ),
						array( 'id' => 'qwen2.5' ),
					),
				)
			),
		);

		$this->assertSame( 'qwen2.5', sanitize_model_id( 'qwen2.5' ) );
		$this->assertSame( array(), $GLOBALS['home_inference_test_settings_errors'] );
	}

	public function test_sanitize_model_id_rejects_unknown_model(): void {
		$GLOBALS['home_inference_test_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'data' => array(
						array( 'id' => 'llama3.2' ),
					),
				)
			),
		);

		$this->assertSame( '', sanitize_model_id( 'unknown-model' ) );
		$this->assertCount( 1, $GLOBALS['home_inference_test_settings_errors'] );
		$this->assertSame(
			'home_inference_model_id_invalid',
			$GLOBALS['home_inference_test_settings_errors'][0]['code']
		);
	}
}
