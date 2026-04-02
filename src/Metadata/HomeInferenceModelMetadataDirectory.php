<?php

declare(strict_types=1);

namespace WordPress\HomeInference\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use WordPress\HomeInference\Provider\HomeInferenceProvider;

/**
 * Model metadata directory that discovers models from a local inference server's
 * OpenAI-compatible /v1/models endpoint.
 *
 * @since 0.1.0
 *
 * @phpstan-type ModelsResponseData array{
 *     data?: list<array{id: string, owned_by?: string}>
 * }
 */
class HomeInferenceModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = [],
		$data = null
	): Request {
		return new Request(
			$method,
			HomeInferenceProvider::url( $path ),
			$headers,
			$data
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.0
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		/** @var ModelsResponseData $responseData */
		$responseData = $response->getData();

		if ( ! isset( $responseData['data'] ) || ! $responseData['data'] ) {
			throw ResponseException::fromMissingData( 'Home Inference', 'data' );
		}

		$capabilities = array(
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		);

		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);

		$models = array();
		$selected_model_id = trim( (string) get_option( 'home_inference_model_id', '' ) );

		foreach ( (array) $responseData['data'] as $model_data ) {
			if ( ! is_array( $model_data ) || ! isset( $model_data['id'] ) ) {
				continue;
			}

			if ( '' !== $selected_model_id && $selected_model_id !== $model_data['id'] ) {
				continue;
			}

			$models[] = new ModelMetadata(
				$model_data['id'],
				$model_data['id'],
				$capabilities,
				$options
			);
		}

		return $models;
	}
}
