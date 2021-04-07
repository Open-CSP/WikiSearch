<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class DateRangeFilter
 *
 * Represents a date range filter to filter in between date properties values. This filter does not take
 * property chains into account.
 *
 * @see ChainedPropertyFilter for a filter that takes property chains into account
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-range-query.html
 */
class PropertyRangeFilter extends AbstractFilter {
    /**
     * @var \WSSearch\SMW\PropertyFieldMapper The property to apply the filter to
     */
    private $property;

    /**
     * @var array The options for this filter
     */
    private $options;

    /**
     * DateRangeFilter constructor.
     *
     * @param \WSSearch\SMW\PropertyFieldMapper|string $property The property to apply the filter to
     * @param array $options The options for this filter, for instance:
     *  [
     *      RangeQuery::GTE => 10,
     *      RangeQuery::LT => 20
     *  ]
     *
     *  to filter out everything that is not greater or equal to ten and less than twenty.
     * @param float $boost
     */
    public function __construct( $property, array $options, float $boost = null ) {
        if ( is_string( $property ) ) {
            $property = new PropertyFieldMapper( $property );
        }

        if ( !($property instanceof PropertyFieldMapper)) {
            throw new \InvalidArgumentException();
        }

        $this->property = $property;
        $this->options = $options;

        if ( $boost !== null ) {
            $this->options["boost"] = $boost;
        } else if ( !isset( $this->options["boost"] ) ) {
            $this->options["boost"] = 1.0;
        }
    }

    /**
     * @inheritDoc
     */
    public function toQuery(): BoolQuery {
        $range_query = new RangeQuery(
            $this->property->getPropertyField(),
            $this->options
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