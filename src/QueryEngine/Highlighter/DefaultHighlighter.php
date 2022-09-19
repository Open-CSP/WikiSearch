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
	private const FALLBACK_HIGHLIGHT_FIELDS = [
		"text_raw",
		"text_raw.search",
		"text_copy",
		"text_copy.search",
		"attachment.content"
	];

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

		foreach ( $this->fields as $field ) {
            $field_settings = $this->common_field_settings;
            $highlighter_type = $this->config->getSearchParameter( "highlighter type" );

			if ( is_string( $field ) ) {
                if ( $highlighter_type !== null && !( new PropertyFieldMapper( $field ) )->isInternalProperty() ) {
                    $field_settings["type"] = $highlighter_type;
                }

				$highlight->addField( $field, $field_settings );
			} else {
                if ( $highlighter_type !== null ) {
                    $field_settings['type'] = $highlighter_type;
                }

				$field_settings['matched_fields'] = $field;
				$highlight->addField( $field[0], $field_settings );
			}
		}

		return $highlight;
	}

	/**
	 * Returns an array of fields to highlight if no specific fields are given in the constructor.
	 *
	 * @return array
	 */
	private function getDefaultFields(): array {
		$properties =
			$this->config->getSearchParameter( "highlighted properties" ) ?:
			$this->config->getSearchParameter( "search term properties" );

		if ( $properties !== false ) {
			$res = [];

			foreach ( $properties as $property ) {
				if ( !$property->hasSearchSubfield() ) {
					$res[] = $property->getPropertyField();
				} else {
					$res[] = [
						$property->getPropertyField(),
						$property->getSearchField()
					];
				}
			}

			return $res;
		}

		// Fallback fields if no field is specified in the highlighted properties or search term properties
		return self::FALLBACK_HIGHLIGHT_FIELDS;
	}
}
