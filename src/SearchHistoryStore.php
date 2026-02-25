<?php

namespace WikiSearch;

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class SearchHistoryStore {
    private const HISTORY_TABLE_NAME = 'search_history';
    private const AGGREGATE_TABLE_NAME = 'search_history_aggregates';

    private IDatabase $dbPrimary;
    private IDatabase $dbReplica;

    /**
     * @param IDatabase $dbPrimary The MediaWiki SQL database abstraction layer
     * @param IDatabase|null $dbReplica Optionally a replica MediaWiki SQL database
     */
    public function __construct( IDatabase $dbPrimary, ?IDatabase $dbReplica = null ) {
        $this->dbPrimary = $dbPrimary;
        $this->dbReplica = $dbReplica ?? $dbPrimary;
    }

    public function pushHistory( string $search_term ): void {
        $search_term = trim( $search_term );

        $this->dbPrimary->insert(
            self::HISTORY_TABLE_NAME,
            [
                'search_query' => $search_term,
                'search_timestamp' => wfTimestampNow(),
            ],
            __METHOD__
        );

        $this->dbPrimary->upsert(
            self::AGGREGATE_TABLE_NAME,
            [
                [
                    'search_query' => $search_term,
                    'search_occurrences' => 1,
                ]
            ],
            [ 'search_query' ],
            [
                'search_occurrences = search_occurrences + 1'
            ],
            __METHOD__
        );
    }

    public function getHistory(): IResultWrapper {
        return $this->dbReplica->select(
            self::HISTORY_TABLE_NAME,
            [
                'search_history_id',
                'search_query',
                'search_timestamp'
            ],
            [],
            __METHOD__,
            []
        );
    }
}