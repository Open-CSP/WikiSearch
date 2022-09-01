<?php

namespace WikiSearch\Special;

use HTMLForm;
use PermissionsError;
use SpecialPage;
use Status;

/**
 * Implements Special:WikiSearchDataStandard.
 */
class SpecialWikiSearchDataStandard extends SpecialPage {
    /**
     * @var string The canonical location of the data standard
     */
    private string $dataStandardLocation;

    public function __construct() {
        parent::__construct( 'WikiSearchDataStandard', 'wikisearch-edit-data-standard', false );
    }

    /**
     * @inheritDoc
     * @throws PermissionsError
     */
    public function execute($subPage ) {
        $this->setHeaders();
        $this->checkPermissions();

        if ( !isset( $GLOBALS['smwgElasticsearchConfig']['index_def']['data'] ) ) {
            $this->getOutput()->showErrorPage(
                'wikisearch-special-data-standard-missing-path-title',
                'wikisearch-special-data-standard-missing-path-text'
            );

            return;
        }

        $this->dataStandardLocation = realpath( $GLOBALS['smwgElasticsearchConfig']['index_def']['data'] );

        if ( !$this->checkDataStandardLocation() ) {
            $this->getOutput()->showErrorPage(
                'wikisearch-special-data-standard-invalid-path-title',
                'wikisearch-special-data-standard-invalid-path-text',
                [ $this->dataStandardLocation ]
            );

            return;
        }

        $dataStandard = file_get_contents( $this->dataStandardLocation );

        if ( $dataStandard === false ) {
            $this->getOutput()->showErrorPage(
                'wikisearch-special-data-standard-could-not-read-title',
                'wikisearch-special-data-standard-could-not-read-text',
                [ $this->dataStandardLocation ]
            );

            return;
        }

        $this->showForm( $this->getFormDescriptor( $dataStandard ) );
    }

    /**
     * Validates the given data standard. This only validates the formatting, and not the actual contents of the data
     * standard.
     *
     * @param string $dataStandard
     * @return bool|string
     */
    public function checkDataStandard( string $dataStandard ) {
        if ( is_array( json_decode( $dataStandard, true ) ) ) {
            return true;
        }

        return $this->msg( "wikisearch-special-data-standard-invalid-json", json_last_error_msg() )->parse();
    }

    /**
     * @param string $dataStandard
     * @return string
     */
    public function formatDataStandard( string $dataStandard ): string {
        if ( $this->checkDataStandard( $dataStandard ) !== true ) {
            // Nothing to format if the standard is invalid
            return $dataStandard;
        }

        return json_encode( json_decode( $dataStandard ), JSON_PRETTY_PRINT );
    }

    /**
     * Called upon submitting the form.
     *
     * @param array $formData The data that was submitted
     * @return Status
     */
    public function formCallback( array $formData ): Status {
        // $dataStandard is validated and formatted, and may be used directly
        $dataStandard = $formData['datastandard'];
        $result = file_put_contents( $this->dataStandardLocation, $dataStandard, LOCK_EX );

        if ( $result === false ) {
            return Status::newFatal( 'wikisearch-special-data-standard-write-failed' );
        }

        if ( $formData['update'] ) {
            $log = '';
            $pid = $this->spawnUpdate( $log );

            $this->getOutput()->addWikiMsg( 'wikisearch-special-data-standard-save-and-update-success', $pid, $log );
        } else {
            $this->getOutput()->addWikiMsg( 'wikisearch-special-data-standard-save-only-success' );
        }

        return Status::newGood();
    }

    /**
     * Shows the edit form.
     *
     * @param array $descriptor
     * @return void
     */
    private function showForm( array $descriptor ): void {
        HTMLForm::factory( 'ooui', $descriptor, $this->getContext() )
            ->setTokenSalt( 'wikisearchdatastandard' )
            ->setSubmitTextMsg( 'wikisearch-special-data-standard-submit-text' )
            ->setSubmitCallback( [$this, 'formCallback'] )
            ->setSubmitDestructive()
            ->setCancelTarget( $this->getFullTitle() )
            ->showCancel()
            ->show();
    }

    /**
     * Returns the form descriptor for the edit form.
     *
     * @param string $dataStandard
     * @return array
     */
    private function getFormDescriptor( string $dataStandard ): array {
        return [
            'datastandard' => [
                'type' => 'textarea',
                'rows' => 32,
                'default' => $dataStandard,
                'required' => true,
                'validation-callback' => [$this, 'checkDataStandard'],
                'filter-callback' => [$this, 'formatDataStandard']
            ],
            'update' => [
                'type' => 'check',
                'label-message' => 'wikisearch-special-data-standard-update-text'
            ]
        ];
    }

    /**
     * @return bool
     */
    private function checkDataStandardLocation(): bool {
        // The location must not be the default SMW location
        return $this->dataStandardLocation !== realpath( $GLOBALS['smwgIP'] . 'data/elastic/smw-data-standard.json' )
            // The location must not be the template location
            && $this->dataStandardLocation !== realpath( $GLOBALS['wgExtensionDirectory'] . '/WikiSearch/data_templates/smw-wikisearch-data-standard-template.json' );
    }

    /**
     * Spawn a process to update the ElasticSearch indices.
     *
     * @return int|false The PID of the spawned process, or false on failure
     */
    private function spawnUpdate( string &$log ) {
        $pidFile = __DIR__ . '/pidfile';
        $logFile = __DIR__ . '/logfile';

        $command = "php {$GLOBALS['wgExtensionDirectory']}/SemanticMediaWiki/maintenance/rebuildElasticIndex.php";
        $result = exec( sprintf( "%s > %s 2>&1 & echo $! > %s", $command, $logFile, $pidFile ) );

        if ( $result === false ) {
            return false;
        }

        sleep( 10 );

        $pid = file_get_contents( $pidFile ) ?: 'unknown';
        $log = file_get_contents( $logFile ) ?: '';

        // Format the logfile
        $search = version_compare( PHP_VERSION, '7.3', '<' ) ? "\r" : "\033[0G";
        $log = str_replace( $search, "\n", $log );

        return intval( trim( $pid ) );
    }
}
