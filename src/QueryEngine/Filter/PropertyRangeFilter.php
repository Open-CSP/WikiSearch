<?php

namespace WikiSearch\QueryEngine\Filter;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class DateRangeFilter
 *
 * Represents a date range filter to filter in between date properties values. This filter does not take
 * property chains into account.
 *
 * @see ChainedPropertyFilter for a filter that takes property chains into account
 *
 * @package WikiSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-range-query.html
 */
class PropertyRangeFilter extends PropertyFilter {
	/**
	 * @var PropertyFieldMapper The property to apply the filter to
	 */
	private PropertyFieldMapper $property;

	/**
	 * @var array The options for this filter
	 */
	private array $options;

	/**
	 * DateRangeFilter constructor.
	 *
	 * @param PropertyFieldMapper|string $property The property to apply the filter to
	 * @param array $options The options for this filter, for instance:
	 *  [
	 *      RangeQuery::GTE => 10,
	 *      RangeQuery::LT => 20
	 *  ]
	 *
	 *  to filter out everything that is not greater or equal to ten and less than twenty.
	 * @param float|null $boost
	 */
	public function __construct( $property, array $options, float $boost = null ) {
		if ( is_string( $property ) ) {
			$property = new PropertyFieldMapper( $property );
		}

		if ( !( $property instanceof PropertyFieldMapper ) ) {
			Logger::getLogger()->critical(
				'Tried to construct a PropertyRangeFilter with an invalid property: {property}',
				[
					'property' => $property
				]
			);

			throw new InvalidArgumentException( '$property must be of type string or PropertyFieldMapper' );
		}

		$this->property = $property;
		$this->options = $options;

		if ( $boost !== null ) {
			$this->options["boost"] = $boost;
		} elseif ( !isset( $this->options["boost"] ) ) {
			$this->options["boost"] = 1.0;
		}
	}

	/**
	 * Returns the property field mapper corresponding to this filter.
	 *
	 * @return PropertyFieldMapper
	 */
	public function getProperty(): PropertyFieldMapper {
		return $this->property;
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
