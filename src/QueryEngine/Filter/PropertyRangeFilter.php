<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use WSSearch\QueryEngine\Property;

/**
 * Class DateRangeFilter
 *
 * Represents a date range filter to filter in between date properties values.
 *
 * @package WSSearch\QueryEngine\Filter
 */
abstract class PropertyDateRangeFilter extends Filter {
    /**
     * @var int The Julian date from which the filter applies
     */
    private $from_date_julian;

    /**
     * @var int The Julian date until which the filter applies
     */
    private $to_date_julian;

    /**
     * DateRangeFilter constructor.
     *
     * @param int $from_date_julian The Julian date from which the filter applies
     * @param int $to_date_julian The Julian date until which the filter applies
     */
    public function __construct( int $from_date_julian, int $to_date_julian ) {
        $this->from_date_julian = $from_date_julian;
        $this->to_date_julian = $to_date_julian;
    }

    /**
     * @inheritDoc
     */
    public function toBuilderInterface(): BuilderInterface {
        return new RangeQuery(
            $this->getProperty()->getPropertyField(),
            [
                RangeQuery::GTE => $this->from_date_julian,
                RangeQuery::LTE => $this->to_date_julian
            ]
        );
    }

    /**
     * Returns the property to which the filter applies.
     *
     * @return Property
     */
    protected abstract function getProperty(): Property;
}