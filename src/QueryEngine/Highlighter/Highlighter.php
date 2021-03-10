<?php


namespace WSSearch\QueryEngine\Highlighter;

use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WSSearch\QueryEngine\QueryConvertable;

/**
 * Interface Highlighter
 *
 * Represents a class that can be converted to a "Highlight" class that can be applied to a query.
 *
 * @package WSSearch\QueryEngine\Highlighter
 */
interface Highlighter extends QueryConvertable {
    /**
     * @inheritDoc
     */
    public function toQuery(): Highlight;
}