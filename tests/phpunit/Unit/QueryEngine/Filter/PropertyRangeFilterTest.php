<?php


namespace WSSearch\Tests\Phpunit\Unit\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use PHPUnit\Framework\MockObject\MockObject;
use WSSearch\QueryEngine\Filter\PropertyRangeFilter;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyRangeFilterTest
 * @package WSSearch\Tests\Phpunit\Unit\QueryEngine\Filter
 * @covers \WSSearch\QueryEngine\Filter\PropertyRangeFilter
 */
class PropertyRangeFilterTest extends \MediaWikiUnitTestCase {
    public function testCanConstruct() {
        $this->assertInstanceOf(
            PropertyRangeFilter::class,
            new PropertyRangeFilter(
                $this->getMockBuilder( PropertyFieldMapper::class )
                    ->disableOriginalConstructor()
                    ->getMock(),
                []
            )
        );
    }

    /**
     * @dataProvider filters
     */
    public function testToQuery( PropertyFieldMapper $property, array $options, BuilderInterface $expected_query ) {
        $filter = new PropertyRangeFilter( $property, $options );

        $this->assertEquals( $expected_query->toArray(), $filter->toQuery()->toArray() );
    }

    /**
     * Data provider that provides the required parameters to construct a PropertyRangeFilter and the
     * expected resulting BuilderInterface.
     */
    public function filters() {
        return [
            [
                $this->propertyFieldMapperMock( "Price" ),
                [
                    "boost" => 10,
                    "gte" => 10,
                    "lte" => 100
                ],
                $this->expectedBuilderInterface( $this->propertyFieldMapperMock( "Price" ), [
                    "boost" => 10,
                    "gte" => 10,
                    "lte" => 100
                ] )
            ],
            [
                $this->propertyFieldMapperMock( "Price" ),
                [
                    "gte" => 10,
                    "lte" => 100
                ],
                $this->expectedBuilderInterface( $this->propertyFieldMapperMock( "Price" ), [
                    "boost" => 1.0,
                    "gte" => 10,
                    "lte" => 100
                ] )
            ]
        ];
    }

    /**
     * Returns the BuilderInterface corresponding to the given options.
     *
     * @param PropertyFieldMapper|MockObject $property
     * @param array $options
     * @return BoolQuery
     */
    public function expectedBuilderInterface( $property, array $options ) {
        $property_field = $property->getPropertyField();

        $bool_query = new BoolQuery();
        $bool_query->add( new RangeQuery(
            $property_field,
            $options
        ), BoolQuery::MUST );

        return $bool_query;
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