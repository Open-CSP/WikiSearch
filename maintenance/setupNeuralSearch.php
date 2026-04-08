<?php

namespace WikiSearch\Maintenance;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Maintenance\MaintenanceFatalError;
use WikiSearch\SMW\PropertyFieldMapper;
use WikiSearch\WikiSearchServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class setupNeuralSearch extends Maintenance {
    private const MODELS = [
        "embedding" => [
            "name" => "huggingface/sentence-transformers/all-MiniLM-L6-v2",
            "version" => "1.0.1",
        ],
        // Highlighting is currently not supported
        //        "highlighting" => [
        //            "name" => "amazon/sentence-highlighting/opensearch-semantic-highlighter-v1",
        //            "version" => "1.0.0",
        //            "function_name" => "QUESTION_ANSWERING"
        //        ]
    ];

    private const EMBEDDING_PIPELINE_NAME = "wsns-pipeline";

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

        $this->output( "Checking distribution and version ...\n" );
        $version = $this->getVersion();

        if ( $version['distribution'] !== 'opensearch' ) {
            $this->fatalError( "\n\nERROR: Distribution must be 'opensearch', got '{$version['distribution']}'.\n");
        } else if ( version_compare( $version['number'], '2.4.0', '<' ) ) {
            $this->fatalError( "\n\nERROR: Version must be 2.4.0 or greater, got '{$version['number']}'.\n");
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
        $models = $config->get( "WikiSearchNeuralModels" );

        $embeddingModelId = $models['embedding'] ?? null;

        $this->output( "Model registration ...\n" );

        foreach ( self::MODELS as $modelKey => $modelSpec ) {
            if ( isset( $models[$modelKey] ) ) {
                $this->output( "\t... `$modelKey` model already deployed, skipping ...\n");
                continue;
            }

            try {
                $this->output( "\t... registering `$modelKey` model (may take some time) ...\n");
                $response = $this->registerModel( $modelSpec['name'], $modelSpec['version'], $modelSpec['function_name'] ?? null );
                $modelId = $response['modelId'];

                $this->output( "\t... deploying `$modelKey` model ...\n" );
                $this->deployModel( $modelId );

                $this->output( "\t... `$modelKey` model deployed ...\n" );
                $this->output( "\t... IMPORTANT: add `\$wgWikiSearchNeuralModels['$modelKey'] = '$modelId';` to your LocalSettings.php ...\n" );

                if ( $modelKey === 'embedding' ) {
                    $embeddingModelId = $modelId;
                }
            } catch ( \Exception $e ) {
                $this->fatalError( "\n\nERROR: Failed to deploy `$modelKey` model: " . $e->getMessage() . "\n" );
            }
        }

        $this->output( "\t... done.\n\n" );

        $embeddedProperties = $config->get( "WikiSearchNeuralEmbeddedProperties" );

        try {
            $this->output( "Embedding pipeline ...\n");
            $this->output( "\t... (re)creating embedding pipeline ...\n" );
            $this->putEmbeddingPipeline( $embeddingModelId, $embeddedProperties );
            $this->output( "\t... embedding pipeline (re)created ...\n");
            $this->output( "\t... done.\n");
        } catch ( \Exception $e ) {
            $this->fatalError( "\n\nERROR: Failed to create embedding pipeline: " . $e->getMessage() . "\n" );
        }
	}

    public function registerModel( string $modelName, string $modelVersion, ?string $functionName ): array {
        $body = array_filter( [
            'name' => $modelName,
            'version' => $modelVersion,
            'model_format' => 'TORCH_SCRIPT',
            'function_name' => $functionName,
        ] );

        $response = $this->client->ml()->registerModel( [ 'body' => $body ] );
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
     * @param array $properties
     * @return array{created: bool}
     * @throws \Exception When the creation of the pipeline failed
     */
    private function putEmbeddingPipeline( string $modelId, array $embeddedProperties ): array {
        $fieldMap = [
            'text_raw' => ( new PropertyFieldMapper( 'text_raw' ) )->getEmbeddingField(),
            'subject.title' => ( new PropertyFieldMapper( 'subject.title' ) )->getEmbeddingField(),
        ];

        foreach ( $embeddedProperties as $property ) {
            $propertyFieldMapper = new PropertyFieldMapper( $property );
            $fieldMap[$property] = $propertyFieldMapper->getEmbeddingField();
        }

        $body = [
            'description' => 'WikiSearch neural embedding generation',
            'processors' => [
                [
                    'text_embedding' => [
                        'model_id'  => $modelId,
                        'field_map' => $fieldMap,
                    ],
                ],
            ],
        ];

        if ( $this->ingestPipelineExists( self::EMBEDDING_PIPELINE_NAME ) ) {
            $this->client->ingest()->deletePipeline( ['id' => self::EMBEDDING_PIPELINE_NAME] );
        }

        $response = $this->client->ingest()->putPipeline( [
            'id' => self::EMBEDDING_PIPELINE_NAME,
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
     * Whether the specified ingest pipeline exists.
     *
     * @param string $pipelineId
     * @return bool
     * @throws \Exception
     */
    private function ingestPipelineExists( string $pipelineId ): bool {
        try {
            $this->client->ingest()->getPipeline( ['id' => $pipelineId ] );
            return true;
        } catch ( \Exception $e ) {
            if (
                $e instanceof \OpenSearch\Common\Exceptions\Missing404Exception
                || $e instanceof \Elasticsearch\Common\Exceptions\Missing404Exception
                || ($e instanceof \Elastic\Elasticsearch\Exception\ClientResponseException && $e->getResponse()->getStatusCode() === 404 )
            ) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * @param array $pipeline
     * @return array
     * @throws \Exception
     */
    private function awaitTask( string $taskId ): array {
        $i = 0;

        do {
            $task = $this->client->ml()->getTask( ['id' => $taskId] );
            sleep( 3 );
        } while ( ( $task['state'] === 'CREATED' || $task['state'] === 'RUNNING' ) && $i++ < 50 );

        if ( $task['state'] !== 'COMPLETED' ) {
            throw new \Exception( 'Task failed to complete: ' . json_encode( $task ) );
        }

        return $task;
    }

    private function getVersion(): array {
        $info = $this->client->info();

        if ( !is_array( $info ) ) {
            $info = $info->asArray();
        }

        return $info['version'];
    }
}

$maintClass = SetupNeuralSearch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
