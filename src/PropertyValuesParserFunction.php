<?php

namespace WSSearch;

use Elasticsearch\ClientBuilder;
use Parser;
use WSSearch\QueryEngine\Aggregation\FilterAggregation;
use WSSearch\QueryEngine\Aggregation\PropertyValueAggregation;
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
        $from = isset( $options["from"] ) ? $options["from"] : "1";
        $to = isset( $options["to"] ) ? $options["to"] : "5000";

        if ( !$property || !$array_name ) {
            return "Missing `array` or `property` parameter";
        }

        if ( !ctype_digit( $from ) || !ctype_digit( $to ) || !ctype_digit( $limit ) ) {
            return "Invalid `from`, `limit` or `to` parameter";
        }

        $from = intval( $from );
        $to = intval( $to );
        $limit = intval( $limit );

        if ( $from < 0 || $from > 9999 ) {
            return "The `from` parameter must be a year between 0 and 10000";
        }

        if ( $to < 0 || $to > 9999 ) {
            return "The `to` parameter must be a year between 0 and 10000";
        }

        if ( $to <= $from ) {
            return "The `to` parameter must be greater than the `from` parameter";
        }

        if ( $limit < 1 || $limit > 9999 ) {
            return "The `limit` parameter must be an integer between 1 and 10000";
        }

        list( $from, $to ) = $this->convertDate( $from, $to );

        $range_filter = new PropertyRangeFilter(
            $date_property,
            [ "to" => $to, "from" => $from ]
        );

        $terms_aggregation = new PropertyValueAggregation( $property, "common_values", $limit );

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