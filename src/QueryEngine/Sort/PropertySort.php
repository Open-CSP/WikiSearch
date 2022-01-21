<?php

namespace WSSearch\QueryEngine\Sort;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use WSSearch\Logger;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class FieldSort
 *
 * Sorts based on the value of the given property.
 *
 * @package WSSearch\QueryEngine\Sort
 */
class PropertySort implements Sort {
	/**
	 * @var string The order of the sort
	 */
	private $order;

	/**
	 * @var string The field to sort on
	 */
	private $field;

	/**
	 * @var array Additional sort parameters to pass to the sort query
	 */
	private $parameters = [];

	/**
	 * FieldSort constructor.
	 *
	 * @param string $property The property to sort on
	 * @param string|null $order
	 */
	public function __construct( $property, string $order = null ) {
		if ( is_string( $property ) ) {
			$property = new PropertyFieldMapper( $property );
		}

		if ( !( $property instanceof PropertyFieldMapper ) ) {
			Logger::getLogger()->critical( 'Tried to construct a PropertySort with an invalid property: {property}', [
				'property' => $property
			] );

			throw new \InvalidArgumentException();
		}

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

		$this->field = $property->getPropertyField( true );
	}

	/**
	 * Adds a parameter (option) to the sort.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function addParameter( string $key, $value ) {
		$this->parameters[$key] = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): BuilderInterface {
		return new FieldSort( $this->field, $this->order, $this->parameters );
	}
}
