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
	public const TYPE_UNIFIED = 'unified';
	public const TYPE_PLAIN = 'plain';
	public const TYPE_FVH = 'fvh';

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight;
}
