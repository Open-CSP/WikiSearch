<?php

namespace WikiSearch;

use Parser;
use PPFrame;

/**
 * Class PropertyValuesParserFunction
 *
 * @package WikiSearch
 */
class SMWNoIndexParserHook {
	/**
	 * Callback for the parser hook <smwnoindex>.
	 *
	 * @param string $content
	 * @param string ...$args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public function execute( string $content, array $args, Parser $parser, PPFrame $frame ): string {
		return sprintf( '<div class="smw-no-index">%s</div>', $parser->recursiveTagParseFully(
			$content,
			$frame
		) );
	}
}
