<?php

declare(strict_types=1);

namespace WordPress\HomeInference\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\HomeInference\Metadata\HomeInferenceModelMetadataDirectory;
use WordPress\HomeInference\Models\HomeInferenceTextGenerationModel;

/**
 * AI Provider for Home Inference.
 *
 * Routes requests to a local inference server via a secure tunnel.
 *
 * @since 0.1.0
 */
class HomeInferenceProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function baseUrl(): string {
		$url = get_option( 'home_inference_endpoint_url', '' );

		if ( empty( $url ) ) {
			return '';
		}

		return rtrim( $url, '/' ) . '/v1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		return new HomeInferenceTextGenerationModel( $modelMetadata, $providerMetadata );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'home-inference',
			'Home Inference',
			ProviderTypeEnum::server(),
			null,
			RequestAuthenticationMethod::apiKey(),
			__( 'Run AI inference on your own hardware using local models.', 'wp-home-inference' ),
			__DIR__ . '/../../assets/logo.svg'
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability( static::modelMetadataDirectory() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new HomeInferenceModelMetadataDirectory();
	}
}
