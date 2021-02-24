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
abstract class PropertyRangeFilter extends Filter {
    /**
     * @var int The minimum value of the property
     */
    private $gte;

    /**
     * @var int The maximum value of the property
     */
    private $lte;

    /**
     * @var Property The property to apply the filter to
     */
    private $property;

    /**
     * DateRangeFilter constructor.
     *
     * @param Property $property The property to apply the filter to
     * @param int $gte The minimum value of the property
     * @param int $lte The maximum value of the property
     */
    public function __construct( Property $property, int $gte, int $lte ) {
        $this->property = $property;
        $this->gte = $gte;
        $this->lte = $lte;
    }

    /**
     * @inheritDoc
     */
    public function toQuery(): BuilderInterface {
        return new RangeQuery(
            $this->property->getPropertyField(),
            [
                RangeQuery::GTE => $this->gte,
                RangeQuery::LTE => $this->lte
            ]
        );
    }
}