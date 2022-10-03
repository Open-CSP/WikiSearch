<?php

namespace WikiSearch\SMW\Annotators;

use Content;
use ParserOutput;
use SMW\DIProperty;
use SMW\SemanticData;
use SMWDIBlob;

/**
 * This annotator contains information about images that are used on a page.
 */
class ImagesAnnotator implements Annotator {
	/**
	 * @inheritDoc
	 */
	public static function addAnnotation( Content $content, ParserOutput $parserOutput, SemanticData $semanticData ): void {
		$images = array_keys( $parserOutput->getImages() );

		foreach ( $images as $image ) {
			$semanticData->addPropertyObjectValue(
				new DIProperty( self::getId() ),
				new SMWDIBlob( $image )
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public static function getId(): string {
		return '__wikisearch_image_links';
	}

	/**
	 * @inheritDoc
	 */
	public static function getLabel(): string {
		return 'Image links';
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
}
