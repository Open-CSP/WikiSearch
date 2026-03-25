<?php

namespace WikiSearch\NeuralSearch;

use MediaWiki\MediaWikiServices;
use WikiSearch\Logger;

/**
 * OpenSearch natural language search backend using dense vector kNN.
 *
 * This backend creates and maintains a "text_embedding" ingest pipeline on the
 * OpenSearch cluster. When SMW indexes a page, the pipeline automatically runs
 * the configured embedding model over the "text_raw" field and stores the result
 * in the "wikisearch_embedding" knn_vector field. Searches then use OpenSearch's
 * "neural" query type, which generates query embeddings at search time and
 * performs an approximate kNN lookup.
 *
 * Admin prerequisites:
 *  1. The index must be created with "knn": true in its settings and a
 *     "wikisearch_embedding" knn_vector field in its mappings. Use the updated
 *     smw-wikisearch-data-standard-template.json for this.
 *  2. Deploy a sentence-transformer (bi-encoder) embedding model via ML Commons:
 *       POST /_plugins/_ml/models/_register
 *  3. Set $wgWikiSearchNeuralSearchModelId to the resulting model ID.
 *  4. Reindex all existing documents so they receive embeddings:
 *       POST /<index>/_update_by_query?pipeline=wikisearch-neural-ingest
 *
 * The extension automatically creates and maintains the ingest pipeline.
 */
class OpenSearchNeuralSearchBackend implements NeuralSearchBackend {
	/**
	 * The name of the OpenSearch ingest pipeline managed by this backend.
	 */
	public const INGEST_PIPELINE_NAME = 'wikisearch-neural-ingest';

	/**
	 * The knn_vector field in the index that stores document embeddings.
	 */
	public const EMBEDDING_FIELD = 'wikisearch_embedding';

	/**
	 * Cache TTL for the pipeline-ready flag (1 hour).
	 */
	private const CACHE_TTL = 3600;

	/**
	 * @param string $modelId The ML Commons model ID of the deployed embedding model.
	 * @param string[] $hosts OpenSearch host URLs (e.g. ['http://localhost:9200']).
	 * @param string|null $username Optional HTTP Basic Auth username.
	 * @param string|null $password Optional HTTP Basic Auth password.
	 */
	public function __construct(
		private string $modelId,
		private array $hosts,
		private ?string $username,
		private ?string $password
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function ensureIngestPipeline( string $index ): bool {
		$cache = MediaWikiServices::getInstance()->getMainObjectStash();
		$cacheKey = $cache->makeKey( 'wikisearch', 'neural-ingest-pipeline', md5( $this->modelId . $index ) );

		if ( $cache->get( $cacheKey ) ) {
			return true;
		}

		try {
			$this->putIngestPipeline();
			$this->setIndexDefaultPipeline( $index );
			$cache->set( $cacheKey, true, self::CACHE_TTL );
			return true;
		} catch ( \Exception $e ) {
			Logger::getLogger()->warning(
				'WikiSearch: failed to set up neural ingest pipeline: {message}',
				[ 'message' => $e->getMessage() ]
			);
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getEmbeddingField(): string {
		return self::EMBEDDING_FIELD;
	}

	/**
	 * @inheritDoc
	 */
	public function getModelId(): string {
		return $this->modelId;
	}

	/**
	 * Creates or updates an OpenSearch legacy index template that will apply
	 * "index.knn: true" and the knn_vector embedding field to any new index
	 * whose name matches $indexPattern. Must be called once before
	 * rebuildElasticIndex.php so that SMW creates the index with knn enabled.
	 *
	 * @param string $indexPattern Wildcard pattern, e.g. "smw-data-*".
	 * @param int    $dimension    Vector dimension (must match the embedding model).
	 * @throws \RuntimeException on HTTP or API failure.
	 */
	public function ensureIndexTemplate( string $indexPattern, int $dimension ): void {
		// The pipeline must exist before the template references it, so that any
		// index created from the template can immediately use it as its default.
		$this->putIngestPipeline();

		$body = [
			'index_patterns' => [ $indexPattern ],
			'settings' => [
				'knn'                    => true,
				'index.default_pipeline' => self::INGEST_PIPELINE_NAME,
			],
			'mappings' => [
				'properties' => [
					self::EMBEDDING_FIELD => [
						'type'      => 'knn_vector',
						'dimension' => $dimension,
						'method'    => [
							'name'       => 'hnsw',
							'space_type' => 'cosinesimil',
							'engine'     => 'lucene',
						],
					],
				],
			],
		];

		$response = $this->performRequest( 'PUT', '/_template/wikisearch-neural-knn', $body );

		if ( !isset( $response['acknowledged'] ) || $response['acknowledged'] !== true ) {
			throw new \RuntimeException(
				'OpenSearch did not acknowledge index template creation: ' . json_encode( $response )
			);
		}
	}

	/**
	 * Creates or updates the text_embedding ingest pipeline.
	 *
	 * @throws \RuntimeException on HTTP or API failure.
	 */
	private function putIngestPipeline(): void {
		$body = [
			'description' => 'WikiSearch neural embedding generation',
			'processors' => [
				[
					'text_embedding' => [
						'model_id'  => $this->modelId,
						'field_map' => [
							'text_raw' => self::EMBEDDING_FIELD,
						],
					],
				],
			],
		];

		$response = $this->performRequest( 'PUT', '/_ingest/pipeline/' . self::INGEST_PIPELINE_NAME, $body );

		if ( !isset( $response['acknowledged'] ) || $response['acknowledged'] !== true ) {
			throw new \RuntimeException(
				'OpenSearch did not acknowledge ingest pipeline creation: ' . json_encode( $response )
			);
		}
	}

	/**
	 * Sets the ingest pipeline as the default pipeline on the given index so
	 * that all documents indexed by SMW automatically receive embeddings.
	 *
	 * @param string $index
	 * @throws \RuntimeException on HTTP or API failure.
	 */
	private function setIndexDefaultPipeline( string $index ): void {
		$body = [
			'index' => [
				'default_pipeline' => self::INGEST_PIPELINE_NAME,
			],
		];

		$response = $this->performRequest( 'PUT', '/' . rawurlencode( $index ) . '/_settings', $body );

		if ( !isset( $response['acknowledged'] ) || $response['acknowledged'] !== true ) {
			throw new \RuntimeException(
				'OpenSearch did not acknowledge index settings update: ' . json_encode( $response )
			);
		}
	}

	/**
	 * Performs a raw HTTP request against the first configured OpenSearch host.
	 *
	 * @param string $method HTTP method (GET, PUT, POST, …).
	 * @param string $path   Request path, e.g. '/_ingest/pipeline/foo'.
	 * @param array  $body   Request body (will be JSON-encoded).
	 * @return array Decoded JSON response.
	 * @throws \RuntimeException on connection or decode failure.
	 */
	private function performRequest( string $method, string $path, array $body = [] ): array {
		$host = rtrim( $this->hosts[0], '/' );
		$url = $host . $path;

		$headers = [ 'Content-Type: application/json' ];

		if ( $this->username !== null && $this->password !== null ) {
			$headers[] = 'Authorization: Basic ' . base64_encode( $this->username . ':' . $this->password );
		}

		$options = [
			'http' => [
				'method'        => $method,
				'header'        => implode( "\r\n", $headers ),
				'content'       => json_encode( $body ),
				'ignore_errors' => true,
			],
		];

		$context = stream_context_create( $options );
		$raw = file_get_contents( 'http://' . $url, false, $context );

		if ( $raw === false ) {
			throw new \RuntimeException( "Could not connect to OpenSearch at $url" );
		}

		$decoded = json_decode( $raw, true );

		if ( !is_array( $decoded ) ) {
			throw new \RuntimeException( "Unexpected response from OpenSearch: $raw" );
		}

		return $decoded;
	}
}
