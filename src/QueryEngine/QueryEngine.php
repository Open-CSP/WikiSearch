<?php

namespace WSSearch;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use ONGR\ElasticsearchDSL\Search;

class QueryEngine {
    const TEXT_INDEX_NAME = "text_raw";

    /**
     * @var Search
     */
    private $elasticsearch_search;

    /**
     * The "index" to use for the ElasticSearch query.
     *
     * @var string
     */
    private $elasticsearch_index;

    /**
     * QueryEngine constructor.
     */
    public function __construct() {
        $this->elasticsearch_search = new Search();

        $config = MediaWikiServices::getInstance()->getMainConfig();

        $this->elasticsearch_index = $config->get( "WSSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );
        $this->elasticsearch_search->setSize( $config->get( "WSSearchDefaultResultLimit" ) );

        $highlight = new Highlight();
        $highlight->addParameter( "pre_tags", "<b>" );
        $highlight->addParameter( "post_tags", "</b>");
        $highlight->addParameter( self::TEXT_INDEX_NAME, [
            "fragment_size" => $config->get( "WSSearchHighlightFragmentSize" ),
            "number_of_fragments" => $config->get( "WSSearchHighlightNumberOfFragments" )
        ]);

        $this->elasticsearch_search->addHighlight( $highlight );
    }

    public function setFilters( array $filters ) {

    }

    /**
     * Returns the "Search" object. Can be used to alter the query directly.
     *
     * @return Search
     */
    public function _(): Search {
        return $this->elasticsearch_search;
    }

    /**
     * Converts this class into a full ElasticSearch query, that can be used like so:
     *
     * $this->elasticsearch_client->search( $this->query_engine->toArray() );
     */
    public function toArray(): array {
        return [
            "index" => $this->elasticsearch_index,
            "body" => $this->elasticsearch_search->toArray()
        ];
    }
}