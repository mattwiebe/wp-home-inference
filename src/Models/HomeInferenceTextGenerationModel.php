<?php

declare(strict_types=1);

namespace WordPress\HomeInference\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\HomeInference\Provider\HomeInferenceProvider;

/**
 * Text generation model for Home Inference using the OpenAI-compatible Chat Completions API.
 *
 * This works with any local inference server that exposes an OpenAI-compatible
 * endpoint (Ollama, llama.cpp, LM Studio, vLLM, etc.).
 *
 * @since 0.1.0
 */
class HomeInferenceTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

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
			$data,
			$this->getRequestOptions()
		);
	}
}
