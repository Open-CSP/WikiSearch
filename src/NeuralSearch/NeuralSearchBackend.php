<?php

namespace WikiSearch\NeuralSearch;

/**
 * Interface for natural language search backends.
 *
 * Each implementation is responsible for:
 *  - Ensuring any required ingest pipeline exists on the cluster so that
 *    documents indexed by SMW automatically receive vector embeddings.
 *  - Providing the knn_vector field name and the model ID used in neural queries.
 */
interface NeuralSearchBackend {
	/**
	 * Ensures the embedding ingest pipeline exists and is set as the default
	 * pipeline on the given index. Returns true on success, false on failure
	 * (caller should fall back to regular keyword search).
	 *
	 * @param string $index The OpenSearch index name.
	 * @return bool
	 */
	public function ensureIngestPipeline( string $index ): bool;

	/**
	 * Returns the knn_vector field name to search against.
	 *
	 * @return string
	 */
	public function getEmbeddingField(): string;

	/**
	 * Returns the deployed ML model ID used in neural queries.
	 *
	 * @return string
	 */
	public function getModelId(): string;
}
