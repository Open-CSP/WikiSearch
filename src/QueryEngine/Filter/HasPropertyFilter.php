<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;

/**
 * Filters pages based on whether they have the specified property.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-query.html
 */
class HasPropertyFilter extends PropertyFilter {
	public function filterToQuery(): BoolQuery {
		$existsQuery = new ExistsQuery(
			$this->field->getPropertyField()
		);

		$boolQuery = new BoolQuery();
		$boolQuery->add( $existsQuery, BoolQuery::FILTER );

		return $boolQuery;
	}
}
