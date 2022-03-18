<?php

namespace WikiSearch\QueryEngine\Highlighter;

use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WikiSearch\QueryEngine\QueryConvertable;

/**
 * Interface Highlighter
 *
 * Represents a class that can be converted to a "Highlight" class that can be applied to a query.
 *
 * @package WikiSearch\QueryEngine\Highlighter
 */
interface Highlighter extends QueryConvertable {
	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight;
}
