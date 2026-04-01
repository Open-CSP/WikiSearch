<?php

namespace WikiSearch\Maintenance;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Maintenance\MaintenanceFatalError;
use WikiSearch\WikiSearchServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class setupNeuralSearch extends Maintenance {
    private const MODEL_NAME = "huggingface/sentence-transformers/all-MiniLM-L6-v2";
    private const MODEL_VERSION = "1.0.1";
    private const PIPELINE_NAME = "wsns-pipeline";

    /**
     * @var \Elastic\Elasticsearch\Client|\Elasticsearch\Client|\OpenSearch\Client The ElasticSearch/OpenSearch client
     */
    private $client;

    public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Set-up OpenSearch for use with neural search'
		);

		$this->requireExtension( 'WikiSearch' );
	}

	public function execute() {
        $this->client = WikiSearchServices::getElasticsearchClientFactory()->newElasticsearchClient();

        $this->output( "Checking distribution ...\n" );
        $distribution = $this->getDistribution();

        if ( $distribution !== 'opensearch' ) {
            $this->fatalError( "\n\nERROR: Distribution must be 'opensearch', got '$distribution'.\n");
        } else {
            $this->output( "\t... confirmed OpenSearch ...\n");
            $this->output( "\t... done.\n" );
        }

        $this->output( "\n" );

        $this->client->cluster()->putSettings( [
            "body" => [
                "persistent" => [
                    "plugins.ml_commons.only_run_on_ml_node" => false
                ]
            ]

        ] );

        $config = $this->getServiceContainer()->getMainConfig();
        $modelId = $config->get( "WikiSearchNeuralSearchModelId" );

        $this->output( "Model registration ...\n" );
        $this->output( "\t... checking for existing model ...\n" );
        if ( !isset( $modelId ) ) {
            try {
                $this->output( "\t... registering model (may take some time) ...\n");

                $response = $this->registerModel();
                $modelId = $response['modelId'];

                $this->output( "\t... deploying model ...\n" );
                $this->deployModel( $modelId );

                // TODO: Store model ID in database
                $this->output( "\t... done.\n");
            } catch ( \Exception $e ) {
                $this->fatalError( "\n\nERROR: Failed to register model: " . $e->getMessage() . "\n" );
            }
        } else {
            $this->output( "\t... model already exists, skipping ...\n" );
            $this->output( "\t... done.\n");
        }

        $this->output( "\n" );

        try {
            $this->output( "Embedding pipeline ...\n");
            $this->output( "\t... (re)creating embedding pipeline ...\n" );
            $this->putEmbeddingPipeline( $modelId );
            $this->output( "\t... embedding pipeline (re)created ...\n");
            $this->output( "\t... done.\n");
        } catch ( \Exception $e ) {
            $this->fatalError( "\n\nERROR: Failed to create embedding pipeline: " . $e->getMessage() . "\n" );
        }
	}

    public function registerModel(): array {
        $response = $this->client->ml()->registerModel( [
            'body' => [
                'name' => self::MODEL_NAME,
                'version' => self::MODEL_VERSION,
                'model_format' => 'TORCH_SCRIPT'
            ]
        ] );

        $taskResponse = $this->awaitTask( $response['task_id'] );
        $modelId = $taskResponse['model_id'];

        return [
            'created' => true,
            'modelId' => $modelId,
        ];
    }

    public function deployModel( string $modelId ): void {
        $response = $this->client->ml()->getModel(['id' => $modelId]);
        $state = $response['model_state'] ?? null;

        if ( !in_array( $state, ['REGISTERED', 'UNDEPLOYED'], strict: true ) ) {
            throw new \Exception(
                "Model '$modelId' cannot be deployed from state: '$state'."
            );
        }

        $this->client->ml()->deployModel(['id' => $modelId]);
    }

    /**
     * Creates the embedding pipeline, if it does not yet exist.
     *
     * @param string $modelId
     * @return array{created: bool}
     * @throws \Exception When the creation of the pipeline failed
     */
    private function putEmbeddingPipeline( string $modelId ): array {
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

        $this->client->ingest()->deletePipeline( ['id' => self::PIPELINE_NAME] );
        $response = $this->client->ingest()->putPipeline( [
            'id' => self::PIPELINE_NAME,
            'body' => $body,
        ] );

        if ( !is_array( $response ) ) {
            $response = $response->asArray();
        }

        $acknowledged = $response['acknowledged'] ?? false;

        if ( !$acknowledged ) {
            throw new \Exception( 'Pipeline not acknowledged' );
        }

        return ['created' => true];
    }

    /**
     * @param array $pipeline
     * @return array
     * @throws \Exception
     */
    private function awaitTask( string $taskId ): array {
        do {
            $task = $this->client->ml()->getTask( ['id' => $taskId] );
            usleep( 2.5 * 1000 * 1000 );
        } while ( $task['state'] === 'CREATED' || $task['state'] === 'RUNNING' );

        if ( $task['state'] !== 'COMPLETED' ) {
            throw new \Exception( 'Task failed to complete: ' . json_encode( $task ) );
        }

        return $task;
    }

    private function getDistribution(): string {
        $info = $this->client->info();

        if ( !is_array( $info ) ) {
            $info = $info->asArray();
        }

        return $info['version']['distribution'] ?? 'unknown';
    }
}

$maintClass = SetupNeuralSearch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
