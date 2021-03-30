<?php


namespace WSSearch\Tests\Phpunit\Unit\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use WSSearch\QueryEngine\Aggregation\PropertyAggregation;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyAggregationTest
 *
 * @package WSSearch\Tests\Phpunit\Unit\QueryEngine\Aggregation
 * @covers \WSSearch\QueryEngine\Aggregation\PropertyAggregation
 */
class PropertyAggregationTest extends \MediaWikiUnitTestCase {
    public function testCanConstruct() {
        $mock_property_field_mapper = $this->mockPropertyFieldMapper( "", "", "", "", 0 );

        $this->assertInstanceOf(
            PropertyAggregation::class,
            new PropertyAggregation( $mock_property_field_mapper )
        );
    }

    /**
     * @dataProvider samplePropertyFieldMappers
     */
    public function testAggregationNameIsPropertyNameWhenEmpty( PropertyFieldMapper $field_mapper ) {
        $property_aggregation = new PropertyAggregation( $field_mapper );
        $abstract_aggregation = $property_aggregation->toQuery();

        $this->assertSame( $field_mapper->getPropertyName(), $abstract_aggregation->getName() );
    }

    /**
     * @dataProvider samplePropertyFieldMappers
     */
    public function testAggregationNameIsCorrectWhenNonempty( PropertyFieldMapper $field_mapper ) {
        $property_aggregation = new PropertyAggregation( $field_mapper, "AGGREGATION_NAME" );
        $abstract_aggregation = $property_aggregation->toQuery();

        $this->assertSame( "AGGREGATION_NAME", $abstract_aggregation->getName() );
    }

    /**
     * @dataProvider samplePropertyFieldMappers
     */
    public function testToQuery( PropertyFieldMapper $field_mapper ) {
        $property_aggregation = new PropertyAggregation( $field_mapper );
        $abstract_aggregation = $property_aggregation->toQuery();

        $this->assertEquals( new TermsAggregation(
            $field_mapper->getPropertyName(),
            $field_mapper->getPropertyField()
        ), $abstract_aggregation );
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
        $mock_property_field_mapper->method( "getPropertyKey" )->willReturn( $property_key );
        $mock_property_field_mapper->method( "getPropertyId" )->willReturn( $property_id );

        $mock_property_field_mapper->method( "getPropertyField" )->willReturnCallback( function() use ($property_type, $property_field) {
            if ( $property_type !== "numField" ) {
                $suffix = ".keyword";
            } else {
                $suffix = "";
            }

            return $property_field . $suffix;
        } );

        return $mock_property_field_mapper;
    }
}