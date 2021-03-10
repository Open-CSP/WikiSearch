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

        $highlight->addField( "text_raw", $field_settings );
        $highlight->addField( "attachment.content", $field_settings );

        return $highlight;
    }
}