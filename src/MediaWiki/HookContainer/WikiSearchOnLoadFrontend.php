<?php

namespace WikiSearch\MediaWiki\HookContainer;

use Parser;
use WikiSearch\SearchEngineConfig;

interface WikiSearchOnLoadFrontend {
	/**
	 * Called when loading the WikiSearch frontend through the #wikisearchfront parser function.
	 *
	 * @param string &$result The value of the parser function.
	 * @param SearchEngineConfig $config The SearchEngine configuration.
	 * @param Parser $parser The MediaWiki parser.
	 * @param array $args Any additional arguments passed to the parser function.
	 * @return void
	 */
	public function onWikiSearchOnLoadFrontend(
		string &$result,
		SearchEngineConfig $config,
		Parser $parser,
		array $args
	): void;
}
