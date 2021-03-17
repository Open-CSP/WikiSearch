<?php


namespace WSSearch\SMW;

use SMW\PropertyRegistry;

/**
 * Class PropertyLabelMapper
 *
 * This class maps the given property key to its corresponding property label and visa-versa.
 *
 * @package WSSearch\SMW
 */
class PropertyAliasMapper {
    /**
     * Maps the given property key to its corresponding property label.
     *
     * @param string $property_key
     * @return string
     */
    public static function findPropertyLabel( string $property_key ): string {
        return ( PropertyRegistry::getInstance() )->findPropertyLabelById( $property_key );
    }

    /**
     * Maps the given property label to its corresponding property key.
     *
     * @param string $property_label
     * @return string
     */
    public static function findPropertyKey( string $property_label ): string {
        $property_key = ( PropertyRegistry::getInstance() )->findPropertyIdByLabel( $property_label );

        if ( $property_key === false ) {
            return $property_label;
        }

        return $property_key;
    }
}