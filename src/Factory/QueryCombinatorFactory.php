<?php

namespace WikiSearch\Factory;

use WikiSearch\QueryEngine\QueryCombinator;

class QueryCombinatorFactory {
	/**
	 * Construct a new QueryCombinator instance.
	 *
	 * @return QueryCombinator
	 */
	public function newQueryCombinator(): QueryCombinator {
		return new QueryCombinator();
	}
}
