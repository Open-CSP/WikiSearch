<?php

namespace WSSearch\QueryEngine\Highlighter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WSSearch\SearchEngineConfig;

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
	 * Note: This highlighter requires a valid SearchEngineConfig to be defined via SearchEngine::$config.
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
		$highlight->setTags( [ '{@@_HIGHLIGHT_@@' ], [ "@@_HIGHLIGH_@@}" ] );

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
			return $this->config->getSearchParameter( "highlighted properties" );
		}

		if ( $this->config->getSearchParameter( "search term properties" ) ) {
			$properties = $this->config->getSearchParameter( "search term properties" );
			$properties = array_map( function ( $property ): string {
				if ( is_string( $property ) ) {
					return $property;
				}

				return $property->getPropertyField();
			}, $properties );

			return $properties;
		}

		// Fallback fields if no field is specified in the highlighted properties or search term properties
		return [
			"text_raw",
			"text_copy",
			"attachment.content"
		];
	}
}
