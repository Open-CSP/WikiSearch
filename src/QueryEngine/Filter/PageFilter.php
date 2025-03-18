<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use Title;
use WikiSearch\Logger;
use WikiSearch\SMW\WikiPageObjectIdLookup;

/**
 * Filters out everything except for the specified title.
 */
class PageFilter extends AbstractFilter {
	/**
	 * PageFilter constructor.
	 *
	 * @param Title $title
	 */
	public function __construct(
        private Title $title
    ) {}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function filterToQuery(): BoolQuery {
		$objectId = WikiPageObjectIdLookup::getObjectIdForTitle( $this->title ) ?? -1;

		$termQuery = new TermQuery( "_id", $objectId );

		$boolQuery = new BoolQuery();
		$boolQuery->add( $termQuery, BoolQuery::FILTER );

		return $boolQuery;
	}
}
