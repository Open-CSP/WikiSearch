<?php


namespace WSSearch\Tests\Phpunit\Unit\QueryEngine\Highlighter;

use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WSSearch\QueryEngine\Highlighter\DefaultHighlighter;
use WSSearch\QueryEngine\Highlighter\Highlighter;
use WSSearch\SearchEngineConfig;

/**
 * Class DefaultHighlighterTest
 * @package WSSearch\Tests\Phpunit\Unit\Highlighter
 * @covers \WSSearch\QueryEngine\Highlighter\DefaultHighlighter
 */
class DefaultHighlighterTest extends \MediaWikiUnitTestCase {
    /**
     * @var \Config
     */
    private $mock_config;

	/**
	 * @var array
	 */
	private $field_settings;

	public function setUp(): void {
        $this->mock_config = $this->getMockBuilder( SearchEngineConfig::class )
            ->disableOriginalConstructor()
            ->getMock();

        $this->mock_config->method( "getSearchParameter" )->willReturn( false );

        $this->field_settings = [
			"fragment_size" => 150,
			"number_of_fragments" => 3
		];

        parent::setUp();
    }

    public function testCanConstruct() {
        $this->assertInstanceOf( Highlighter::class, new DefaultHighlighter(  $this->mock_config, null, $this->field_settings ) );
    }

    /**
     * @dataProvider highlighterFields
     */
    public function testFieldsAreCorrect( array $fields ) {
        $highlighter = new DefaultHighlighter( $this->mock_config, $fields, $this->field_settings );
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

        $highlighter = new DefaultHighlighter( $this->mock_config, $fields, $field_settings );

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