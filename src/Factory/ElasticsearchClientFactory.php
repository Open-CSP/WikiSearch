<?php

namespace WikiSearch\Factory;

use Config;

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
	 * @return \Elastic\Elasticsearch\Client|\Elasticsearch\Client
	 */
	public function newElasticsearchClient() {
        $clientBuilder = class_exists("\Elastic\Elasticsearch\ClientBuilder") ?
            \Elastic\Elasticsearch\ClientBuilder::create() :
            \Elasticsearch\ClientBuilder::create();

		$this->addHosts( $clientBuilder );
		$this->addBasicAuthentication( $clientBuilder );

		return $clientBuilder->build();
	}

	/**
	 * Adds the Elasticsearch hosts.
	 *
	 * @param \Elastic\Elasticsearch\ClientBuilder|\Elasticsearch\ClientBuilder $builder
	 * @return void
	 */
	private function addHosts( $builder ): void {
		$builder->setHosts( $this->getElasticsearchHosts() );
	}

	/**
	 * Adds basic authentication if available.
	 *
	 * @param \Elastic\Elasticsearch\ClientBuilder|\Elasticsearch\ClientBuilder $builder
	 * @return void
	 */
	private function addBasicAuthentication( $builder ): void {
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
		return array_map( static function ( array|string $endpoint ) {
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
