<?php

namespace WikiSearch\QueryEngine\Filter;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\FuzzyQuery;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyFuzzyValueFilter
 *
 * Filters pages based on the values of their properties using fuzzy matching. This filter does not take
 * property chains into account.
 *
 * @see ChainedPropertyFilter for a filter that takes property chains into account
 *
 * @package WikiSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-fuzzy-query.html
 */
class PropertyFuzzyValueFilter extends PropertyFilter {
	/**
	 * @var PropertyFieldMapper The property to filter on
	 */
	private PropertyFieldMapper $property;

	/**
	 * @var string|int The fuzziness to use, or "AUTO"
	 */
	private $fuzziness;

	/**
	 * @var mixed The value the property to filter on
	 */
	private $property_value;

	/**
	 * PropertyFuzzyValueFilter constructor.
	 *
	 * @param PropertyFieldMapper|string $property The name or object of the property to filter on
	 * @param string $property_value The value the property to filter on
	 * @param string|int $fuzziness The fuzziness to use, or "AUTO"
	 */
	public function __construct( $property, string $property_value, $fuzziness = "AUTO" ) {
		if ( is_string( $property ) ) {
			$property = new PropertyFieldMapper( $property );
		}

		if ( !( $property instanceof PropertyFieldMapper ) ) {
			Logger::getLogger()->critical(
				'Tried to construct a PropertyFuzzyValueFilter with an invalid property: {property}',
				[
					'property' => $property
				]
			);

			throw new InvalidArgumentException( '$property must be of type string or PropertyFieldMapper' );
		}

		if ( $fuzziness !== "AUTO" && ( !is_int( $fuzziness ) || $fuzziness < 0 ) ) {
			Logger::getLogger()->critical(
				'Tried to construct a PropertyFuzzyValueFilter with an invalid fuzziness parameter: {fuzziness}',
				[
					'fuzziness' => $fuzziness
				]
			);

			throw new InvalidArgumentException(
				'$fuzziness must be "AUTO" or a positive integer'
			);
		}

		$this->property = $property;
		$this->property_value = $property_value;
		$this->fuzziness = $fuzziness;
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
	 * Sets the property this filter will filter on.
	 *
	 * @param PropertyFieldMapper $property
	 */
	public function setPropertyName( PropertyFieldMapper $property ): void {
		$this->property = $property;
	}

	/**
	 * Sets the value of the property this filter will filter on.
	 *
	 * @param string $property_value
	 */
	public function setPropertyValue( string $property_value ): void {
		$this->property_value = $property_value;
	}

	/**
	 * Sets the value of the fuzziness parameter this filter will use.
	 *
	 * @param string|int $fuzziness The fuzziness to use, or "AUTO"
	 * @return void
	 */
	public function setFuzziness( $fuzziness ): void {
		if ( $fuzziness !== "AUTO" && ( !is_int( $fuzziness ) || $fuzziness < 0 ) ) {
			Logger::getLogger()->critical(
				'Tried to set an invalid value for fuzziness: {fuzziness}',
				[
					'fuzziness' => $fuzziness
				]
			);

			throw new InvalidArgumentException(
				'$fuzziness must be "AUTO" or a positive integer'
			);
		}
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function filterToQuery(): BoolQuery {
		$field = $this->property->hasKeywordSubfield() ?
			$this->property->getKeywordField() :
			$this->property->getPropertyField();

		$parameters = [
			"fuzziness" => $this->fuzziness
		];

		$fuzzy_query = new FuzzyQuery( $field, $this->property_value, $parameters );

		$bool_query = new BoolQuery();
		$bool_query->add( $fuzzy_query, BoolQuery::FILTER );

		/*
		 * Example of such a query:
		 *
		 *  "bool": {
		 *      "filter": {
		 *          "fuzzy": {
		 *              "P:0.wpgID": 0
		 *          }
		 *      }
		 *  }
		 */

		return $bool_query;
	}
}
