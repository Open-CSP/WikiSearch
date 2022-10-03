<?php

namespace WikiSearch\QueryEngine\Highlighter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WikiSearch\SearchEngineConfig;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class DefaultHighlighter
 *
 * The default highlighter applied to all WikiSearch searches.
 *
 * @package WikiSearch\QueryEngine\Highlighter
 */
class DefaultHighlighter implements Highlighter {
	/**
	 * @var SearchEngineConfig
	 */
	private SearchEngineConfig $config;

	/**
	 * @var array The fields to apply the highlight to
	 */
	private array $fields;

	/**
	 * @var array The settings applied to each field of the highlight. This specifies for instance the fragment
	 * size or the number of fragments per field.
	 *
	 * phpcs:ignore
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.7/search-request-highlighting.html#highlighting-settings
	 */
	private array $common_field_settings;

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
			$this->common_field_settings = $field_settings;
		} else {
			$main_config = MediaWikiServices::getInstance()->getMainConfig();
			$this->common_field_settings = [
				"fragment_size" => $main_config->get( "WikiSearchHighlightFragmentSize" ),
				"number_of_fragments" => $main_config->get( "WikiSearchHighlightNumberOfFragments" )
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight {
		$highlight = new Highlight();
		$highlight->setTags( [ '{@@_HIGHLIGHT_@@' ], [ "@@_HIGHLIGHT_@@}" ] );

		$highlighter_type = $this->config->getSearchParameter( "highlighter type" );

		foreach ( $this->fields as $field ) {
			$field_settings = $this->common_field_settings;

			if ( $highlighter_type === "fvh" && $field->supportsFVH() ) {
				// TODO: Support different highlighter types
				$field_settings['type'] = $highlighter_type;
			}

			if ( $field->hasSearchSubfield() ) {
				$field_settings['matched_fields'] = [ $field->getPropertyField(), $field->getSearchField() ];
			}

			$highlight->addField( $field->getPropertyField(), $field_settings );
		}

		return $highlight;
	}

	/**
	 * Returns an array of fields to highlight if no specific fields are given in the constructor.
	 *
	 * @return PropertyFieldMapper[]
	 */
	private function getDefaultFields(): array {
		$properties =
			$this->config->getSearchParameter( "highlighted properties" ) ?:
			$this->config->getSearchParameter( "search term properties" );

		if ( $properties !== false ) {
			return $properties;
		}

		// Fallback fields if no field is specified in the highlighted properties or search term properties
		return [
			new PropertyFieldMapper( "text_raw" ),
			new PropertyFieldMapper( "text_copy" ),
			new PropertyFieldMapper( "attachment-content" )
		];
	}
}
