<?php

namespace WSSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\RangeAggregation;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyRangeAggregation
 *
 * A multi-bucket value source based aggregation that enables the user to define a
 * set of ranges - each representing a bucket.
 *
 * @package WSSearch\QueryEngine\Aggregation
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-range-aggregation.html
 */
class PropertyRangeAggregation implements PropertyAggregation {
	/**
	 * @var string
	 */
	private $aggregation_name;

	/**
	 * @var PropertyFieldMapper
	 */
	private $property;

	/**
	 * @var array
	 */
	private $ranges;

	/**
	 * PropertyAggregation constructor.
	 *
	 * @param PropertyFieldMapper|string $property The property object or name for the aggregation
	 * @param array $ranges
	 * @param string|null $aggregation_name
	 */
	public function __construct( $property, array $ranges, string $aggregation_name = null ) {
		if ( is_string( $property ) ) {
			$property = new PropertyFieldMapper( $property );
		}

		if ( !( $property instanceof PropertyFieldMapper ) ) {
			throw new \InvalidArgumentException();
		}

		if ( $aggregation_name === null ) {
			$aggregation_name = $property->getPropertyName();
		}

		$this->aggregation_name = $aggregation_name;
		$this->property = $property;
		$this->ranges = $ranges;
	}

	/**
	 * Sets the property object to use for the aggregation.
	 *
	 * @param PropertyFieldMapper $property
	 */
	public function setProperty( PropertyFieldMapper $property ) {
		$this->property = $property;
	}

	/**
	 * Sets the ranges to use for the aggregation.
	 *
	 * @param array $ranges
	 */
	public function setRanges( array $ranges ) {
		$this->ranges = $ranges;
	}

	/**
	 * Returns the property field mapper corresponding to this aggregation.
	 *
	 * @return PropertyFieldMapper
	 */
	public function getProperty(): PropertyFieldMapper {
		return $this->property;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->aggregation_name;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): AbstractAggregation {
		return new RangeAggregation(
			$this->aggregation_name,
			$this->property->getPropertyField(),
			$this->ranges,
			true
		);
	}
}
