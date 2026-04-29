<?php

declare(strict_types=1);

namespace Mattwiebe\LocalAiConnector\Provider;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use Mattwiebe\LocalAiConnector\Metadata\ActualComputerModelMetadataDirectory;
use Mattwiebe\LocalAiConnector\Models\ActualComputerTextGenerationModel;

/**
 * AI provider for Actual Computer.
 *
 * @since 0.1.6
 */
class ActualComputerProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.6
	 */
	protected static function baseUrl(): string {
		return 'https://api.actual.inc/v1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.6
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		foreach ( $modelMetadata->getSupportedCapabilities() as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new ActualComputerTextGenerationModel( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException( 'Unsupported model capabilities.' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.6
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'mw-actual-computer',
			'Actual Computer',
			ProviderTypeEnum::server(),
			null,
			RequestAuthenticationMethod::apiKey(),
			__( 'Connect WordPress AI to an Actual Computer endpoint.', 'mw-local-ai-connector' ),
			__DIR__ . '/../../assets/logo.svg'
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.6
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability( static::modelMetadataDirectory() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.6
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new ActualComputerModelMetadataDirectory();
	}
}
