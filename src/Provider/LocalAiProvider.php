<?php

declare(strict_types=1);

namespace Mattwiebe\LocalAiConnector\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use Mattwiebe\LocalAiConnector\Metadata\LocalAiModelMetadataDirectory;
use Mattwiebe\LocalAiConnector\Models\LocalAiTextGenerationModel;

/**
 * AI provider for Local AI.
 *
 * Routes requests to a local inference server via a secure tunnel.
 *
 * @since 0.1.0
 */
class LocalAiProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function baseUrl(): string {
		$url = get_option( 'mw_local_ai_endpoint_url', '' );

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
		foreach ( $modelMetadata->getSupportedCapabilities() as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new LocalAiTextGenerationModel( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException( 'Unsupported model capabilities.' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'mw-local-ai',
			'Local AI',
			ProviderTypeEnum::server(),
			null,
			RequestAuthenticationMethod::apiKey(),
			__( 'Run AI inference on your own hardware using local models.', 'mw-local-ai-connector' ),
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
		return new LocalAiModelMetadataDirectory();
	}
}
