<?php

namespace WikiSearch\QueryEngine\Query;

use ONGR\ElasticsearchDSL\BuilderInterface;

/**
 * Wraps an arbitrary raw query array as an ONGR BuilderInterface so it can be
 * added to BoolQuery or any other DSL compound query.
 *
 * This is used to inject OpenSearch-specific query types (e.g. "neural") that
 * are not natively supported by the ONGR ElasticsearchDSL library.
 */
class RawQuery implements BuilderInterface {
	/**
	 * @param array $query The raw query array, e.g. ['neural' => [...]]
	 */
	public function __construct( private array $query ) {
	}

	/**
	 * @inheritDoc
	 */
	public function toArray(): array {
		return $this->query;
	}

	/**
	 * @inheritDoc
	 */
	public function getType(): string {
		return 'raw';
	}
}
