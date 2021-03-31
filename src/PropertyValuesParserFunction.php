<?php

namespace WSSearch;

use Elasticsearch\ClientBuilder;
use Parser;
use WSSearch\QueryEngine\Aggregation\FilterAggregation;
use WSSearch\QueryEngine\Aggregation\PropertyAggregation;
use WSSearch\QueryEngine\Factory\QueryEngineFactory;
use WSSearch\QueryEngine\Filter\PropertyRangeFilter;

/**
 * Class PropertyValuesParserFunction
 *
 * @package WSSearch
 */
class PropertyValuesParserFunction {
    /**
     * Callback for the parser function {{#prop_values}}.
     *
     * @param Parser $parser
     * @param string ...$args
     * @return string
     * @throws \MWException
     */
    public function execute( Parser $parser, ...$args ) {
        if ( !class_exists( "\WSArrays" ) ) {
            return "WSArrays must be installed.";
        }

        $options = $this->extractOptions( $args );

        $limit = isset( $options["limit"] ) ? $options["limit"] : "100";
        $property = isset( $options["property"] ) ? $options["property"] : "";
        $array_name = isset( $options["array"] ) ? $options["array"] : "";
        $date_property = isset( $options["date property"] ) ? $options["date property"] : "Modification date";

        if ( !$property || !$array_name ) {
            return "Missing `array` or `property` parameter";
        }

        if ( !isset( $options["from"] ) || !isset( $options["to"] ) ) {
            return "Missing `from` or `to` parameter";
        }

        if ( !ctype_digit( $options["from"] ) || !ctype_digit( $options["to"] ) || !ctype_digit( $limit ) ) {
            return "Invalid `from`, `limit` or `to` parameter";
        }

        list( $from, $to ) = $this->convertDate( $options["from"], $options["to"] );

        $range_filter = new PropertyRangeFilter(
            $date_property,
            [ "to" => $to, "from" => $from ]
        );

        $terms_aggregation = new PropertyAggregation( $property, "common_values", $limit );

        $aggregation = new FilterAggregation(
            $range_filter,
            [$terms_aggregation],
            "property_values"
        );

        $query_engine = QueryEngineFactory::fromNull();
        $query_engine->addAggregation( $aggregation );
        $query_engine->setLimit( 9999 );

        $query = $query_engine->toArray();

        $results = ClientBuilder::create()
            ->setHosts( QueryEngineFactory::fromNull()->getElasticHosts() )
            ->build()
            ->search( $query );

        $buckets = $results["aggregations"]["property_values"]["common_values"]["buckets"];

        \WSArrays::$arrays[$array_name] = new \ComplexArray( $buckets );

        return "";
    }

    /**
     * Turns the given options of the format "a=b" into array values of the format "a" => "b".
     *
     * @param array $options
     * @return array
     */
    private function extractOptions( array $options ): array {
        $results = [];

        foreach ( $options as $option ) {
            $pair = array_map( 'trim', explode( '=', $option, 2 ) );

            if ( count( $pair ) === 2 ) {
                $results[ $pair[0] ] = $pair[1];
            }

            if ( count( $pair ) === 1 ) {
                $results[ $pair[0] ] = true;
            }
        }

        return $results;
    }

    /**
     * Converts the given dates to Julian dates.
     *
     * @param int $from_year
     * @param int $to_year
     * @return array
     */
    private function convertDate( int $from_year, int $to_year ): array {
        return [ gregoriantojd( 1, 1, $from_year ), gregoriantojd( 12, 31, $to_year ) ];
    }
}