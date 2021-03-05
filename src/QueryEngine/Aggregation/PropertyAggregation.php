<?php

namespace WSSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use WSSearch\SMW\Property;

/**
 * Class PropertyAggregation
 *
 * Multi-bucket value source based aggregation with buckets of property values.
 *
 * @package WSSearch\QueryEngine\Aggregation
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-terms-aggregation.html
 */
class PropertyAggregation extends Aggregation {
    /**
     * @var \WSSearch\SMW\Property
     */
    private $property;

    /**
     * PropertyAggregation constructor.
     *
     * @param \WSSearch\SMW\Property|string $property The property object or name for the aggregation
     */
    public function __construct( $property ) {
        if ( is_string( $property ) ) {
            $property = new Property( $property );
        }

        if ( !($property instanceof Property)) {
            throw new \InvalidArgumentException();
        }

        $this->property = $property;

        parent::__construct( $property->getPropertyName() );
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

        $field = "{$this->property->getPropertyField()}$suffix";
        var_dump($field);

        die();

        return new TermsAggregation(
            $this->aggregation_name,
            $field
        );
    }
}