<?php


namespace WSSearch\Tests\Phpunit\Unit\QueryEngine\Sort;

use ONGR\ElasticsearchDSL\Sort\FieldSort;
use WSSearch\QueryEngine\Sort\PropertySort;
use WSSearch\QueryEngine\Sort\Sort;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertySortTest
 * @package WSSearch\Tests\Phpunit\Unit\QueryEngine\Sort
 * @covers \WSSearch\QueryEngine\Sort\PropertySort
 */
class PropertySortTest extends \MediaWikiUnitTestCase {
    public function testCanConstruct() {
        $property_mock = $this->getMockBuilder( PropertyFieldMapper::class )
            ->disableOriginalConstructor()
            ->getMock();

        $this->assertInstanceOf( Sort::class, new PropertySort( $property_mock, null ) );
    }

    /**
     * @dataProvider sortOrders
     */
    public function testToQuery( string $order, $expected_order, array $expected_parameters ) {
        $property_mock = $this->getMockBuilder( PropertyFieldMapper::class )
            ->disableOriginalConstructor()
            ->getMock();
        $property_mock->method( "getPropertyField" )->willReturn( "Foobar" );

        $expected_query = new FieldSort( $property_mock->getPropertyField(), $expected_order, $expected_parameters );
        $sort = new PropertySort( $property_mock, $order );

        $this->assertEquals( $expected_query, $sort->toQuery() );
    }

    /**
     * Data provider to provide some sensible highlighting fields.
     */
    public function sortOrders() {
        return [
            ["ascending", "asc", [ "mode" => "min" ]],
            ["asc", "asc", [ "mode" => "min" ] ],
            ["", null, [] ],
            ["descending", "desc", [ "mode" => "max" ] ],
            ["dsc", "desc", [ "mode" => "max" ] ]
        ];
    }
}