<?php

namespace WikiSearch\Maintenance;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use MediaWiki\Maintenance\Maintenance;
use WikiSearch\WikiSearchServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class setupNeuralSearch extends Maintenance {
    private const PIPELINE_NAME = "wsns-pipeline";

    /**
     * @var \Elastic\Elasticsearch\Client|\Elasticsearch\Client The ElasticSearch/OpenSearch client
     */
    private $client;

    public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Set-up OpenSearch for use with neural search'
		);

		$this->requireExtension( 'WikiSearch' );

        $this->client = WikiSearchServices::getElasticsearchClientFactory()
            ->newElasticsearchClient();
	}

	public function execute() {
        $this->ensureOpenSearch();

		$config = $this->getServiceContainer()->getMainConfig();
		$modelId = $config->get( 'WikiSearchNeuralSearchModelId' );

		if ( $modelId === null ) {
			$this->fatalError(
				'$wgWikiSearchNeuralSearchModelId is not set. ' .
				'Configure it in LocalSettings.php before running this script.'
			);
		}

        $this->putEmbeddingPipeline( $modelId );

        $this->output( "Done.\n");
	}

    /**
     * @param string $modelId
     * @return void
     * @throws \MediaWiki\Maintenance\MaintenanceFatalError
     */
    private function putEmbeddingPipeline( string $modelId ) {
        $body = [
            'description' => 'WikiSearch neural embedding generation',
            'processors' => [
                [
                    'text_embedding' => [
                        'model_id'  => $modelId,
                        'field_map' => [
                            'text_raw' => 'text_raw_embedding',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $result = $this->putPipelineIfNotExists( [
                'id' => self::PIPELINE_NAME,
                'body' => $body,
            ] );

            $this->output( $result['message'] . "\n" );
        } catch ( \Exception $e ) {
            $this->fatalError( 'ERROR: Failed to create pipeline: ' . $e->getMessage() . "\n" );
        }
    }

    /**
     * @param array $pipeline
     * @return array
     * @throws \Exception
     */
    private function putPipelineIfNotExists( array $pipeline ): array {
        try {
            $this->client->ingest()->getPipeline(['id' => $pipeline['id']]);

            return [
                'success' => true,
                'created' => false,
                'message' => "Embedding pipeline already exists, skipping...",
            ];
        } catch (\Exception $e) {
            if ( method_exists( 'getResponse', $e ) && $e->getResponse()->getStatusCode() !== 404 ) {
                throw $e;
            }

            if ( !$e instanceof Missing404Exception && !$e instanceof \OpenSearch\Common\Exceptions\Missing404Exception ) {
                throw $e;
            }
        }

        $response = $this->client->ingest()->putPipeline( $pipeline );
        if ( !is_array( $response ) ) {
            $response = $response->asArray();
        }

        $acknowledged = $response['acknowledged'] ?? false;

        if (!$acknowledged) {
            throw new \Exception( 'Pipeline not acknowledged' );
        }

        return [
            'created' => true,
            'message' => "Pipeline '{$pipeline['id']}' created successfully...",
        ];
    }

    private function ensureOpenSearch(): void {
        try {
            $info = $this->client->info();
        } catch ( \Exception $e ) {
            $this->fatalError( 'ERROR: Failed to connect to OpenSearch: ' . $e->getMessage() );
        }

        if ( !is_array( $info ) ) {
            $info = $info->asArray();
        }

        $distribution = $info['version']['distribution'] ?? 'unknown';

        if ($distribution !== 'opensearch') {
            $this->fatalError( 'ERROR: Neural search is only supported with OpenSearch. You are running ' . $distribution . '.');
        }
    }
}

$maintClass = SetupNeuralSearch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
