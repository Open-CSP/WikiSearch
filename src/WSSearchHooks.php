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
use LogEntry;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;
use OutputPage;
use Parser;
use Revision;
use Skin;
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
			"search_facets"         => sprintf( "%s/%s/table_search_facets.sql", $directory, $type ),
			"search_properties"     => sprintf( "%s/%s/table_search_properties.sql", $directory, $type ),
            "search_parameters"     => sprintf( "%s/%s/table_search_parameters.sql", $directory, $type )
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
			$parser->setFunctionHook( "WSSearchConfig", [ self::class, "searchConfigCallback"] );
			$parser->setFunctionHook( "WSSearchFrontend", [ self::class, "searchEngineFrontendCallback"] );
			$parser->setFunctionHook( "verwijzingen", [ self::class, "verwijzingenCallback" ] );
		} catch ( MWException $e ) {
			LoggerFactory::getInstance( "WSSearch" )->error( "Unable to register parser hooks" );
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
	    $search_field_override = $config->get( "WSSearchSearchFieldOverride" );

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
     * Callback for the '#searchEngineConfig' parser function. Responsible for the creation of the
     * appropriate SearchEngineConfig object and for storing that object in the database.
     *
     * @param Parser $parser
     * @param string ...$parameters
     * @return string
     */
	public static function searchConfigCallback(Parser $parser, string ...$parameters ): string {
	    try {
            $config = SearchEngineConfig::newFromParameters( $parser->getTitle(), $parameters );
        } catch( \InvalidArgumentException $exception ) {
            return self::error( "wssearch-invalid-engine-config-detailed", [$exception->getMessage()] );
        }

		$config->update( wfGetDB( DB_MASTER ) );

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
	public static function searchEngineFrontendCallback(Parser $parser, string ...$parameters ): string {
	    $config = SearchEngineConfig::newFromDatabase( $parser->getTitle() );

	    if ( $config === null ) {
		    return self::error( "wssearch-invalid-engine-config" );
        }

        $result = self::error( "wssearch-missing-frontend" );

		\Hooks::run( "WSSearchOnLoadFrontend", [ &$result, $config, $parser, $parameters ] );

		return $result;
	}

    /**
     * @param Parser $parser
     * @return string
     * @throws MWException
     */
	public static function verwijzingenCallback( Parser $parser ) {
        if ( !class_exists( "\WSArrays" ) ) {
            return "WSArrays must be installed.";
        }

        /*
        $options = self::extractOptions( func_get_args() );

        $limit = isset( $options["limit"] ) ? $options["limit"] : "100";
        $property = isset( $options["property"] ) ? $options["property"] : "";
        $array_name = isset( $options["array"] ) ? $options["array"] : "";
        $date_property = isset( $options["date property"] ) ? $options["date property"] : "Modification date";

        if ( !$property || !$array_name ) {
            return "Missing `array` or `property` parameter";
        }

        if ( !isset( $options["from"] ) || !isset( $options["to"] ) ) {
            return "Missing `from` or `to` parameter";
        }

        if ( !ctype_digit( $options["from"] ) || !ctype_digit( $options["to"] ) || !ctype_digit( $limit ) ) {
            return "Invalid `from`, `limit` or `to` parameter";
        }

        list( $from, $to ) = self::convertDate( $options["from"], $options["to"] );

        $filter = [
            "verwijzingen" => [
                "filter" => [
                    "range" => [
                        ( new Property( $date_property ) )->getPropertyField() => [
                            "to" => $to,
                            "from" => $from
                        ]
                    ]
                ],
                "aggs" => [
                    "aantal_verwijzingen" => [
                        "terms" => [
                            "field" => (new Property($property))->getPropertyField()  . ".keyword",
                            "size" => $limit
                        ]
                    ]
                ]
            ]
        ];

        $search_engine = new SearchEngine();
        $search_engine->setLimit(0);
        $search_engine->addAggregations( [ new VerwijzingenAggregation( $property, $date_property ) ] );

        $result = $search_engine->doSearch();

        \WSArrays::$arrays[$array_name] = new \ComplexArray( $result["aggs"]["verwijzingen"]["aantal_verwijzingen"]["buckets"] );

        return "";
        */

        // TODO: To query engine

        return "Not implemented in new version yet (ask Marijn)";
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

    /**
     * Converts an array of values in form [0] => "name=value"
     * into a real associative array in form [name] => value
     * If no = is provided, true is assumed like this: [name] => true
     *
     * @param array string $options
     * @return array $results
     */
    private static function extractOptions( array $options ) {
        $results = [];
        foreach ( $options as $option ) {
            $pair = array_map( 'trim', explode( '=', $option, 2 ) );
            if ( count( $pair ) === 2 ) {
                $results[ $pair[0] ] = $pair[1];
            }
            if ( count( $pair ) === 1 ) {
                $results[ $pair[0] ] = true;
            }
        }
        return $results;
    }

    /**
     * @param string $from_year
     * @param string $to_year
     * @return array
     */
    private static function convertDate( int $from_year, int $to_year ) {
        return [ gregoriantojd( 1, 1, $from_year ), gregoriantojd( 12, 31, $to_year ) ];
    }
}
