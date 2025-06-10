<?php

namespace WikiSearch\SMW\Annotators;

use Content;
use Exception;
use ParserOutput;
use SMW\DIProperty;
use SMW\SemanticData;
use SMWDIBlob;
use voku\helper\HtmlDomParser;

/**
 * This annotator contains information about images "alt" attributes.
 */
class AltTextAnnotator implements Annotator {
	/**
	 * @inheritDoc
	 */
	public static function addAnnotation( Content $content, ParserOutput $parserOutput, SemanticData $semanticData ): void {
		// Get the HTML
		$text = $parserOutput->getText();
		$altTexts = self::getAltTexts( $text );

		foreach ( $altTexts as $altText ) {
			$semanticData->addPropertyObjectValue(
				new DIProperty( self::getId() ),
				new SMWDIBlob( $altText )
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public static function getId(): string {
		return '__wikisearch_image_alt_texts';
	}

	/**
	 * @inheritDoc
	 */
	public static function getLabel(): string {
		return 'Image alt texts';
	}

	/**
	 * @inheritDoc
	 */
	public static function getDefinition(): array {
		return [
			'label' => self::getLabel(),
			'type' => '_txt',
			'viewable' => true,
			'annotable' => false
		];
	}

	/**
	 * Parses the given HTML and returns the alt texts.
	 *
	 * @param string $html
	 * @return array
	 */
	private static function getAltTexts( string $html ): array {
		// Parse the DOM
		$dom = HtmlDomParser::str_get_html( $html );

		try {
			$imgElements = $dom->find( 'img' );
		} catch ( Exception $exception ) {
			return [];
		}

		$altTexts = [];

		foreach ( $imgElements as $element ) {
			$altText = $element->getAttribute( 'alt' );

			if ( $altText ) {
				$altTexts[] = $altText;
			}
		}

		return array_unique( $altTexts );
	}
}
