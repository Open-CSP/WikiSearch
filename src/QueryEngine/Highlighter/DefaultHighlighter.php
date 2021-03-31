<?php


namespace WSSearch\QueryEngine\Highlighter;

use Config;
use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WSSearch\SearchEngine;
use WSSearch\SearchEngineConfig;
use WSSearch\SearchEngineException;
use WSSearch\SMW\PropertyFieldMapper;

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
    private $fields;

    /**
     * @var array The settings applied to each field of the highlight. This specifies for instance the fragment
     * size or the number of fragments per field.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.7/search-request-highlighting.html#highlighting-settings
     */
    private $field_settings;

    /**
     * @var SearchEngineConfig
     */
    private $config;

    /**
     * DefaultHighlighter constructor.
     *
     * @param SearchEngineConfig $config
     * @param string[]|null $fields The fields to apply the highlight to, or null to highlight the default fields
     * @param array|null $field_settings
     */
    public function __construct( SearchEngineConfig $config, array $fields = null, array $field_settings = null ) {
        $this->config = $config;

        if ( $fields !== null ) {
            $this->fields = $fields;
        } else {
            $this->fields = $this->getDefaultFields();
        }

        if ( $field_settings !== null ) {
            $this->field_settings = $field_settings;
        } else {
            $config = MediaWikiServices::getInstance()->getMainConfig();

            $this->field_settings = [
                "fragment_size" => $config->get( "WSSearchHighlightFragmentSize" ),
                "number_of_fragments" => $config->get( "WSSearchHighlightNumberOfFragments" )
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

    /**
     * Returns an array of fields to highlight if no specific fields are given in the constructor.
     *
     * @return array
     */
    private function getDefaultFields(): array {
        if ( $this->config->getSearchParameter( "highlighted properties" ) ) {
            $highlighted_properties = $this->config->getSearchParameter("highlighted properties");
            return $this->toPropertyList($highlighted_properties);
        }

        if ( $this->config->getSearchParameter( "search term properties" ) ) {
            $search_term_properties = $this->config->getSearchParameter( "search term properties" );
            return $this->toPropertyList( $search_term_properties );
        }

        // Fallback fields if no field is specified in the highlighted properties or search term properties
        return [
            "text_raw",
            "text_copy",
            "attachment.content"
        ];
    }

    /**
     * Takes a string of properties and a separator and returns an array of the property field names.
     *
     * @param string $parameter
     * @param string $separator
     * @return string[]
     */
    private function toPropertyList( string $parameter, string $separator = "," ): array {
        $fields = explode( $separator, $parameter ); // Split the string on the given separator
        $fields = array_map( "trim", $fields ); // Remove any excess whitespace
        return array_map( function( $property ): string {
            return ( new PropertyFieldMapper( $property ) )->getPropertyField( true ); // Map the property name to its field
        }, $fields );
    }
}