<?php

namespace WikiSearch\Maintenance;

use Maintenance;
use WikiSearch\Factory\ElasticsearchClientFactory;
use WikiSearch\NeuralSearch\OpenSearchNeuralSearchBackend;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Creates the OpenSearch index template required for WikiSearch neural search.
 *
 * The template pre-applies "index.knn: true" and the knn_vector field mapping
 * to every new index whose name matches "smw-data-*". This must be done via an
 * index template because OpenSearch treats index.knn as a final (creation-only)
 * setting that cannot be added via the settings-update call that SMW issues
 * during rebuildElasticIndex.php.
 *
 * Usage:
 *   php maintenance/run.php WikiSearch:SetupNeuralSearch
 *
 * After running this script, delete the existing SMW index and rebuild it:
 *   curl -X DELETE http://localhost:9200/smw-data-<wikiid>-v*
 *   php maintenance/run.php SMW:rebuildElasticIndex
 */
class SetupNeuralSearch extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Creates the OpenSearch index template required for WikiSearch neural (knn) search. ' .
			'Run once before rebuilding the SMW Elasticsearch index.'
		);
		$this->requireExtension( 'WikiSearch' );
	}

	public function execute() {
		$config = $this->getServiceContainer()->getMainConfig();

		$modelId = $config->get( 'WikiSearchNeuralSearchModelId' );
		if ( $modelId === null ) {
			$this->fatalError(
				'$wgWikiSearchNeuralSearchModelId is not set. ' .
				'Configure it in LocalSettings.php before running this script.'
			);
		}

		$dimension = (int)$config->get( 'WikiSearchNeuralSearchDimension' );

		$clientFactory = new ElasticsearchClientFactory( $config );
		$hosts    = $clientFactory->getHosts();
		$username = $config->get( 'WikiSearchBasicAuthenticationUsername' );
		$password = $config->get( 'WikiSearchBasicAuthenticationPassword' );

		$backend = new OpenSearchNeuralSearchBackend( $modelId, $hosts, $username, $password );

		$this->output( "Creating OpenSearch index template 'wikisearch-neural-knn' for pattern 'smw-data-*'...\n" );

		try {
			$backend->ensureIndexTemplate( 'smw-data-*', $dimension );
		} catch ( \RuntimeException $e ) {
			$this->fatalError( 'Failed: ' . $e->getMessage() );
		}

		$this->output( "Done.\n\n" );
		$this->output( "Next steps:\n" );
		$this->output( "  1. Delete the existing SMW index, e.g.:\n" );
		$this->output( "       curl -X DELETE http://localhost:9200/smw-data-<wikiid>-v*\n" );
		$this->output( "  2. Rebuild the index:\n" );
		$this->output( "       php maintenance/run.php SMW:rebuildElasticIndex\n" );
	}
}

$maintClass = SetupNeuralSearch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
