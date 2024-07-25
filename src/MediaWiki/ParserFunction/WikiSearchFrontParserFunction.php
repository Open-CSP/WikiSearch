<?php

namespace WikiSearch\MediaWiki\ParserFunction;

use Parser;
use WikiSearch\SearchEngineConfig;
use WikiSearch\WikiSearchServices;

class WikiSearchFrontParserFunction {
	/**
	 * Callback for the parser function {{#wikisearchfront}}.
	 *
	 * @param Parser $parser
	 * @param string ...$args
	 * @return string
	 */
	public function execute( Parser $parser, ...$args ): string {
		$config = SearchEngineConfig::newFromDatabase( $parser->getTitle() );

		if ( $config === null ) {
			\WikiSearch\WikiSearchServices::getLogger()->getLogger()->alert( 'Tried to load front-end with an invalid SearchEngineConfig' );
			return self::error( "wikisearch-invalid-engine-config" );
		}

		$result = self::error( "wikisearch-missing-frontend" );
		WikiSearchServices::getHookRunner()->onWikiSearchOnLoadFrontend( $result, $config, $parser, $args );

		return $result;
	}

	/**
	 * Returns a formatted error message.
	 *
	 * @param string $message
	 * @param array $params
	 * @return string
	 */
	private static function error( string $message, array $params = [] ): string {
		return \Html::rawElement(
			'span', [ 'class' => 'error' ], wfMessage( $message, $params )->text()
		);
	}
}
