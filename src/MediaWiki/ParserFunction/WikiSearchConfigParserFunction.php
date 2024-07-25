<?php

namespace WikiSearch\MediaWiki\ParserFunction;

use Parser;
use WikiSearch\SearchEngineConfig;

class WikiSearchConfigParserFunction {
	/**
	 * Callback for the #wikisearchconfig parser function.
	 *
	 * @param Parser $parser
	 * @param string ...$args
	 * @return string
	 */
	public function execute( Parser $parser, ...$args ): string {
		try {
			$config = SearchEngineConfig::newFromParameters( $parser->getTitle(), $args );
		} catch ( \InvalidArgumentException $exception ) {
			\WikiSearch\WikiSearchServices::getLogger()->getLogger()->alert( 'Caught exception while creating SearchEngineConfig: {e}', [
				'e' => $exception
			] );

			return self::error( "wikisearch-invalid-engine-config-detailed", [ $exception->getMessage() ] );
		}

		$database = wfGetDB( DB_PRIMARY );

		$config->update( $database );

		return "";
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
