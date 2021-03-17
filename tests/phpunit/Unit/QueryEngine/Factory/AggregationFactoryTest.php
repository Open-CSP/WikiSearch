<?php

namespace WSSearch\Tests\Phpunit\Unit\QueryEngine\Factory;

use WSSearch\QueryEngine\Aggregation\Aggregation;
use WSSearch\QueryEngine\Aggregation\PropertyAggregation;
use WSSearch\QueryEngine\Aggregation\PropertyRangeAggregation;
use WSSearch\QueryEngine\Factory\AggregationFactory;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class AggregationFactoryTest
 * @package WSSearch\Tests\Phpunit\Unit\QueryEngine\Factory
 * @covers \WSSearch\QueryEngine\Factory\AggregationFactory
 */
class AggregationFactoryTest extends \MediaWikiUnitTestCase {
    /**
     * @var AggregationFactory
     */
    private $aggregation_factory;

    public function setUp(): void {
        $this->aggregation_factory = new AggregationFactory();

        parent::setUp();
    }
    
    public function testCanConstruct() {
        $this->assertInstanceOf( AggregationFactory::class, new AggregationFactory() );
    }

    /**
     * @dataProvider aggregationArrays
     */
    public function testFromArray( array $aggregation_array, Aggregation $expected_aggregation = null ) {
        $this->assertEquals( $expected_aggregation, $this->aggregation_factory->fromArray( $aggregation_array ) );
    }

    /**
     * Data provider to provide some "aggregation arrays" and the corresponding aggregation object (or null when invalid). An
     * aggregation array is the array given to the WSSearch API in order to specify an aggregation and the format is specified in
     * the WSSearch README.
     *
     * @see https://bitbucket.org/wikibasesolutions/wssearch/src/master/
     */
    public function aggregationArrays() {
        return [
            [
                [
                    "type" => "range",
                    "ranges" => [
                        [ "to" => 50 ],
                        [ "from" => 100, "to" => 200 ],
                        [ "from" => 100 ]
                    ],
                    "property" => $this->propertyFieldMapperMock( "Price" )
                ],
                new PropertyRangeAggregation(
                    $this->propertyFieldMapperMock( "Price" ), [
                        [ "to" => 50 ],
                        [ "from" => 100, "to" => 200 ],
                        [ "from" => 100 ]
                    ]
                )
            ],
            [
                [
                    "type" => "range",
                    "ranges" => [],
                    "property" => $this->propertyFieldMapperMock( "Price" )
                ],
                new PropertyRangeAggregation(
                    $this->propertyFieldMapperMock( "Price" ), []
                )
            ],
            [
                [
                    "type" => "range",
                    "ranges" => [
                        [ "to" => 50 ],
                        [ "from" => 100, "to" => 200 ],
                        [ "from" => 100 ]
                    ]
                ],
                null
            ],
            [
                [
                    "type" => "range",
                    "property" => $this->propertyFieldMapperMock( "Price" )
                ],
                null
            ],
            [
                [
                    "ranges" => [
                        [ "to" => 50 ],
                        [ "from" => 100, "to" => 200 ],
                        [ "from" => 100 ]
                    ],
                    "property" => $this->propertyFieldMapperMock( "Price" )
                ],
                null
            ],
            [
                [
                    "type" => "nonexistentrange",
                    "ranges" => [
                        [ "to" => 50 ],
                        [ "from" => 100, "to" => 200 ],
                        [ "from" => 100 ]
                    ],
                    "property" => $this->propertyFieldMapperMock( "Price" )
                ],
                null
            ],
            [
                [
                    "type" => "property",
                    "ranges" => [
                        [ "to" => 50 ],
                        [ "from" => 100, "to" => 200 ],
                        [ "from" => 100 ]
                    ],
                    "property" => $this->propertyFieldMapperMock( "Price" )
                ],
                null
            ],
            [
                [
                    "type" => "property",
                    "property" => $this->propertyFieldMapperMock( "Price2" )
                ],
                null
            ],
            [
                [
                    "type" => "property"
                ],
                null
            ],
            [
                [
                    "type" => "range",
                    "ranges" => [
                        [ "to" => 50 ],
                        [ "from" => 100, "to" => 200 ],
                        [ "from" => 100 ]
                    ],
                    "property" => $this->propertyFieldMapperMock( "Price" ),
                    "name" => "Foobar"
                ],
                new PropertyRangeAggregation(
                    $this->propertyFieldMapperMock( "Price" ), [
                        [ "to" => 50 ],
                        [ "from" => 100, "to" => 200 ],
                        [ "from" => 100 ]
                    ],
                    "Foobar"
                )
            ],
            [
                [
                    "type" => "property",
                    "property" => $this->propertyFieldMapperMock( "Price" ),
                    "name" => "Foobar"
                ],
                new PropertyAggregation(
                    $this->propertyFieldMapperMock( "Price" ),
                    "Foobar"
                )
            ],
            [
                [
                    "type" => "property",
                    "ranges" => "",
                    "property" => $this->propertyFieldMapperMock( "Price" ),
                    "name" => "Foobar"
                ],
                new PropertyAggregation(
                    $this->propertyFieldMapperMock( "Price" ),
                    "Foobar"
                )
            ],
            [
                [
                    "type" => [
                        "foo"
                    ],
                    "ranges" => "",
                    "property" => $this->propertyFieldMapperMock( "Price" ),
                    "name" => "Foobar"
                ],
                null
            ],
            [
                [
                    "type" => [],
                    "ranges" => [],
                    "property" => $this->propertyFieldMapperMock( "Price" ),
                    "name" => "Foobar"
                ],
                null
            ],
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