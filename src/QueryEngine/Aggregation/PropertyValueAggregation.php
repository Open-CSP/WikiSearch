<?php

namespace WikiSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyAggregation
 *
 * Multi-bucket value source based aggregation with buckets of property values.
 *
 * @package WikiSearch\QueryEngine\Aggregation
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-terms-aggregation.html
 */
class PropertyValueAggregation implements PropertyAggregation {
	/**
	 * @var string
	 */
	private $aggregation_name;

	/**
	 * @var PropertyFieldMapper
	 */
	private $property;

	/**
	 * @var int The maximum number of term buckets to be returned
	 */
	private $size;

	/**
	 * PropertyAggregation constructor.
	 *
	 * @param PropertyFieldMapper|string $property The property object or name for the aggregation
	 * @param string|null $aggregation_name
	 * @param int|null $size The maximum number of term buckets to be returned
	 */
	public function __construct( $property, string $aggregation_name = null, int $size = null ) {
		if ( is_string( $property ) ) {
			$property = new PropertyFieldMapper( $property );
		}

		if ( !( $property instanceof PropertyFieldMapper ) ) {
			Logger::getLogger()->critical(
				'Tried to construct a PropertyValueAggregation with an invalid property: {property}',
				[
					'property' => $property
				]
			);

			throw new \InvalidArgumentException();
		}

		if ( $aggregation_name === null ) {
			$aggregation_name = $property->getPropertyName();
		}

		$this->aggregation_name = $aggregation_name;
		$this->property = $property;
		$this->size = $size;
	}

	/**
	 * Sets the property object to use for the aggregation.
	 *
	 * @param PropertyFieldMapper $property
	 */
	public function setProperty( PropertyFieldMapper $property ): void {
		$this->property = $property;
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
		$terms_aggregation = new TermsAggregation(
			$this->aggregation_name,
			$this->property->getPropertyField( true )
		);

		if ( $this->size !== null ) {
			$terms_aggregation->addParameter( "size", $this->size );
		}

		return $terms_aggregation;
	}
}
