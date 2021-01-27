<?php

/**
 * WSSearch MediaWiki extension
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

namespace WSSearch;

use Content;
use ContentHandler;
use DatabaseUpdater;
use FatalError;
use LogEntry;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;
use Parser;
use Revision;
use Status;
use Title;
use User;
use WikiPage;

/**
 * Class SearchHooks
 *
 * @package WSSearch
 */
abstract class WSSearchHooks {
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
		$directory = $GLOBALS['wgExtensionDirectory'] . '/WSSearch/sql';
		$type = $updater->getDB()->getType();

		$tables = [
			"search_condition"      => sprintf( "%s/%s/table_search_condition.sql", $directory, $type ),
			"search_facets"         => sprintf( "%s/%s/table_search_facets.sql", $directory, $type ),
			"search_properties"     => sprintf( "%s/%s/table_search_properties.sql", $directory, $type )
		];

		foreach ( $tables as $table ) {
			if ( !file_exists( $table ) ) {
				throw new MWException( wfMessage( 'wssearch-invalid-dbms', $type )->parse() );
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
			$parser->setFunctionHook( "searchEngineConfig", [ self::class, "searchEngineConfigCallback" ] );
			$parser->setFunctionHook( "loadSearchEngine", [ self::class, "loadSearchEngineCallback" ] );
		} catch ( MWException $e ) {
			LoggerFactory::getInstance( "WSSearch" )->error( "Unable to register parser hooks" );
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
	public static function searchEngineConfigCallback( Parser $parser, string ...$parameters ): string {
		if ( !isset( $parameters[0] ) || !$parameters[0] ) {
			return self::error( "wssearch-invalid-engine-config" );
		}

		$condition = array_shift( $parameters );

		$facet_properties = [];
		$result_properties = [];

		foreach ( $parameters as $parameter ) {
			if ( strlen( $parameter ) === 0 ) { continue;
			}

			if ( $parameter[0] === "?" ) {
				// This is a "result property"
				$result_properties[] = ltrim( $parameter, "?" );
			} else {
				// This is a "facet property"
				$facet_properties[] = $parameter;
			}
		}

		try {
			$config = new SearchEngineConfig( $parser->getTitle(), $condition, $facet_properties, $result_properties );
			$config->update( wfGetDB( DB_MASTER ) );
		} catch ( \InvalidArgumentException $exception ) {
			return self::error( "wssearch-invalid-engine-config" );
		}

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
	 * @throws MWException
	 * @throws FatalError
	 */
	public static function loadSearchEngineCallback( Parser $parser, string ...$parameters ): string {
	    $config = SearchEngineConfig::newFromDatabase( $parser->getTitle() );
		$result = self::error( "wssearch-missing-frontend" );
		\Hooks::run( "WSSearchOnLoadFrontend", [ &$result, $config, $parser, $parameters ] );

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
