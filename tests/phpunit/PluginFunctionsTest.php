<?php

declare(strict_types=1);

namespace Mattwiebe\LocalAiConnector\Tests\PHPUnit;

use WP_Error;
use WP_UnitTestCase;
use WordPress\AiClient\Providers\Http\DTO\Response;
use Mattwiebe\LocalAiConnector\Metadata\ActualComputerModelMetadataDirectory;
use Mattwiebe\LocalAiConnector\Metadata\LocalAiModelMetadataDirectory;
use function Mattwiebe\LocalAiConnector\allow_local_ai_safe_remote_requests;
use function Mattwiebe\LocalAiConnector\fetch_proxy_models;
use function Mattwiebe\LocalAiConnector\sanitize_api_key;
use function Mattwiebe\LocalAiConnector\sanitize_actual_computer_model_id;
use function Mattwiebe\LocalAiConnector\sanitize_local_ai_model_id;
use function Mattwiebe\LocalAiConnector\should_allow_actual_computer_request;
use function Mattwiebe\LocalAiConnector\should_allow_local_ai_request;

final class PluginFunctionsTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		update_option( 'mw_local_ai_endpoint_url', 'https://proxy.example.test' );
		update_option( 'mw_local_ai_api_key', 'secret-token' );
		delete_option( 'mw_local_ai_model_id' );
		update_option( 'mw_actual_computer_api_key', 'actual-secret-token' );
		delete_option( 'mw_actual_computer_model_id' );
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
		$this->assertSame( 'mw_local_ai_models_invalid_response', $models->get_error_code() );
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

		$this->assertSame( 'qwen2.5', sanitize_local_ai_model_id( 'qwen2.5' ) );

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

		$this->assertSame( '', sanitize_local_ai_model_id( 'unknown-model' ) );

		remove_all_filters( 'pre_http_request' );
	}

	public function test_should_allow_local_ai_request_matches_configured_endpoint(): void {
		$this->assertTrue( should_allow_local_ai_request( 'https://proxy.example.test/v1/models' ) );
		$this->assertFalse( should_allow_local_ai_request( 'https://api.openai.com/v1/models' ) );
	}

	public function test_should_allow_actual_computer_request_matches_configured_endpoint(): void {
		$this->assertTrue( should_allow_actual_computer_request( 'https://api.actual.inc/v1/models' ) );
		$this->assertFalse( should_allow_actual_computer_request( 'https://proxy.example.test/v1/models' ) );
	}

	public function test_allow_local_ai_safe_remote_requests_disables_unsafe_url_rejection_only_for_local_ai(): void {
		$allowed_args = allow_local_ai_safe_remote_requests(
			array(
				'reject_unsafe_urls' => true,
			),
			'https://proxy.example.test/v1/models'
		);

		$other_args = allow_local_ai_safe_remote_requests(
			array(
				'reject_unsafe_urls' => true,
			),
			'https://api.openai.com/v1/models'
		);

		$this->assertFalse( $allowed_args['reject_unsafe_urls'] );
		$this->assertTrue( $other_args['reject_unsafe_urls'] );
	}

	public function test_allow_local_ai_safe_remote_requests_disables_unsafe_url_rejection_for_actual_computer(): void {
		$allowed_args = allow_local_ai_safe_remote_requests(
			array(
				'reject_unsafe_urls' => true,
			),
			'https://api.actual.inc/v1/models'
		);

		$this->assertFalse( $allowed_args['reject_unsafe_urls'] );
	}

	public function test_model_directory_falls_back_to_available_models_when_selected_model_is_missing(): void {
		if ( ! class_exists( \WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory::class ) ) {
			$this->markTestSkipped( 'WordPress AI Client model metadata directory classes are not available in this test environment.' );
		}

		update_option( 'mw_local_ai_model_id', 'missing-model' );

		$directory = new LocalAiModelMetadataDirectory();
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

	public function test_sanitize_actual_computer_model_id_accepts_live_model(): void {
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) {
				if ( 'https://api.actual.inc/v1/models' !== $url ) {
					return $response;
				}

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'data' => array(
								array( 'id' => 'actual-large' ),
								array( 'id' => 'actual-small' ),
							),
						)
					),
				);
			},
			10,
			3
		);

		$this->assertSame( 'actual-small', sanitize_actual_computer_model_id( 'actual-small' ) );

		remove_all_filters( 'pre_http_request' );
	}

	public function test_actual_model_directory_falls_back_to_available_models_when_selected_model_is_missing(): void {
		if ( ! class_exists( \WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory::class ) ) {
			$this->markTestSkipped( 'WordPress AI Client model metadata directory classes are not available in this test environment.' );
		}

		update_option( 'mw_actual_computer_model_id', 'missing-model' );

		$directory  = new ActualComputerModelMetadataDirectory();
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
						array( 'id' => 'actual-large' ),
						array( 'id' => 'actual-small' ),
					),
				)
			)
		);

		$models = $reflection->invoke( $directory, $response );

		$this->assertCount( 2, $models );
		$this->assertSame( array( 'actual-large', 'actual-small' ), wp_list_pluck( $models, 'id' ) );
	}
}
