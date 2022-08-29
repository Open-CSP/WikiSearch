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
     * @var string The location of the data standard
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

        $this->dataStandardLocation = $GLOBALS['smwgElasticsearchConfig']['index_def']['data'];

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

        return 'Invalid JSON: ' . json_last_error_msg();
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
        // $dataStandard is validated and can be used directly
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
     * @return void
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
        return $this->dataStandardLocation !== $GLOBALS['smwgIP'] . '/data/elastic/smw-data-standard.json' // The location must not be the default SMW location
            && $this->dataStandardLocation !== $GLOBALS['wgExtensionDirectory'] . '/WikiSearch/data_templates/smw-wikisearch-data-standard-template.json'; // The location must not be the template location
    }

    /**
     * Spawn a process to update the ElasticSearch indices.
     *
     * @return string The PID of the spawned process
     */
    private function spawnUpdate( string &$log ): string {
        $pidFile = __DIR__ . '/pidfile';
        $logFile = __DIR__ . '/logfile';

        exec( sprintf( "%s > %s 2>&1 & echo $! > %s", "php {$GLOBALS['wgExtensionDirectory']}/SemanticMediaWiki/maintenance/rebuildElasticIndex.php --with-maintenance-log --auto-recovery --debug", $logFile, $pidFile ) );
        sleep( 10 );

        $pid = file_get_contents( $pidFile );
        $log = file_get_contents( $logFile );

        return trim( $pid );
    }
}