<?php

namespace WSSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\RangeAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use WSSearch\SMW\Property;

/**
 * Class PropertyRangeAggregation
 *
 * A multi-bucket value source based aggregation that enables the user to define a
 * set of ranges - each representing a bucket.
 *
 * @package WSSearch\QueryEngine\Aggregation
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-range-aggregation.html
 */
class PropertyRangeAggregation implements Aggregation {
    /**
     * @var string
     */
    private $aggregation_name;

    /**
     * @var Property
     */
    private $property;

    /**
     * @var array
     */
    private $ranges;

    /**
     * PropertyAggregation constructor.
     *
     * @param Property|string $property The property object or name for the aggregation
     * @param array $ranges
     * @param string|null $aggregation_name
     */
    public function __construct( $property, array $ranges, string $aggregation_name = null ) {
        if ( is_string( $property ) ) {
            $property = new Property( $property );
        }

        if ( !($property instanceof Property)) {
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
     * @param Property $property
     */
    public function setProperty( Property $property ) {
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
     * @inheritDoc
     */
    public function toQuery(): AbstractAggregation {
        $property_type = $this->property->getPropertyType();

        // TODO: Make this more general
        switch ($property_type) {
            case "numField":
                // numField properties do not have a ".keyword"
                $suffix = "";
                break;
            default:
                $suffix = ".keyword";
                break;
        }

        return new RangeAggregation(
            $this->aggregation_name,
            "{$this->property->getPropertyField()}$suffix",
            $this->ranges
        );
    }
}