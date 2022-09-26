<?php

/**
 * WikiSearch MediaWiki extension
 * Copyright (C) 2021  Wikibase Solutions
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace WikiSearch\API;

use ApiBase;
use ApiUsageException;
use Elasticsearch\ClientBuilder;
use MWException;
use Title;
use WikiSearch\QueryEngine\Factory\QueryEngineFactory;
use WikiSearch\QueryEngine\Filter\PageFilter;
use WikiSearch\QueryEngine\Filter\SearchTermFilter;
use WikiSearch\QueryEngine\Highlighter\FragmentHighlighter;
use WikiSearch\QueryEngine\QueryEngine;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class ApiQueryWikiSearchHighlight
 *
 * @package WikiSearch
 */
class ApiQueryWikiSearchHighlight extends ApiQueryWikiSearchBase {
	/**
	 * @inheritDoc
	 *
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		$this->checkUserRights();

		$query = $this->getParameter( "query" );
		$properties = $this->getParameter( "properties" );
		$limit = $this->getParameter( "limit" );
		$page_id = $this->getParameter( "page_id" );

		$size = $this->getParameter( "size" );
		$highlighter_type = $this->getParameter( "highlighter_type" );

		if ( $size === null ) {
			$size = 250;
		}

		$properties = explode( ",", $properties );
		$properties = array_map( function ( string $property ): PropertyFieldMapper {
			return new PropertyFieldMapper( $property );
		}, $properties );

		$title = Title::newFromID( $page_id );

		if ( !( $title instanceof Title ) || !$title->exists() ) {
			$this->dieWithError( $this->msg( "wikisearch-api-invalid-pageid" ) );
		}

		$highlighter = new FragmentHighlighter( $properties, $highlighter_type, $size, $limit );
		$search_term_filter = new SearchTermFilter( $query, $properties );
		$page_filter = new PageFilter( $title );

		$query_engine = $this->getEngine();

		$query_engine->addHighlighter( $highlighter );
		$query_engine->addConstantScoreFilter( $page_filter );
		$query_engine->addConstantScoreFilter( $search_term_filter );

		$results = ClientBuilder::create()
			->setHosts( QueryEngineFactory::fromNull()->getElasticHosts() )
			->build()
			->search( $query_engine->toArray() );

		$this->getResult()->addValue( null, 'words', $this->wordsFromResult( $results ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'query' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'properties' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'page_id' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'size' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 250
			],
			'highlighter_type' => [
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}

	/**
	 * Creates the QueryEngine from the current request.
	 *
	 * @return QueryEngine
	 */
	private function getEngine(): QueryEngine {
		return QueryEngineFactory::fromNull();
	}

	/**
	 * Converts an ElasticSearch result to a list of highlight words.
	 *
	 * @param array $result
	 * @return array
	 */
	private function wordsFromResult( array $result ): array {
		$words = [];

		$hits = $result["hits"]["hits"];
		foreach ( $hits as $hit ) {
			if ( !isset( $hit["highlight"] ) ) {
				continue;
			}

			$highlights = $hit["highlight"];

			foreach ( $highlights as $highlight ) {
				$words = array_merge( $words, $highlight );
			}
		}

		$highlighted_source = implode( ' ', $words );

		preg_match_all( "/(?<=\{@@_HIGHLIGHT_@@).*?(?=@@_HIGHLIGHT_@@})/", $highlighted_source, $matches );

		if ( isset( $matches[0] ) ) {
			$words = $matches[0];
		}

		return array_values( array_unique( $words ) );
	}
}
