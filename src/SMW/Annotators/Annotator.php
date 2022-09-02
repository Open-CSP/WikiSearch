<?php

namespace WikiSearch\SMW\Annotators;

use ParserOutput;
use SMW\SemanticData;

interface Annotator {
    public const ANNOTATORS = [
        AltTextAnnotator::class
    ];

    /**
     * Analyze the given parser output and decorate the given semantic data object with the results.
     *
     * @param ParserOutput $parserOutput
     * @param SemanticData $semanticData
     */
    public static function addAnnotation( ParserOutput $parserOutput, SemanticData $semanticData ): void;

    /**
     * Returns the ID of annotation that will be added.
     *
     * @return string
     */
    public static function getId(): string;

    /**
     * Returns the label of annotation that will be added.
     *
     * @return string
     */
    public static function getLabel(): string;

    /**
     * Returns the definition of the annotation that will be added.
     *
     * @return array
     */
    public static function getDefinition(): array;
}
