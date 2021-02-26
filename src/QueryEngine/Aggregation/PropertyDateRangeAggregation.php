<?php


namespace WSSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\Bucketing\DateRangeAggregation;
use WSSearch\QueryEngine\Property;

/**
 * Class PropertyDateRangeAggregation
 *
 * @package WSSearch\QueryEngine\Aggregation
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-daterange-aggregation.html
 */
class PropertyDateRangeAggregation extends Aggregation {
    /**
     * @var array[] The minimum value of the property
     */
    private $ranges;

    /**
     * @var Property The property to apply the filter to
     */
    private $property;

    /**
     * DateRangeFilter constructor.
     *
     * @param string $aggregation_name
     * @param Property|string $property The property to apply the filter to
     * @param array $ranges The date ranges to aggregate
     */
    public function __construct( string $aggregation_name, $property, array $ranges ) {
        if ( is_string( $property ) ) {
            $property = new Property( $property );
        }

        if ( !($property instanceof Property)) {
            throw new \InvalidArgumentException();
        }

        $this->property = $property;
        $this->ranges = $ranges;

        parent::__construct( $aggregation_name );
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
     * Sets the date ranges to aggregate.
     *
     * @param array[] $ranges
     */
    public function setRanges( array $ranges ) {
        $this->ranges = $ranges;
    }

    /**
     * @inheritDoc
     */
    public function toQuery(): DateRangeAggregation {
        return new DateRangeAggregation(
            $this->aggregation_name,
            $this->property->getPropertyField(),
            $this->ranges
        );
    }
}