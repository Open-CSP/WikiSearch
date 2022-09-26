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

namespace WikiSearch;

use Content;
use ContentHandler;
use DatabaseUpdater;
use LogEntry;
use MediaWiki\MediaWikiServices;
use MWException;
use OutputPage;
use Parser;
use Revision;
use Skin;
use SMW\PropertyRegistry;
use SMW\SemanticData;
use SMW\Store;
use Status;
use Title;
use User;
use WikiPage;
use WikiSearch\SMW\Annotators\Annotator;
use WikiSearch\SMW\AnnotatorStore;
use WikiSearch\SMW\PropertyInitializer;

/**
 * Class SearchHooks
 *
 * @package WikiSearch
 */
abstract class WikiSearchHooks {
	/**
	 * Occurs after the delete article request has been processed.
	 *
	 * @param WikiPage $article The article that was deleted
	 * @param User $user The user that deleted the article
	 * @param string $reason The reason the article was deleted
	 * @param int $id ID of the article that was deleted
	 * @param Content|null $content The content of the deleted article, or null in case of an error
	 * @param LogEntry $log_entry The log entry used to record the deletion
	 * @param int $archived_revision_count The number of revisions archived during the page delete
	 */
	public static function onArticleDeleteComplete(
		WikiPage $article,
		User $user,
		string $reason,
		int $id,
		$content,
		LogEntry $log_entry,
		int $archived_revision_count
	) {
		SearchEngineConfig::delete( wfGetDB( DB_MASTER ), $id );
	}

	/**
	 * Occurs after the save page request has been processed.
	 *
	 * @param WikiPage $article WikiPage modified
	 * @param User $user User performing the modification
	 * @param Content $main_content New content, as a Content object
	 * @param string $summary Edit summary/comment
	 * @param bool $is_minor Whether or not the edit was marked as minor
	 * @param mixed $is_watch
	 * @param mixed $section
	 * @param mixed $flags
	 * @param Revision|null $revision Revision object of the saved content
	 * @param Status $status Status object about to be returned by doEditContent()
	 * @param mixed $original_revision_id
	 * @param mixed $undid_revision_id
	 *
	 * @throws MWException
	 */
	public static function onPageContentSaveComplete(
		WikiPage $article,
		User $user,
		Content $main_content,
		string $summary,
		bool $is_minor,
		$is_watch,
		$section,
		$flags,
		$revision,
		Status $status,
		$original_revision_id,
		$undid_revision_id
	) {
		// Delete any "searchEngineConfig"'s on this page
		SearchEngineConfig::delete( wfGetDB( DB_MASTER ), $article->getId() );

		// Create an appropriate parser
		$parser = MediaWikiServices::getInstance()->getParser();
		$parser->mOptions = $parser->getOptions() ?? \ParserOptions::newFromUserAndLang(
			\RequestContext::getMain()->getUser(),
			\RequestContext::getMain()->getLanguage()
		);

		$parser->setTitle( $parser->mTitle ?? Title::newMainPage() );
		$parser->clearState();

		// Reparse the wikitext upon safe with the parser
		$parser->recursiveTagParse( ContentHandler::getContentText( $main_content ) );
	}

	/**
	 * Called whenever schema updates are required. Updates the database schema.
	 *
	 * @param DatabaseUpdater $updater
	 * @throws MWException
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
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
	 * Called when the parser initializes for the first time.
	 *
	 * @param Parser $parser Parser object being initialized
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		try {
			$parser->setFunctionHook( "WikiSearchConfig", [ self::class, "searchConfigCallback" ] );
			$parser->setFunctionHook( "WikiSearchFrontend", [ self::class, "searchEngineFrontendCallback" ] );
			$parser->setFunctionHook( "prop_values", [ new PropertyValuesParserFunction(), "execute" ] );
		} catch ( MWException $e ) {
			Logger::getLogger()->alert( 'Unable to register parser hooks: {e}', [
				'e' => $e
			] );
		}
	}

	/**
	 * Allows last minute changes to the output page, e.g. adding of CSS or
	 * JavaScript by extensions.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
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

        $output = $content->getParserOutput( $subjectTitle );

        foreach ( Annotator::ANNOTATORS as $annotator ) {
            // Decorate the semantic data object with the annotation
            $annotator::addAnnotation( $content, $output, $semanticData );
        }
    }

	/**
	 * Callback for the '#searchEngineConfig' parser function. Responsible for the creation of the
	 * appropriate SearchEngineConfig object and for storing that object in the database.
	 *
	 * @param Parser $parser
	 * @param string ...$parameters
	 * @return string
	 */
	public static function searchConfigCallback( Parser $parser, string ...$parameters ): string {
		try {
			$config = SearchEngineConfig::newFromParameters( $parser->getTitle(), $parameters );
		} catch ( \InvalidArgumentException $exception ) {
			Logger::getLogger()->alert( 'Caught exception while creating SearchEngineConfig: {e}', [
				'e' => $exception
			] );

			return self::error( "wikisearch-invalid-engine-config-detailed", [ $exception->getMessage() ] );
		}

		$database = wfGetDB( DB_PRIMARY );

		$config->update( $database );

		return "";
	}

	/**
	 * Callback for the '#loadSearchEngine' parser function. Responsible for loading the frontend
	 * of the extension.
	 *
	 * @param Parser $parser
	 * @param string ...$parameters
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function searchEngineFrontendCallback( Parser $parser, string ...$parameters ): string {
		$config = SearchEngineConfig::newFromDatabase( $parser->getTitle() );

		if ( $config === null ) {
			Logger::getLogger()->alert( 'Tried to load front-end with an invalid SearchEngineConfig' );

			return self::error( "wikisearch-invalid-engine-config" );
		}

		$result = self::error( "wikisearch-missing-frontend" );

		\Hooks::run( "WikiSearchOnLoadFrontend", [ &$result, $config, $parser, $parameters ] );

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
			'span', [ 'class' => 'error' ], wfMessage( $message, $params )->toString()
		);
	}
}
