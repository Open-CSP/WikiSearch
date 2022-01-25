<?php

namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use Title;
use WSSearch\Logger;
use WSSearch\SMW\WikiPageObjectIdLookup;

/**
 * Class PageFilter
 *
 * Filters out everything except for the specified page.
 *
 * @package WSSearch\QueryEngine\Filter
 */
class PageFilter extends AbstractFilter {
	/**
	 * @var Title
	 */
	private Title $title;

	/**
	 * PageFilter constructor.
	 *
	 * @param Title $title
	 */
	public function __construct( Title $title ) {
		$this->title = $title;
	}

	/**
	 * Sets the page to filter on.
	 *
	 * @param Title $title
	 */
	public function setPage( Title $title ): void {
		$this->title = $title;
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function toQuery(): BoolQuery {
		$object_id = WikiPageObjectIdLookup::getObjectIdForTitle( $this->title );

		if ( $object_id === null ) {
			$object_id = -1;

			Logger::getLogger()->alert( 'Failed to lookup object ID for Title {title}, falling back to -1', [
				'title' => $this->title->getFullText()
			] );
		}

		$term_query = new TermQuery(
			"_id",
			$object_id
		);

		$bool_query = new BoolQuery();
		$bool_query->add( $term_query, BoolQuery::FILTER );

		/*
		 * Example of such a query:
		 *
		 *  "bool": {
		 *      "filter": {
		 *          "term": {
		 *              "P:0.wpgID": 0
		 *          }
		 *      }
		 *  }
		 */

		return $bool_query;
	}
}
