<?php

namespace WikiSearch\MediaWiki;

use MediaWiki\HookContainer\HookContainer;
use Parser;
use WikiSearch\MediaWiki\HookContainer\WikiSearchOnLoadFrontend;
use WikiSearch\SearchEngineConfig;

/**
 * This class is responsible for running hooks defined by WikiSearch.
 */
class HookRunner implements WikiSearchOnLoadFrontend {
	/**
	 * @var HookContainer
	 */
	private HookContainer $hookContainer;

	/**
	 * @param HookContainer $hookContainer
	 */
	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onWikiSearchOnLoadFrontend(
		string &$result,
		SearchEngineConfig $config,
		Parser $parser,
		array $args
	): void {
		$this->hookContainer->run( "WikiSearchOnLoadFrontend", [ &$result, $config, $parser, $args ] );
	}
}


