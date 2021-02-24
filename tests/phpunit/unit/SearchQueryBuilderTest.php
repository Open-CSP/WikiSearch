<?php

namespace WSSearch\Tests\Phpunit\Unit;

use MediaWiki\MediaWikiServices;
use WSSearch\QueryEngine\Property;
use WSSearch\SearchQueryBuilder;

class SearchQueryBuilderTest extends \MediaWikiTestCase {
    /**
     * @var SearchQueryBuilder
     */
    private $builder;

    /**
     * @var array
     */
    private $canonical_query;

    public function setUp() {
        parent::setUp();

        $config = MediaWikiServices::getInstance()->getMainConfig();

        $this->builder = SearchQueryBuilder::newCanonical();
        $this->canonical_query = [
            "index" => "smw-data-" . strtolower( wfWikiID() ),
            "from" => 0,
            "size" => $config->get( "WSSearchDefaultResultLimit" ),
            "body" => [
                "highlight" => [
                    "pre_tags" => [ "<b>" ],
                    "post_tags" => [ "</b>" ],
                    "fields" => [
                        "text_raw" => [
                            "fragment_size" => $config->get( "WSSearchHighlightFragmentSize" ),
                            "number_of_fragments" => $config->get( "WSSearchHighlightNumberOfFragments" )
                        ]
                    ]
                ],
                "aggs" => [],
                "query" => [
                    "constant_score" => [
                        "filter" => [
                            "bool" => [
                                "must" => [
                                    [
                                        "bool" => [
                                            "filter" => []
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Tests if the canonical query builder builds a "canonical" query.
     */
    public function testCanonicalBuildQuery() {
        $actual_query = $this->builder->buildQuery();

        $this->assertArrayEquals( $this->canonical_query, $actual_query );
    }

    /**
     * Tests the "setLimit" function of the query builder.
     */
    public function testSetLimit() {
        for ( $i = 0; $i < 100; $i++ ) {
            // Set the limit
            $this->builder->setLimit( $i );

            // Get a copy of the canonical query
            $expected_query = $this->canonical_query;

            // Set the limit to $i in the canonical query
            $expected_query["size"] = $i;

            $actual_query = $this->builder->buildQuery();

            $this->assertArrayEquals( $expected_query, $actual_query );
        }
    }

    /**
     * Tests the "setOffset" function of the query builder.
     */
    public function testSetOffset() {
        for ( $i = 0; $i < 100; $i++ ) {
            // Set the offset
            $this->builder->setOffset( $i );

            // Get a copy of the canonical query
            $expected_query = $this->canonical_query;

            // Set the offset to $i in the canonical query
            $expected_query["from"] = $i;

            $actual_query = $this->builder->buildQuery();

            $this->assertArrayEquals( $expected_query, $actual_query );
        }
    }

    /**
     * Tests the "setAggregateFilters" function of the query builder.
     *
     * @dataProvider aggs
     * @param array $aggs
     */
    public function testSetAggregateFilters($aggs) {
        // Set the aggs filter
        $this->builder->setAggregateFilters( $aggs );

        // Get a copy of the canonical query
        $expected_query = $this->canonical_query;

        // Set the aggregate filters
        $expected_query["body"]["aggs"] = $aggs;

        $actual_query = $this->builder->buildQuery();

        $this->assertArrayEquals( $expected_query, $actual_query );
    }

    /**
     * Tests the "setMainCondition" function of the query builder.
     *
     * @dataProvider mainCondition
     * @param Property $prop
     * @param string $val
     */
    public function testSetMainCondition(Property $prop, string $val) {
        // Set the condition
        $this->builder->setMainCondition($prop, $val);

        // Get a copy of the canonical query
        $expected_query = $this->canonical_query;

        // Build the term field
        $termfield = [
            "term" => [
                "P:" . $prop->getPropertyID() . "." . $prop->getPropertyType() . ".keyword" => $val
            ]
        ];

        // Set the active filter to the term field query
        $expected_query["body"]["query"]["constant_score"]["filter"]["bool"]["must"][0]["bool"]["filter"][] = $termfield;

        $actual_query = $this->builder->buildQuery();

        $this->assertArrayEquals( $expected_query, $actual_query );
    }

    /**
     * Data provider for random aggregate filter queries.
     */
    public function aggs() {
        return [
            [
                [
                    "foo" => "bar",
                    "randomaggs"
                ]
            ],
            [
                []
            ],
            [
                [[[[]]]]
            ],
            [
                [
                    "foo" => [
                        "foo" => [
                            "bar"
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Data provider of property-value pairs for testSetMainCondition.
     */
    public function mainCondition() {
        $values = [];

        for ( $i = 0; $i < 100; $i++ ) {
            $property_info_mock = $this->createMock( Property::class );

            $property_info_mock->method("getPropertyID")->willReturn($i);
            $property_info_mock->method("getPropertyType")->willReturn(md5(rand()));

            $value = md5(rand());

            $values[] = [ $property_info_mock, $value ];
        }

        return $values;
    }
}