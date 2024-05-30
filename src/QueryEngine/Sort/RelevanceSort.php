<?php

namespace WikiSearch\QueryEngine\Sort;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Sorts based on relevance of the result.
 */
class RelevanceSort implements Sort {
    private const SCORE_FIELD = '_score';

	/**
	 * @inheritDoc
	 */
	public function toQuery(): BuilderInterface {
		return new FieldSort( self::SCORE_FIELD, 'desc', [] );
	}
}
