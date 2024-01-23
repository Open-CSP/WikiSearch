<?php

namespace WikiSearch\QueryEngine\Sort;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class RelevanceSort
 *
 * Sorts based on relevance of the result.
 *
 * @package WikiSearch\QueryEngine\Sort
 */
class RelevanceSort implements Sort {
    private const SCORE_FIELD = '_score';

	/**
	 * @var string|null The order of the sort
	 */
	private ?string $order;

	/**
	 * @var array Additional sort parameters to pass to the sort query
	 */
	private array $parameters = [];

	/**
	 * FieldSort constructor.
	 *
	 * @param string|PropertyFieldMapper $property The property to sort on
	 * @param string|null $order
	 */
	public function __construct( string $order = null ) {
		switch ( $order ) {
			case "asc":
			case "ascending":
			case "up":
				$this->order = FieldSort::ASC;
				$this->addParameter( "mode", "min" );
				break;
			case "desc":
			case "dsc":
			case "descending":
			case "down":
				$this->order = FieldSort::DESC;
				$this->addParameter( "mode", "max" );
				break;
			default:
				$this->order = null;
				break;
		}
	}

	/**
	 * Adds a parameter (option) to the sort.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function addParameter( string $key, $value ): void {
		$this->parameters[$key] = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): BuilderInterface {
		return new FieldSort( self::SCORE_FIELD, $this->order, $this->parameters );
	}
}
