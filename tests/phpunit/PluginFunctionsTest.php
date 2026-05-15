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
use function Mattwiebe\LocalAiConnector\get_wordpress_ai_ability_model_targets;
use function Mattwiebe\LocalAiConnector\get_wordpress_ai_feature_id_for_ability;
use function Mattwiebe\LocalAiConnector\prepend_default_local_ai_text_model;
use function Mattwiebe\LocalAiConnector\provider_definitions;
use function Mattwiebe\LocalAiConnector\register_settings;
use function Mattwiebe\LocalAiConnector\sanitize_api_key;
use function Mattwiebe\LocalAiConnector\sanitize_actual_computer_model_id;
use function Mattwiebe\LocalAiConnector\sanitize_ability_model_preferences;
use function Mattwiebe\LocalAiConnector\sanitize_local_ai_model_id;
use function Mattwiebe\LocalAiConnector\should_allow_actual_computer_request;
use function Mattwiebe\LocalAiConnector\should_allow_local_ai_request;
use function Mattwiebe\LocalAiConnector\wordpress_ai_ability_schema_has_media_markers;
use function Mattwiebe\LocalAiConnector\wordpress_ai_feature_supports_local_ai_text_models;

final class PluginFunctionsTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		update_option( 'mwlai_endpoint_url', 'https://proxy.example.test' );
		update_option( 'mwlai_api_key', 'secret-token' );
		delete_option( 'mwlai_model_id' );
		update_option( 'mwlai_actual_computer_api_key', 'actual-secret-token' );
		delete_option( 'mwlai_actual_computer_model_id' );
		$_POST = array();
	}

	public function test_sanitize_api_key_trims_outer_whitespace_only(): void {
		$raw = " \t  token:abc+/=  \n";

		$this->assertSame( 'token:abc+/=', sanitize_api_key( $raw ) );
	}

	public function test_local_ai_setup_keeps_tailscale_command_code_markup(): void {
		$definitions = provider_definitions();
		$html        = $definitions['mwlai']['setup']['steps'][1]['html'];

		$this->assertStringContainsString( '<code>tailscale up</code>', $html );
		$this->assertStringContainsString( '<code>tailscale up</code>', \wp_kses_post( $html ) );
	}

	public function test_registered_settings_have_sanitize_callbacks(): void {
		global $wp_registered_settings;

		register_settings();

		$definitions = provider_definitions();
		$options     = array(
			$definitions['mwlai']['endpoint_option'],
			$definitions['mwlai']['api_key_option'],
			$definitions['mwlai']['model_option'],
			$definitions['mwlai-actual-computer']['api_key_option'],
			$definitions['mwlai-actual-computer']['model_option'],
		);

		foreach ( $options as $option ) {
			$this->assertArrayHasKey( $option, $wp_registered_settings );
			$this->assertArrayHasKey( 'sanitize_callback', $wp_registered_settings[ $option ] );
			$this->assertIsCallable( $wp_registered_settings[ $option ]['sanitize_callback'] );
		}
	}

	public function test_wordpress_ai_feature_id_for_ability_derives_registered_features(): void {
		add_filter(
			'mwlai_wordpress_ai_feature_metadata',
			static function (): array {
				return array(
					'title-generation'    => array( 'capability' => 'text_generation' ),
					'review-notes'        => array( 'capability' => 'text_generation' ),
					'alt-text-generation' => array( 'capability' => 'vision' ),
				);
			}
		);

		$this->assertSame( 'title-generation', get_wordpress_ai_feature_id_for_ability( 'ai/title-generation' ) );
		$this->assertSame( 'review-notes', get_wordpress_ai_feature_id_for_ability( 'ai/review-notes' ) );
		$this->assertSame( 'alt-text-generation', get_wordpress_ai_feature_id_for_ability( 'ai/alt-text-generation' ) );
		$this->assertSame( '', get_wordpress_ai_feature_id_for_ability( 'ai/comment-analysis' ) );
		$this->assertSame( '', get_wordpress_ai_feature_id_for_ability( 'wordpress/get-site-info' ) );

		remove_all_filters( 'mwlai_wordpress_ai_feature_metadata' );
	}

	public function test_wordpress_ai_feature_supports_only_text_generation_features(): void {
		$ability = new class() {
			public function get_input_schema(): array {
				return array();
			}

			public function get_output_schema(): array {
				return array();
			}
		};

		add_filter(
			'mwlai_wordpress_ai_feature_metadata',
			static function (): array {
				return array(
					'title-generation'    => array( 'capability' => 'text_generation' ),
					'alt-text-generation' => array( 'capability' => 'vision' ),
				);
			}
		);

		$this->assertTrue( wordpress_ai_feature_supports_local_ai_text_models( 'title-generation', $ability ) );
		$this->assertFalse( wordpress_ai_feature_supports_local_ai_text_models( 'alt-text-generation', $ability ) );

		remove_all_filters( 'mwlai_wordpress_ai_feature_metadata' );
	}

	public function test_wordpress_ai_ability_schema_detects_media_markers(): void {
		$this->assertFalse(
			wordpress_ai_ability_schema_has_media_markers(
				array(
					'type'       => 'object',
					'properties' => array(
						'content' => array( 'type' => 'string' ),
					),
				)
			)
		);

		$this->assertTrue(
			wordpress_ai_ability_schema_has_media_markers(
				array(
					'type'       => 'object',
					'properties' => array(
						'image_url' => array( 'type' => 'string' ),
					),
				)
			)
		);
	}

	public function test_wordpress_ai_ability_targets_use_active_registered_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_unregister_ability' ) ) {
			$this->markTestSkipped( 'The WordPress Abilities API is not available.' );
		}

		add_filter(
			'mwlai_wordpress_ai_feature_metadata',
			static function (): array {
				return array(
					'title-generation'    => array( 'capability' => 'text_generation' ),
					'alt-text-generation' => array( 'capability' => 'vision' ),
				);
			}
		);

		$register_abilities = static function (): void {
			wp_register_ability(
				'ai/title-generation',
				array(
					'label'               => 'Title Generation',
					'description'         => 'Generates a title.',
					'category'            => 'ai',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'content' => array( 'type' => 'string' ),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'title' => array( 'type' => 'string' ),
						),
					),
					'execute_callback'    => '__return_null',
					'permission_callback' => '__return_true',
				)
			);

			wp_register_ability(
				'ai/alt-text-generation',
				array(
					'label'               => 'Alt Text Generation',
					'description'         => 'Generates alt text.',
					'category'            => 'ai',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'image_url' => array( 'type' => 'string' ),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'alt_text' => array( 'type' => 'string' ),
						),
					),
					'execute_callback'    => '__return_null',
					'permission_callback' => '__return_true',
				)
			);
		};

		add_action( 'wp_abilities_api_init', $register_abilities );
		do_action( 'wp_abilities_api_init' );
		remove_action( 'wp_abilities_api_init', $register_abilities );

		$targets       = get_wordpress_ai_ability_model_targets();
		$ability_slugs = wp_list_pluck( $targets, 'ability_slug' );

		wp_unregister_ability( 'ai/title-generation' );
		wp_unregister_ability( 'ai/alt-text-generation' );
		remove_all_filters( 'mwlai_wordpress_ai_feature_metadata' );

		$this->assertContains( 'ai/title-generation', $ability_slugs );
		$this->assertNotContains( 'ai/alt-text-generation', $ability_slugs );
	}

	public function test_sanitize_ability_model_preferences_accepts_only_available_models(): void {
		$preferences = sanitize_ability_model_preferences(
			array(
				'title-generation'   => 'mwlai|qwen2.5',
				'review-notes'       => 'unknown-model',
				'content-resizing'   => '',
				'excerpt-generation' => 'openai|gpt-4.1-mini',
				'invalid/nested/key' => 'llama3.2',
			),
			array( 'llama3.2', 'qwen2.5' )
		);

		$this->assertSame(
			array(
				'title-generation'   => array(
					'provider' => 'mwlai',
					'model'    => 'qwen2.5',
				),
				'content-resizing' => '',
				'excerpt-generation' => array(
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				),
			),
			$preferences
		);
	}

	public function test_default_local_ai_model_is_prepended_to_text_preferences(): void {
		update_option( 'mwlai_model_id', 'qwen2.5' );

		$preferences = prepend_default_local_ai_text_model(
			array(
				array( 'openai', 'gpt-4.1-mini' ),
				array( 'mwlai', 'qwen2.5' ),
			)
		);

		$this->assertSame(
			array(
				array( 'mwlai', 'qwen2.5' ),
				array( 'openai', 'gpt-4.1-mini' ),
			),
			$preferences
		);
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

	public function test_fetch_proxy_models_allows_blank_api_key(): void {
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) {
				if ( 'http://127.0.0.1:13531/v1/models' !== $url ) {
					return $response;
				}

				if ( isset( $args['headers']['Authorization'] ) ) {
					return new WP_Error( 'unexpected_authorization_header', 'Authorization header should not be sent.' );
				}

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'data' => array(
								array( 'id' => 'ollama/llama3.2' ),
							),
						)
					),
				);
			},
			10,
			3
		);

		$models = fetch_proxy_models( 'http://127.0.0.1:13531', '' );

		remove_all_filters( 'pre_http_request' );

		$this->assertIsArray( $models );
		$this->assertSame( array( 'ollama/llama3.2' ), wp_list_pluck( $models, 'id' ) );
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
		$this->assertSame( 'mwlai_models_invalid_response', $models->get_error_code() );
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

	public function test_sanitize_model_id_accepts_live_model_without_api_key(): void {
		update_option( 'mwlai_endpoint_url', 'http://127.0.0.1:13531' );
		update_option( 'mwlai_api_key', '' );

		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) {
				if ( 'http://127.0.0.1:13531/v1/models' !== $url ) {
					return $response;
				}

				if ( isset( $args['headers']['Authorization'] ) ) {
					return new WP_Error( 'unexpected_authorization_header', 'Authorization header should not be sent.' );
				}

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'data' => array(
								array( 'id' => 'ollama/llama3.2' ),
							),
						)
					),
				);
			},
			10,
			3
		);

		$this->assertSame( 'ollama/llama3.2', sanitize_local_ai_model_id( 'ollama/llama3.2' ) );

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

		update_option( 'mwlai_model_id', 'missing-model' );

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

	public function test_model_directory_exposes_all_available_models_when_default_model_is_selected(): void {
		if ( ! class_exists( \WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory::class ) ) {
			$this->markTestSkipped( 'WordPress AI Client model metadata directory classes are not available in this test environment.' );
		}

		update_option( 'mwlai_model_id', 'qwen2.5' );

		$directory  = new LocalAiModelMetadataDirectory();
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

		update_option( 'mwlai_actual_computer_model_id', 'missing-model' );

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
