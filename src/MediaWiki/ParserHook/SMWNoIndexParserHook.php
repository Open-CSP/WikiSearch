<?php

namespace WikiSearch\MediaWiki\ParserHook;

use Parser;
use PPFrame;

/**
 * Handler for the <smwnoindex> parser hook.
 *
 * @package WikiSearch
 */
class SMWNoIndexParserHook {
	/**
	 * Callback for the parser hook <smwnoindex>.
	 *
	 * @param string $content
	 * @param string ...$_args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public function execute( string $content, array $_args, Parser $parser, PPFrame $frame ): string {
		return sprintf( '<div class="smw-no-index">%s</div>', $parser->recursiveTagParseFully(
			$content,
			$frame
		) );
	}
}
