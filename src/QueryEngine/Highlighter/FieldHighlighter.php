<?php


namespace WSSearch\QueryEngine\Highlighter;

use Config;
use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class DefaultHighlighter
 *
 * The default highlighter applied to all WSSearch searches.
 *
 * @package WSSearch\QueryEngine\Highlighter
 */
class FieldHighlighter implements Highlighter {
    /**
     * @var array The fields to apply the highlight to
     */
    private $fields = [
        "text_raw",
        "text_copy",
        "attachment.content"
    ];

    /**
     * @var array The settings applied to each field of the highlight. This specifies for instance the fragment
     * size or the number of fragments per field.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.7/search-request-highlighting.html#highlighting-settings
     */
    private $field_settings;

    /**
     * @var Config
     */
    private $config;

    /**
     * DefaultHighlighter constructor.
     *
     * @param string[]|null $fields The fields to apply the highlight to, or null to highlight the default fields
     * @param array|null $field_settings
     * @param Config|null $config
     */
    public function __construct( array $fields = null, array $field_settings = null, Config $config = null ) {
        $this->config = $config === null ? MediaWikiServices::getInstance()->getMainConfig() : $config;

        if ( $fields !== null ) {
            $this->fields = $fields;
        }

        if ( $field_settings !== null ) {
            $this->field_settings = $field_settings;
        } else {
            $this->field_settings = [
                "fragment_size" => $this->config->get( "WSSearchHighlightFragmentSize" ),
                "number_of_fragments" => $this->config->get( "WSSearchHighlightNumberOfFragments" )
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function toQuery(): Highlight {
        $highlight = new Highlight();
        $highlight->setTags( ['<b class="wssearch-term-highlight">'], ["</b>"] );

        foreach ( $this->fields as $field ) {
            $highlight->addField( $field, $this->field_settings );
        }

        return $highlight;
    }
}