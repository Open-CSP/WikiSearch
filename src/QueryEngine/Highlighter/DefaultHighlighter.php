<?php


namespace WSSearch\QueryEngine\Highlighter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;

/**
 * Class DefaultHighlighter
 *
 * The default highlighter applied to all WSSearch searches.
 *
 * @package WSSearch\QueryEngine\Highlighter
 */
class DefaultHighlighter implements Highlighter {
    /**
     * @var array The fields to apply the highlight to
     */
    private $fields = [
        "text_raw",
        "text_copy",
        "attachment.content"
    ];

    /**
     * DefaultHighlighter constructor.
     *
     * @param array|null $fields The fields to apply the highlight to, or null to highlight the default fields
     */
    public function __construct( array $fields = null ) {
        if ( $fields !== null ) {
            $this->fields = $fields;
        }
    }

    /**
     * @inheritDoc
     */
    public function toQuery(): Highlight {
        $config = MediaWikiServices::getInstance()->getMainConfig();

        $highlight = new Highlight();
        $highlight->setTags( ['<b class="wssearch-term-highlight">'], ["</b>"] );

        $field_settings = [
            "fragment_size" => $config->get( "WSSearchHighlightFragmentSize" ),
            "number_of_fragments" => $config->get( "WSSearchHighlightNumberOfFragments" )
        ];

        foreach ( $this->fields as $field ) {
            $highlight->addField( $field, $field_settings );
        }

        return $highlight;
    }
}