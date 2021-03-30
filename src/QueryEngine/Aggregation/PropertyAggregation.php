<?php

namespace WSSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyAggregation
 *
 * Multi-bucket value source based aggregation with buckets of property values.
 *
 * @package WSSearch\QueryEngine\Aggregation
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-terms-aggregation.html
 */
class PropertyAggregation implements Aggregation {
    /**
     * @var string
     */
    private $aggregation_name;

    /**
     * @var PropertyFieldMapper
     */
    private $property;

    /**
     * PropertyAggregation constructor.
     *
     * @param PropertyFieldMapper|string $property The property object or name for the aggregation
     * @param string|null $aggregation_name
     */
    public function __construct( $property, string $aggregation_name = null ) {
        if ( is_string( $property ) ) {
            $property = new PropertyFieldMapper( $property );
        }

        if ( !($property instanceof PropertyFieldMapper)) {
            throw new \InvalidArgumentException();
        }

        if ( $aggregation_name === null ) {
            $aggregation_name = $property->getPropertyName();
        }

        $this->aggregation_name = $aggregation_name;
        $this->property = $property;
    }

    /**
     * Sets the property object to use for the aggregation.
     *
     * @param PropertyFieldMapper $property
     */
    public function setProperty(PropertyFieldMapper $property ) {
        $this->property = $property;
    }

    /**
     * @inheritDoc
     */
    public function toQuery(): AbstractAggregation {
        return new TermsAggregation(
            $this->aggregation_name,
            $this->property->getPropertyField( true )
        );
    }
}