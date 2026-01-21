<?php

namespace WikiSearch\SMW\Annotators;

use Content;
use DOMDocument;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParserOutput;
use RequestContext;
use SMW\DIProperty;
use SMW\SemanticData;
use SMWDIBlob;
use Title;

/**
 * This annotator contains information about the parsed text of pages.
 */
class ParsedTextAnnotator implements Annotator {
	/**
	 * @inheritDoc
	 */
	public static function addAnnotation( Content $content, ParserOutput $parserOutput, SemanticData $semanticData ): void {
		$output = self::getParserOutput( $content, $semanticData->getSubject()->getTitle() );

		if ( $output === null ) {
			// The content is not wikitext, so it does not make sense to add the parsed text annotation
			return;
		}

		// Get the HTML from the ParserOutput object
		$html = $output->getText();

		// Remove "no-index" elements
		$html = self::purifyNativeData( $html );

		// Strip all HTML tags from the parser output
		$text = str_replace( "  ", " ", strip_tags( $html ) );

		$semanticData->addPropertyObjectValue(
			new DIProperty( self::getId() ),
			new SMWDIBlob( $text )
		);
	}

	/**
	 * @inheritDoc
	 */
	public static function getId(): string {
		return '__wikisearch_parsed_text';
	}

	/**
	 * @inheritDoc
	 */
	public static function getLabel(): string {
		return 'Parsed text';
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
	 * Removes all "no-index" elements.
	 *
	 * @param string $html
	 * @return string
	 */
	private static function purifyNativeData( string $html ): string {
		$dom = new DOMDocument();
		@$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );

		$selector = new \DOMXPath( $dom );

		// Filter out div elements with the class "smw-no-index"
		$query = $selector->query( '//*[contains(attribute::class, "smw-no-index")]' );
		foreach ( $query as $element ) {
			$element->parentNode->removeChild( $element );
		}

		// Filter out glossary elements
		$query = $selector->query( '//div[contains(attribute::class, "mw-lingo-definition-text")]' );
		foreach ( $query as $element ) {
			$element->parentNode->removeChild( $element );
		}

		$query = $selector->query( '//div[contains(attribute::class, "mw-lingo-tooltip")]' );
		foreach ( $query as $element ) {
			$element->parentNode->removeChild( $element );
		}

		$query = $selector->query( '//*[contains(attribute::class, "searchaux")]' );
		foreach ( $query as $element ) {
			$element->parentNode->removeChild( $element );
		}

		$html = $dom->saveHTML();
		$html = html_entity_decode( $html );

		return str_replace( "<", " <", $html );
	}

	/**
	 * Returns the specified magic word.
	 *
	 * @param string $magic_word
	 * @return string
	 */
	private static function magicWord( string $magic_word ): string {
		return sprintf( "__%s__", $magic_word );
	}

	/**
	 * Get the parser output from the given content.
	 *
	 * @param Content $content The content to get the parser output for
	 * @param Title $title
	 * @return ParserOutput|null
	 */
	private static function getParserOutput( Content $content, Title $title ): ?ParserOutput {
		if ( $content->getModel() !== CONTENT_MODEL_WIKITEXT ) {
			return null;
		}

		// Add the magic words "NOTOC" and "NOEDITSECTION" to the wikitext
		$magicWords = self::magicWord( "NOTOC" ) . self::magicWord( "NOEDITSECTION" );

		if ( ExtensionRegistry::getInstance()->isLoaded( 'Lingo' ) ) {
			// Add the magic word "NOGLOSSARY" if Lingo is loaded
			$magicWords .= self::magicWord( "NOGLOSSARY" );
		}

		$context = RequestContext::getMain();
		$user = $context->getUser();

		try {
			$lang = $context->getLanguage();
		} catch ( \Exception $exception ) {
			// Unable to get proper language object, use the content language
			$lang = $GLOBALS['wgLang'];
		}

		// Create the new ParserOptions from the current user and the language
		$parserOptions = ParserOptions::newFromUserAndLang( $user, $lang );
		// Use a fresh parser instance to avoid race conditions with the singleton parser
		$parser = MediaWikiServices::getInstance()->getParserFactory()->create();

		return $parser->parse( $magicWords . $content->getNativeData(), $title, $parserOptions, true, true );
	}
}
