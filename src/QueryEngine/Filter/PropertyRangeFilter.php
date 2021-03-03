<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use WSSearch\SMW\Property;

/**
 * Class DateRangeFilter
 *
 * Represents a date range filter to filter in between date properties values.
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-range-query.html
 */
class PropertyRangeFilter extends Filter {
    /**
     * @var int The minimum value of the property
     */
    private $gte;

    /**
     * @var int The maximum value of the property
     */
    private $lte;

    /**
     * @var \WSSearch\SMW\Property The property to apply the filter to
     */
    private $property;

    /**
     * @var float The boost value of the query
     */
    private $boost;

    /**
     * DateRangeFilter constructor.
     *
     * @param \WSSearch\SMW\Property|string $property The property to apply the filter to
     * @param int $gte The minimum value of the property
     * @param int $lte The maximum value of the property
     */
    public function __construct( $property, int $gte, int $lte, float $boost = 1.0 ) {
        if ( is_string( $property ) ) {
            $property = new Property( $property );
        }

        if ( !($property instanceof Property)) {
            throw new \InvalidArgumentException();
        }

        $this->property = $property;
        $this->gte = $gte;
        $this->lte = $lte;
        $this->boost = $boost;
    }

    /**
     * @inheritDoc
     */
    public function toQuery(): BoolQuery {
        $range_query = new RangeQuery(
            $this->property->getPropertyField(),
            [
                RangeQuery::GTE => $this->gte,
                RangeQuery::LTE => $this->lte,
                "boost" => $this->boost
            ]
        );

        $bool_query = new BoolQuery();
        $bool_query->add( $range_query, BoolQuery::MUST );

        /*
         * Example of such a query:
         *
         * "bool": {
         *      "must": [
         *          {
         *              "range": {
         *                  "P:0.wpgField": {
         *                      "gte": "6 ft"
         *                  }
         *              }
         *          }
         *      ]
         *  }
         */

        return $bool_query;
    }
}