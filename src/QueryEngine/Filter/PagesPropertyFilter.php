<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use WSSearch\SearchEngineException;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyPagesFilter
 *
 * Filters pages based on whether the specified property has any of the given
 * (SMW) page IDs as its value. This filter does not take property chains
 * into account.
 *
 * @see ChainedPropertyFilter for a filter that takes property chains into account
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
 */
class PagesPropertyFilter implements Filter {
    /**
     * @var PropertyFieldMapper
     */
    private $property;

    /**
     * @var int[]
     */
    private $property_values;

    /**
     * PropertyTermsFilter constructor.
     *
     * @param PropertyFieldMapper $property The property that should match the given page IDs
     * @param array $pages Array of (SMW) page IDs
     * @throws SearchEngineException
     */
    public function __construct( PropertyFieldMapper $property, array $pages ) {
        if ( $property->getPropertyType() !== "wpgField" ) {
            throw new SearchEngineException( "Cannot apply PropertyPagesFilter to non-page property" );
        }

        foreach ( $pages as $page ) {
        	if ( !is_int( $page ) ) {
        		throw new \InvalidArgumentException();
			}
		}

        $this->property = $property;
        $this->property_values = $pages;
    }

    /**
     * Sets the property this filter will filter on.
     *
     * @param PropertyFieldMapper $property
     */
    public function setPropertyName( PropertyFieldMapper $property ) {
        $this->property = $property;
    }

    /**
     * Sets the pages the given property should have.
     *
     * @param int[] $property_values
     */
    public function setPropertyValues( array $property_values ) {
		foreach ( $property_values as $value ) {
			if ( !is_int( $value ) ) {
				throw new \InvalidArgumentException();
			}
		}

        $this->property_values = $property_values;
    }

    /**
     * Converts the object to a BuilderInterface for use in the QueryEngine.
     *
     * @return BoolQuery
     */
    public function toQuery(): BoolQuery {
        $terms_query = new TermsQuery( $this->property->getPropertyPageFieldIdentifier(), $this->property_values );

        $bool_query = new BoolQuery();
        $bool_query->add( $terms_query, BoolQuery::FILTER );

        return $bool_query;
    }
}