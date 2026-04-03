<?php

declare(strict_types=1);

namespace WordPress\HomeInference\Tests\PHPUnit;

use WP_Error;
use WP_UnitTestCase;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\HomeInference\Metadata\HomeInferenceModelMetadataDirectory;
use function WordPress\HomeInference\fetch_proxy_models;
use function WordPress\HomeInference\sanitize_api_key;
use function WordPress\HomeInference\sanitize_model_id;

final class PluginFunctionsTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		update_option( 'home_inference_endpoint_url', 'https://proxy.example.test' );
		update_option( 'connectors_ai_home_inference_api_key', 'secret-token' );
		delete_option( 'home_inference_model_id' );
		$_POST = array();
	}

	public function test_sanitize_api_key_trims_outer_whitespace_only(): void {
		$raw = " \t  token:abc+/=  \n";

		$this->assertSame( 'token:abc+/=', sanitize_api_key( $raw ) );
	}

	public function test_fetch_proxy_models_returns_ids_from_valid_response(): void {
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) {
				if ( 'https://proxy.example.test/v1/models' !== $url ) {
					return $response;
				}

				return array(
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
			},
			10,
			3
		);

		$models = fetch_proxy_models( 'https://proxy.example.test', 'secret-token' );

		remove_all_filters( 'pre_http_request' );

		$this->assertIsArray( $models );
		$this->assertSame( array( 'llama3.2', 'qwen2.5' ), wp_list_pluck( $models, 'id' ) );
	}

	public function test_fetch_proxy_models_returns_wp_error_for_invalid_response(): void {
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) {
				if ( 'https://proxy.example.test/v1/models' !== $url ) {
					return $response;
				}

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( array( 'unexpected' => array() ) ),
				);
			},
			10,
			3
		);

		$models = fetch_proxy_models( 'https://proxy.example.test', 'secret-token' );

		remove_all_filters( 'pre_http_request' );

		$this->assertInstanceOf( WP_Error::class, $models );
		$this->assertSame( 'home_inference_models_invalid_response', $models->get_error_code() );
	}

	public function test_sanitize_model_id_accepts_live_model(): void {
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) {
				if ( 'https://proxy.example.test/v1/models' !== $url ) {
					return $response;
				}

				return array(
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
			},
			10,
			3
		);

		$this->assertSame( 'qwen2.5', sanitize_model_id( 'qwen2.5' ) );

		remove_all_filters( 'pre_http_request' );
	}

	public function test_sanitize_model_id_rejects_unknown_model(): void {
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) {
				if ( 'https://proxy.example.test/v1/models' !== $url ) {
					return $response;
				}

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'data' => array(
								array( 'id' => 'llama3.2' ),
							),
						)
					),
				);
			},
			10,
			3
		);

		$this->assertSame( '', sanitize_model_id( 'unknown-model' ) );

		remove_all_filters( 'pre_http_request' );
	}

	public function test_model_directory_falls_back_to_available_models_when_selected_model_is_missing(): void {
		if ( ! class_exists( \WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory::class ) ) {
			$this->markTestSkipped( 'WordPress AI Client model metadata directory classes are not available in this test environment.' );
		}

		update_option( 'home_inference_model_id', 'missing-model' );

		$directory = new HomeInferenceModelMetadataDirectory();
		$reflection = new \ReflectionMethod( $directory, 'parseResponseToModelMetadataList' );
		$reflection->setAccessible( true );

		$response = new Response(
			200,
			array(
				'content-type' => 'application/json',
			),
			wp_json_encode(
				array(
					'data' => array(
						array( 'id' => 'llama3.2' ),
						array( 'id' => 'qwen2.5' ),
					),
				)
			)
		);

		$models = $reflection->invoke( $directory, $response );

		$this->assertCount( 2, $models );
		$this->assertSame( array( 'llama3.2', 'qwen2.5' ), wp_list_pluck( $models, 'id' ) );
	}
}
