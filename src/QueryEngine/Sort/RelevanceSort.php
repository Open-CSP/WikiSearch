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
	 * @var string|null The order of the sort
	 */
	private ?string $order = null;

    /**
     * @var string|null The mode of the sort (either min, max or null)
     */
	private ?string $mode = null;

    /**
     * @param string|null $order
     */
	public function __construct( string $order = null ) {
		switch ( $order ) {
			case "asc":
			case "ascending":
			case "up":
				$this->order = FieldSort::ASC;
				$this->mode = "min";
				break;
			case "desc":
			case "dsc":
			case "descending":
			case "down":
				$this->order = FieldSort::DESC;
				$this->mode = "max";
				break;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): BuilderInterface {
        $parameters = [];

        if ( $this->mode !== null ) {
            $parameters['mode'] = $this->mode;
        }

		return new FieldSort( self::SCORE_FIELD, $this->order, $parameters );
	}
}
