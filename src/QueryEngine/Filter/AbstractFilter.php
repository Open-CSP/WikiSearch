<?php

namespace WSSearch\QueryEngine\Filter;

use WSSearch\QueryEngine\QueryConvertable;

abstract class AbstractFilter implements QueryConvertable {
	/**
	 * @var bool
	 */
	private $is_post_filter = false;

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
	 * Returns true if and only if this is a "post"-filter.
	 *
	 * @return bool
	 */
	public function isPostFilter(): bool {
		return $this->is_post_filter;
	}
}
