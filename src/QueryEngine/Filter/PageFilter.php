<?php

namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PageFilter
 *
 * Filters out everything except for the specified page.
 *
 * @package WSSearch\QueryEngine\Filter
 */
class PageFilter extends AbstractFilter {
	/**
	 * @var \Title
	 */
	private $title;

	/**
	 * PageFilter constructor.
	 *
	 * @param \Title $title
	 */
	public function __construct( \Title $title ) {
		$this->title = $title;
	}

	/**
	 * Sets the page to filter on.
	 *
	 * @param \Title $title
	 */
	public function setPage( \Title $title ) {
		$this->title = $title;
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function toQuery(): BoolQuery {
		$term_query = new TermQuery(
			"subject.title",
			$this->title->getFullText()
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