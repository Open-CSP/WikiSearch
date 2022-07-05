<?php

namespace WikiSearch\QueryEngine\Filter;

use ConfigException;
use Elasticsearch\ClientBuilder;
use MediaWiki\MediaWikiServices;
use MWException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WikiSearch\Logger;
use WikiSearch\QueryEngine\Factory\QueryEngineFactory;
use WikiSearch\SearchEngineException;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class CompoundFilter
 *
 * This class is used to combine multiple filters into one filter.
 */
class CompoundFilter extends AbstractFilter {
    /**
     * @var AbstractFilter[]
     */
    private array $filters;

    /**
     * CompoundFilter constructor.
     *
     * @param AbstractFilter[] $filters The filters to combine
     */
    public function __construct( array $filters ) {
        $this->filters = $filters;
    }

    /**
     * @inheritDoc
     *
     * @return BoolQuery
     */
    public function toQuery(): BoolQuery {
        $compound_filter = new BoolQuery();

        foreach ( $this->filters as $filter ) {
            $compound_filter->add( $filter->toQuery(), BoolQuery::FILTER );
        }

        return $compound_filter;
    }
}
