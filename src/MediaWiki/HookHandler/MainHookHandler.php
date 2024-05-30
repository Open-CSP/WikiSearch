<?php

namespace WikiSearch\MediaWiki\HookHandler;

use ContentHandler;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use MediaWiki\Storage\Hook\PageContentSaveHook;
use MWException;
use Title;
use WikiSearch\MediaWiki\ParserFunction\PropertyValuesParserFunction;
use WikiSearch\MediaWiki\ParserFunction\WikiSearchConfigParserFunction;
use WikiSearch\MediaWiki\ParserFunction\WikiSearchFrontParserFunction;
use WikiSearch\MediaWiki\ParserHook\SMWNoIndexParserHook;
use WikiSearch\SearchEngineConfig;
use WikiSearch\WikiSearchServices;

/**
 * Handler for all hooks that implement a HookHandler.
 */
class MainHookHandler implements
	ArticleDeleteCompleteHook,
	PageContentSaveHook,
	LoadExtensionSchemaUpdatesHook,
	ParserFirstCallInitHook,
	BeforePageDisplayHook
{
	/**
	 * @inheritDoc
	 */
	public function onArticleDeleteComplete(
		$wikiPage,
		$user,
		$reason,
		$id,
		$content,
		$logEntry,
		$archivedRevisionCount
	): void {
		SearchEngineConfig::delete( wfGetDB( DB_PRIMARY ), $id );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageContentSave(
		$wikiPage,
		$user,
		$content,
		&$summary,
		$isminor,
		$iswatch,
		$section,
		$flags,
		$status
	): void {
		// Delete any "searchEngineConfig"'s on this page
		SearchEngineConfig::delete( wfGetDB( DB_PRIMARY ), $wikiPage->getId() );

		// Create an appropriate parser
		$parser = MediaWikiServices::getInstance()->getParser();
		$parser->setOptions( $parser->getOptions() ?? \ParserOptions::newFromUserAndLang(
			\RequestContext::getMain()->getUser(),
			\RequestContext::getMain()->getLanguage()
		) );

		$parser->setTitle( $parser->getTitle() ?? Title::newMainPage() );
		$parser->clearState();

		// Reparse the wikitext upon safe with the parser
		$parser->recursiveTagParse( ContentHandler::getContentText( $content ) );
	}

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ): void {
		$directory = $GLOBALS['wgExtensionDirectory'] . '/WikiSearch/sql';
		$type = $updater->getDB()->getType();

		$tables = [
			"search_facets"         => sprintf( "%s/%s/table_search_facets.sql", $directory, $type ),
			"search_properties"     => sprintf( "%s/%s/table_search_properties.sql", $directory, $type ),
			"search_parameters"     => sprintf( "%s/%s/table_search_parameters.sql", $directory, $type )
		];

		foreach ( $tables as $table ) {
			if ( !file_exists( $table ) ) {
				throw new MWException( wfMessage( 'wikisearch-invalid-dbms', $type )->parse() );
			}
		}

		foreach ( $tables as $table_name => $sql_path ) {
			$updater->addExtensionTable( $table_name, $sql_path );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ): void {
		try {
			$parser->setFunctionHook( "wikisearchconfig", [ new WikiSearchConfigParserFunction(), "execute" ] );
			$parser->setFunctionHook( "wikisearchfrontend", [ new WikiSearchFrontParserFunction(), "execute" ] );
			$parser->setFunctionHook( "prop_values", [ new PropertyValuesParserFunction(), "execute" ] );
			$parser->setHook( "smwnoindex", [ new SMWNoIndexParserHook(), "execute" ] );
		} catch ( MWException $e ) {
			WikiSearchServices::getLogger()->getLogger()->alert( 'Unable to register parser hooks: {e}', [
				'e' => $e
			] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $out->getTitle()->getFullText() !== "Special:Search" ) {
			return;
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$search_field_override = $config->get( "WikiSearchSearchFieldOverride" );

		if ( $search_field_override === false ) {
			return;
		}

		// Create Title object to get the full URL
		$title = Title::newFromText( $search_field_override );

		// The search page redirect is invalid
		if ( !$title instanceof Title ) {
			return;
		}

		// Get the current search query
		$search_query = $out->getRequest()->getval( "search", "" );

		// Get the full URL to redirect to
		$redirect_url = $title->getFullUrlForRedirect( [ "term" => $search_query ] );

		// Perform the redirect
		header( "Location: $redirect_url" );

		exit();
	}
}
