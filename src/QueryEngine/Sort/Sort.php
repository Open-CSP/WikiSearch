<?php

namespace WikiSearch\QueryEngine\Sort;

use ONGR\ElasticsearchDSL\BuilderInterface;
use WikiSearch\QueryEngine\QueryConvertable;

/**
 * Represents a class that can be converted to a sort class that can be applied to a query.
 */
interface Sort extends QueryConvertable {
	/**
	 * @inheritDoc
	 */
	public function toQuery(): BuilderInterface;
}
