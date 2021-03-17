<?php


namespace WSSearch\Tests\Phpunit\Unit\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\Bucketing\RangeAggregation;
use WSSearch\QueryEngine\Aggregation\PropertyRangeAggregation;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyRangeAggregationTest
 *
 * @package WSSearch\Tests\Phpunit\Unit\QueryEngine\Aggregation
 * @covers \WSSearch\QueryEngine\Aggregation\PropertyRangeAggregation
 */
class PropertyRangeAggregationTest extends \MediaWikiUnitTestCase {
    public function testCanConstruct() {
        $mock_property_field_mapper = $this->mockPropertyFieldMapper( "", "", "", "", 0 );

        $this->assertInstanceOf(
            PropertyRangeAggregation::class,
            new PropertyRangeAggregation( $mock_property_field_mapper, [] )
        );
    }

    /**
     * @dataProvider samplePropertyFieldMappers
     */
    public function testAggregationNameIsPropertyNameWhenEmpty( PropertyFieldMapper $field_mapper ) {
        $property_aggregation = new PropertyRangeAggregation( $field_mapper, [] );
        $abstract_aggregation = $property_aggregation->toQuery();

        $this->assertSame( $field_mapper->getPropertyName(), $abstract_aggregation->getName() );
    }

    /**
     * @dataProvider samplePropertyFieldMappers
     */
    public function testAggregationNameIsCorrectWhenNonempty( PropertyFieldMapper $field_mapper ) {
        $property_aggregation = new PropertyRangeAggregation( $field_mapper, [], "AGGREGATION_NAME" );
        $abstract_aggregation = $property_aggregation->toQuery();

        $this->assertSame( "AGGREGATION_NAME", $abstract_aggregation->getName() );
    }

    /**
     * @dataProvider samplePropertyFieldMappers
     */
    public function testRangesAreCorrect( PropertyFieldMapper $field_mapper ) {
        $expected_ranges = [
            [
                [ "from" => 0, "to" => 9001 ],
                [ "from" => -1, "to" => -2 ],
                [ "from" => 10, "to" => 1000 ],
                [ "from" => 99, "to" => 99 ]
            ],
            [
                [ "from" => 100, "to" => 1000, "key" => 1000 ],
                [ "from" => 1000, "to" => 100000, "key" => "Last month" ]
            ],
            [
                [],
                [ "to" => 1000 ],
                [ "from" => 100 ]
            ],
            []
        ];

        foreach ( $expected_ranges as $expected_range ) {
            $property_range_aggregation = new PropertyRangeAggregation( $field_mapper, $expected_range );
            /** @var RangeAggregation $range_aggregation_query */
            $range_aggregation_query = $property_range_aggregation->toQuery();
            $this->assertSame( $expected_range, $range_aggregation_query->getArray()["ranges"] );
        }
    }

    /**
     * @dataProvider samplePropertyFieldMappers
     */
    public function testToQuery( PropertyFieldMapper $field_mapper ) {
        $expected_ranges = [
            [
                [ "from" => 0, "to" => 9001 ],
                [ "from" => -1, "to" => -2 ],
                [ "from" => 10, "to" => 1000 ],
                [ "from" => 99, "to" => 99 ]
            ],
            [
                [ "from" => 100, "to" => 1000, "key" => 1000 ],
                [ "from" => 1000, "to" => 100000, "key" => "Last month" ]
            ],
            [
                [],
                [ "to" => 1000 ],
                [ "from" => 100 ]
            ],
            []
        ];

        foreach ( $expected_ranges as $expected_range ) {
            $property_aggregation = new PropertyRangeAggregation( $field_mapper, $expected_range );
            $abstract_aggregation = $property_aggregation->toQuery();

            $this->assertEquals( new RangeAggregation(
                $field_mapper->getPropertyName(),
                $field_mapper->getPropertyField(),
                $expected_range,
                true
            ), $abstract_aggregation );
        }
    }

    /**
     * Returns a curated sample of interesting and not-so-interesting property names to use for testing.
     *
     * @return array
     */
    public function samplePropertyFieldMappers(): array {
        return [
            [
                $this->mockPropertyFieldMapper(
                    "Modification date",
                    "datField",
                    "P:29.datField",
                    "_MDAT",
                    29
                )
            ],
            [
                $this->mockPropertyFieldMapper(
                    "Average rating",
                    "numField",
                    "P:2240.numField",
                    "__rp_average",
                    2240
                )
            ],
            [
                $this->mockPropertyFieldMapper(
                    "Foobar",
                    "wpgField",
                    "P:0.wpgField",
                    "Foobar",
                    0
                )
            ],
            [
                $this->mockPropertyFieldMapper(
                    "0",
                    "wpgField",
                    "P:0.wpgField",
                    "0",
                    0
                )
            ],
            [
                $this->mockPropertyFieldMapper(
                    "Example property",
                    "wpgField",
                    "P:0.wpgField",
                    "Example_property",
                    0
                )
            ],
            [
                $this->mockPropertyFieldMapper(
                    "__INVALID",
                    "",
                    "",
                    "",
                    -1
                )
            ]
        ];
    }

    private function mockPropertyFieldMapper(
        string $property_name,
        string $property_type,
        string $property_field,
        string $property_key,
        int $property_id
    ) {
        $mock_property_field_mapper = $this->getMockBuilder( PropertyFieldMapper::class )
            ->disableOriginalConstructor()
            ->getMock();

        $mock_property_field_mapper->method( "getPropertyName" )->willReturn( $property_name );
        $mock_property_field_mapper->method( "getPropertyType" )->willReturn( $property_type );
        $mock_property_field_mapper->method( "getPropertyField" )->willReturn( $property_field );
        $mock_property_field_mapper->method( "getPropertyKey" )->willReturn( $property_key );
        $mock_property_field_mapper->method( "getPropertyId" )->willReturn( $property_id );

        return $mock_property_field_mapper;
    }
}