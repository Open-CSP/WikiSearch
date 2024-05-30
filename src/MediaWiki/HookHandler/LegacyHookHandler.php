<?php

namespace WikiSearch\MediaWiki\HookHandler;

use MediaWiki\MediaWikiServices;
use MWException;
use SMW\PropertyRegistry;
use SMW\SemanticData;
use SMW\Store;
use WikiPage;
use WikiSearch\Scribunto\ScribuntoLuaLibrary;
use WikiSearch\SMW\PropertyInitializer;

/**
 * Handler for all hooks that do not yet implement a HookHandler.
 */
class LegacyHookHandler {
	/**
	 * Hook to add additional predefined properties.
	 *
	 * @param PropertyRegistry $propertyRegistry
	 * @return void
	 * @link https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/docs/examples/hook.property.initproperties.md
	 */
	public static function onInitProperties( PropertyRegistry $propertyRegistry ): void {
		$propertyInitializer = new PropertyInitializer( $propertyRegistry );
		$propertyInitializer->initProperties();
	}

	/**
	 * Allow extensions to add libraries to Scribunto.
	 *
	 * @link https://www.mediawiki.org/wiki/Extension:Scribunto/Hooks/ScribuntoExternalLibraries
	 *
	 * @param string $engine
	 * @param array &$extraLibraries
	 * @return bool
	 */
	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ): bool {
		if ( $engine !== 'lua' ) {
			// Don't mess with other engines
			return true;
		}

		$extraLibraries['wikisearch'] = ScribuntoLuaLibrary::class;

		return true;
	}

	/**
	 * Hook to extend the SemanticData object before the update is completed.
	 *
	 * @param Store $store
	 * @param SemanticData $semanticData
	 * @return void
	 * @throws MWException
	 * @link https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/docs/technical/hooks/hook.store.beforedataupdatecomplete.md
	 */
	public static function onBeforeDataUpdateComplete( Store $store, SemanticData $semanticData ) {
		$subjectTitle = $semanticData->getSubject()->getTitle();

		if ( $subjectTitle === null ) {
			return;
		}

		$page = WikiPage::factory( $subjectTitle );

		if ( $page === null ) {
			return;
		}

		$content = $page->getContent();

		if ( $content === null ) {
			return;
		}

		$mwServices = MediaWikiServices::getInstance();
		if ( method_exists( $mwServices, 'getContentRenderer' ) ) {
			// MW1.38+
			$output = $mwServices->getContentRenderer()->getParserOutput( $content, $subjectTitle );
		} else {
			$output = $content->getParserOutput( $subjectTitle );
		}

		foreach ( PropertyInitializer::getAnnotators() as $annotator ) {
			// Decorate the semantic data object with the annotation
			$annotator::addAnnotation( $content, $output, $semanticData );
		}
	}
}
