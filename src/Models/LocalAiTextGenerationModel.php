<?php

declare(strict_types=1);

namespace Mattwiebe\LocalAiConnector\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use Mattwiebe\LocalAiConnector\Provider\LocalAiProvider;

/**
 * Text generation model for Local AI using the OpenAI-compatible Chat Completions API.
 *
 * This works with any local inference server that exposes an OpenAI-compatible
 * endpoint (Ollama, llama.cpp, LM Studio, vLLM, etc.).
 *
 * @since 0.1.0
 */
class LocalAiTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * Constructor.
	 *
	 * Local inference on consumer hardware can take significantly longer than
	 * cloud APIs to produce the first response bytes, so we apply a longer
	 * default transport timeout for generation requests.
	 *
	 * @since 0.1.0
	 *
	 * @param ModelMetadata    $modelMetadata    The model metadata.
	 * @param ProviderMetadata $providerMetadata The provider metadata.
	 */
	public function __construct( ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata ) {
		parent::__construct( $modelMetadata, $providerMetadata );

		$request_options = new RequestOptions();
		$request_options->setConnectTimeout( 10.0 );
		$request_options->setTimeout( 300.0 );

		$this->setRequestOptions( $request_options );
	}

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
			LocalAiProvider::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}
