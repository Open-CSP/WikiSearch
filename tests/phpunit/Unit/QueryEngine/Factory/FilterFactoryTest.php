<?php

namespace WSSearch\Tests\Phpunit\Unit\QueryEngine\Factory;

use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use WSSearch\QueryEngine\Factory\FilterFactory;
use WSSearch\QueryEngine\Filter\Filter;
use WSSearch\QueryEngine\Filter\PropertyRangeFilter;
use WSSearch\QueryEngine\Filter\PropertyValueFilter;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class FilterFactoryTest
 * @package WSSearch\Tests\Phpunit\Unit\QueryEngine\Factory
 * @covers \WSSearch\QueryEngine\Factory\FilterFactory
 */
class FilterFactoryTest extends \MediaWikiUnitTestCase {
    /**
     * @var FilterFactory
     */
    private $filter_factory;

    public function setUp(): void {
        $this->filter_factory = new FilterFactory();

        parent::setUp();
    }

    public function testCanConstruct() {
        $this->assertInstanceOf( FilterFactory::class, new FilterFactory() );
    }

    /**
     * @dataProvider filterArrays
     */
    public function testFromArray( array $filter_array, Filter $expected_filter = null ) {
        $this->assertEquals( $expected_filter, $this->filter_factory->fromArray( $filter_array ) );
    }

    /**
     * Data provider to provide some "filter arrays" and the corresponding filter object (or null when invalid). A
     * filter array is the array given to the WSSearch API in order to specify a filter. The format is specified in
     * the WSSearch README.
     *
     * @see https://bitbucket.org/wikibasesolutions/wssearch/src/master/
     */
    public function filterArrays() {
        return [
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Age" ),
                    "value" => "Foobar"
                ],
                new PropertyValueFilter(
                    $this->propertyFieldMapperMock( "Age" ),
                    "Foobar"
                )
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Foo" ),
                    "value" => "Foobar"
                ],
                new PropertyValueFilter(
                    $this->propertyFieldMapperMock( "Foo" ),
                    "Foobar"
                )
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Age" )
                ],
                null
            ],
            [
                [
                    "value" => "Foobar"
                ],
                null
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Age" ),
                    "value" => ""
                ],
                new PropertyValueFilter(
                    $this->propertyFieldMapperMock( "Age" ),
                    ""
                )
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "" ),
                    "value" => "Foobar"
                ],
                new PropertyValueFilter(
                    $this->propertyFieldMapperMock( "" ),
                    "Foobar"
                )
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Age" ),
                    "range" => ""
                ],
                null
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Age" ),
                    "value" => []
                ],
                null
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Age" ),
                    "range" => [
                        "lte" => []
                    ]
                ],
                new PropertyRangeFilter(
                    $this->propertyFieldMapperMock( "Age" ),
                    [
                        RangeQuery::LTE => []
                    ]
                )
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Age" ),
                    "range" => [
                        "lte" => 100
                    ]
                ],
                new PropertyRangeFilter(
                    $this->propertyFieldMapperMock( "Age" ),
                    [
                        RangeQuery::LTE => 100
                    ]
                )
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Age" ),
                    "range" => [
                        "lte" => 100,
                        "boost" => 10
                    ]
                ],
                new PropertyRangeFilter(
                    $this->propertyFieldMapperMock( "Age" ),
                    [
                        RangeQuery::LTE => 100,
                        "boost" => 10
                    ]
                )
            ],
            [
                [
                    "key" => $this->propertyFieldMapperMock( "Age" ),
                    "range" => [
                        "lt" => 100,
                        "boost" => 10
                    ]
                ],
                new PropertyRangeFilter(
                    $this->propertyFieldMapperMock( "Age" ),
                    [
                        RangeQuery::LT => 100,
                        "boost" => 10
                    ]
                )
            ]
        ];
    }

    /**
     * Returns a usable PropertyFieldMapper mock for the given property name.
     *
     * @param string $property_name
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    public function propertyFieldMapperMock( string $property_name ) {
        $property_field_mapper_mock = $this->getMockBuilder( PropertyFieldMapper::class )
            ->disableOriginalConstructor()
            ->getMock();

        $property_field_mapper_mock->method( "getPropertyName" )->willReturn( $property_name );
        $property_field_mapper_mock->method( "getPropertyField" )->willReturn( "P:0.wpgField" );

        return $property_field_mapper_mock;
    }
}