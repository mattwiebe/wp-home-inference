<?php

declare(strict_types=1);

namespace Mattwiebe\LocalAiConnector\Models;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use Mattwiebe\LocalAiConnector\Provider\ActualComputerProvider;

/**
 * Text generation model for Actual Computer using an OpenAI-compatible API.
 *
 * @since 0.1.6
 */
class ActualComputerTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * Constructor.
	 *
	 * @since 0.1.6
	 *
	 * @param ModelMetadata    $modelMetadata    The model metadata.
	 * @param ProviderMetadata $providerMetadata The provider metadata.
	 */
	public function __construct( ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata ) {
		parent::__construct( $modelMetadata, $providerMetadata );

		$request_options = new RequestOptions();
		$request_options->setConnectTimeout( 10.0 );
		$request_options->setTimeout( 120.0 );

		$this->setRequestOptions( $request_options );
	}

	/**
	 * Actual Computer expects the legacy Chat Completions shape where each
	 * message content is a plain string rather than a typed content-parts array.
	 *
	 * @since 0.1.6
	 *
	 * @param list<Message> $messages The messages to prepare.
	 * @param string|null   $systemInstruction Optional system instruction.
	 * @return list<array<string, mixed>>
	 */
	protected function prepareMessagesParam( array $messages, ?string $systemInstruction = null ): array {
		$messages_param = array_map(
			function ( Message $message ): array {
				$message_parts = $message->getParts();

				if ( 1 === count( $message_parts ) && $message_parts[0]->getType()->isFunctionResponse() ) {
					$function_response = $message_parts[0]->getFunctionResponse();
					if ( ! $function_response ) {
						throw new RuntimeException( 'The function response typed message part must contain a function response.' );
					}

					return array(
						'role'         => 'tool',
						'content'      => wp_json_encode( $function_response->getResponse() ),
						'tool_call_id' => $function_response->getId(),
					);
				}

				$message_data = array(
					'role'    => $this->getActualMessageRoleString( $message->getRole() ),
					'content' => $this->flattenMessagePartsToString( $message_parts ),
				);

				$tool_calls = array_values( array_filter( array_map( array( $this, 'getMessagePartToolCallData' ), $message_parts ) ) );
				if ( ! empty( $tool_calls ) ) {
					$message_data['tool_calls'] = $tool_calls;
				}

				return $message_data;
			},
			$messages
		);

		if ( $systemInstruction ) {
			array_unshift(
				$messages_param,
				array(
					'role'    => 'system',
					'content' => $systemInstruction,
				)
			);
		}

		return $messages_param;
	}

	/**
	 * Returns the role string expected by Actual's chat endpoint.
	 *
	 * @since 0.1.6
	 *
	 * @param MessageRoleEnum $role Message role.
	 * @return string
	 */
	private function getActualMessageRoleString( MessageRoleEnum $role ): string {
		if ( $role === MessageRoleEnum::model() ) {
			return 'assistant';
		}

		return 'user';
	}

	/**
	 * Flattens supported message parts into a single string for providers that
	 * do not support typed content arrays.
	 *
	 * @since 0.1.6
	 *
	 * @param MessagePart[] $message_parts Message parts.
	 * @return string
	 */
	private function flattenMessagePartsToString( array $message_parts ): string {
		$parts = array();

		foreach ( $message_parts as $part ) {
			$type = $part->getType();

			if ( $type->isText() ) {
				if ( $part->getChannel()->isThought() ) {
					continue;
				}

				$text = $part->getText();
				if ( null !== $text && '' !== $text ) {
					$parts[] = $text;
				}

				continue;
			}

			if ( $type->isFunctionCall() ) {
				continue;
			}

			if ( $type->isFile() ) {
				$file = $part->getFile();
				if ( $file && $file->isRemote() && $file->getUrl() ) {
					$parts[] = $file->getUrl();
				}
			}
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.1.6
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = [],
		$data = null
	): Request {
		return new Request(
			$method,
			ActualComputerProvider::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}
