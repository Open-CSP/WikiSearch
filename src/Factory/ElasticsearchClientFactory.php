<?php

namespace WikiSearch\Factory;

use Config;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;

class ElasticsearchClientFactory {
    private Config $config;

    /**
     * @param Config $config
     */
    public function __construct( Config $config ) {
        $this->config = $config;
    }

    /**
     * Construct a new Elasticsearch Client instance.
     *
     * @return Client
     * @throws AuthenticationException
     */
    public function newElasticsearchClient(): Client {
        $client = ClientBuilder::create();

        $this->addHosts( $client );
        $this->addBasicAuthentication( $client );

        return $client->build();
    }

    /**
     * Adds the Elasticsearch hosts.
     *
     * @param ClientBuilder $builder
     * @return void
     */
    private function addHosts( ClientBuilder $builder ): void {
        $builder->setHosts( $this->getElasticsearchHosts() );
    }

    /**
     * Adds basic authentication if available.
     *
     * @param ClientBuilder $builder
     * @return void
     */
    private function addBasicAuthentication( ClientBuilder $builder ): void {
        $username = $this->config->get( 'WikiSearchBasicAuthenticationUsername' );
        $password = $this->config->get( 'WikiSearchBasicAuthenticationPassword' );

        if ( $username !== null && $password !== null ) {
            $builder->setBasicAuthentication( $username, $password );
        }
    }

    /**
     * Returns the hosts.
     *
     * @return string[]
     */
    private function getElasticsearchHosts(): array {
        // phpcs:ignore
        global $smwgElasticsearchEndpoints;

        $hosts = $this->config->get( "WikiSearchElasticSearchHosts" ) ?? $smwgElasticsearchEndpoints;

        if ( empty( $hosts ) ) {
            // If no hosts are available, default to "localhost:9200"
            return [ "localhost:9200" ];
        }

        // Support both the array syntax (array{host: string, port: int, scheme: string}) and the string syntax
        return array_map( function ( array|string $endpoint ) {
            if ( is_string( $endpoint ) ) {
                return $endpoint;
            }

            $scheme = $endpoint['scheme'];
            $host = $endpoint['host'];
            $port = $endpoint['port'];

            return "$scheme://$host:$port";
        }, $hosts );
    }
}