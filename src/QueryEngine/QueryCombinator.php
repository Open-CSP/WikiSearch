<?php

namespace WikiSearch\QueryEngine;

/**
 * Used to combine ElasticSearch queries. For instance, to combine three queries:
 *
 * $combinator = WikiSearchServices::getQueryCombinatorFactory()->newQueryCombinator();
 * $combinedQuery = $combinator->add( $query1 )->add( $query2 )->add( $query3 )->getQuery();
 *
 * @package WikiSearch\QueryEngine
 */
class QueryCombinator {
    /**
     * @var array
     */
    private array $query;

    /**
     * Returns the combined query.
     *
     * @return array
     */
    public function getQuery(): array {
        if ( !isset( $this->query ) ) {
            return [];
        }

        return $this->query;
    }

    /**
     * Adds the given query to the current query.
     *
     * @param array $query The query to add
     * @return QueryCombinator
     */
    public function add( array $query ): QueryCombinator {
        if ( !isset( $this->query ) ) {
            // This is the initial query
            $this->query = $query;
        } elseif ( isset( $query["body"]["query"] ) ) {
            $this->query["body"]["query"] = [
                "bool" => [
                    "must" => [
                        $query["body"]["query"],
                        $this->query["body"]["query"]
                    ]
                ]
            ];
        }

        return $this;
    }
}