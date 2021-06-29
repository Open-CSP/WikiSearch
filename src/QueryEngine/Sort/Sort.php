<?php

namespace WSSearch\QueryEngine\Sort;

use ONGR\ElasticsearchDSL\BuilderInterface;
use WSSearch\QueryEngine\QueryConvertable;

/**
 * Interface Sort
 *
 * Represents a class that can be converted to a sort class that can be applied to a query.
 *
 * @package WSSearch\QueryEngine\Highlighter
 */
interface Sort extends QueryConvertable {
	/**
	 * @inheritDoc
	 */
	public function toQuery(): BuilderInterface;
}
