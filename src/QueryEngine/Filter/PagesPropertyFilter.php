<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Filters pages based on whether the specified property has any of the given
 * (SMW) page IDs as its value. This filter does not take property chains
 * into account.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
 */
class PagesPropertyFilter extends PropertyFilter {
	/**
	 * @inheritDoc
	 * @param int[] $pageIds List of Semantic MediaWiki page IDs
	 */
	public function __construct( string|PropertyFieldMapper $field, private array $pageIds ) {
		parent::__construct( $field );
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function filterToQuery(): BoolQuery {
		$field = sprintf( "%s.wpgID", $this->field->getPID() );
		$termsQuery = new TermsQuery( $field, $this->pageIds );

		$boolQuery = new BoolQuery();
		$boolQuery->add( $termsQuery, BoolQuery::FILTER );

		return $boolQuery;
	}
}
