<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WikiSearch\QueryEngine\QueryConvertable;

abstract class Filter implements QueryConvertable {
	/**
	 * @var bool
	 */
	private bool $isPostFilter = false;

	/**
	 * @var bool
	 */
	private bool $isNegated = false;

    /**
     * Sets the filter to be a "post"-filter.
     *
     * @param bool $isPostFilter
     * @return Filter
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/filter-search-results.html#post-filter
     */
	public function setPostFilter( bool $isPostFilter = true ): self {
		$this->isPostFilter = $isPostFilter;

        return $this;
	}

    /**
     * Negates this filter.
     *
     * @param bool $isNegated
     * @return Filter
     */
	public function setNegated( bool $isNegated = true ): self {
		$this->isNegated = $isNegated;

        return $this;
	}

	/**
	 * Returns true if and only if this is a "post"-filter.
	 *
	 * @return bool
	 */
	public function isPostFilter(): bool {
		return $this->isPostFilter;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): BoolQuery {
		$query = new BoolQuery();
		$query->add( $this->filterToQuery(), $this->isNegated ? BoolQuery::MUST_NOT : BoolQuery::MUST );

		return $query;
	}

	/**
	 * Returns the filter as an ElasticSearch query.
	 *
	 * @return BoolQuery
	 */
	abstract protected function filterToQuery(): BoolQuery;
}
