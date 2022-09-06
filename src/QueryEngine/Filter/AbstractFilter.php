<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WikiSearch\QueryEngine\QueryConvertable;

abstract class AbstractFilter implements QueryConvertable {
	/**
	 * @var bool
	 */
	private bool $is_post_filter = false;

	/**
	 * @var bool
	 */
	private bool $is_negated = false;

	/**
	 * Sets the filter to be a "post"-filter.
	 *
	 * @param bool $set
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/filter-search-results.html#post-filter
	 */
	public function setPostFilter( bool $set = true ) {
		$this->is_post_filter = $set;
	}

	/**
	 * Negates this filter.
	 *
	 * @param bool $set
	 * @return void
	 */
	public function setNegated( bool $set = true ) {
		$this->is_negated = $set;
	}

	/**
	 * Returns true if and only if this is a "post"-filter.
	 *
	 * @return bool
	 */
	public function isPostFilter(): bool {
		return $this->is_post_filter;
	}

	/**
	 * Returns true if and only if this filter is negated.
	 *
	 * @return bool
	 */
	public function isNegated(): bool {
		return $this->is_negated;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): BoolQuery {
		$filterQuery = $this->filterToQuery();
		$queryType = $this->is_negated ? BoolQuery::MUST_NOT : BoolQuery::MUST;

		$query = new BoolQuery();
		$query->add( $filterQuery, $queryType );

		return $query;
	}

	/**
	 * Returns the filter as an ElasticSearch query.
	 *
	 * @return BoolQuery
	 */
	abstract protected function filterToQuery(): BoolQuery;
}
