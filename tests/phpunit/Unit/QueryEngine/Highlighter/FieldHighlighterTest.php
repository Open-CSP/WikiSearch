<?php


namespace WSSearch\Tests\Phpunit\Unit\QueryEngine\Highlighter;

use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WSSearch\QueryEngine\Highlighter\DefaultHighlighter;
use WSSearch\QueryEngine\Highlighter\Highlighter;

/**
 * Class DefaultHighlighterTest
 * @package WSSearch\Tests\Phpunit\Unit\Highlighter
 * @covers \WSSearch\QueryEngine\Highlighter\DefaultHighlighter
 */
class FieldHighlighterTest extends \MediaWikiUnitTestCase {
    /**
     * @var \Config
     */
    private $mock_config;

    public function setUp(): void {
        $this->mock_config = $this->getMockBuilder( \GlobalVarConfig::class )
            ->disableOriginalConstructor()
            ->getMock();

        $this->mock_config->method( "get" )->willReturnMap(
            [ "WSSearchHighlightFragmentSize" => 150, "WSSearchHighlightNumberOfFragments" => 1 ]
        );

        parent::setUp();
    }

    public function testCanConstruct() {
        $this->assertInstanceOf( Highlighter::class, new DefaultHighlighter( null, null, $this->mock_config ) );
    }

    /**
     * @dataProvider highlighterFields
     */
    public function testFieldsAreCorrect( array $fields ) {
        $highlighter = new DefaultHighlighter( $fields, null, $this->mock_config );
        $this->assertArrayEquals( $fields, array_keys( $highlighter->toQuery()->toArray()["fields"] ) );
    }

    /**
     * @dataProvider highlighterFields
     */
    public function testToQuery( array $fields ) {
        $field_settings = [];

        $expected_highlight = new Highlight();
        $expected_highlight->setTags( ['<b class="wssearch-term-highlight">'], ["</b>"] );

        foreach ( $fields as $field ) {
            $expected_highlight->addField( $field, $field_settings );
        }

        $highlighter = new DefaultHighlighter( $fields, $field_settings );

        $this->assertEquals( $expected_highlight, $highlighter->toQuery() );
    }

    /**
     * Data provider to provide some sensible highlighting fields.
     */
    public function highlighterFields() {
        return [
            [[ "text_raw", "text_copy", "attachment.content" ]],
            [[ "Page", "Property" ]],
            [[ "Term", "HighlightMe!" ]]
        ];
    }
}