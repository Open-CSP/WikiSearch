<?php

namespace WikiSearch\Special;

use HTMLForm;
use PermissionsError;
use SpecialPage;

/**
 * Implements Special:WikiSearchDataStandard.
 */
class SpecialWikiSearchDataStandard extends SpecialPage {
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
            return 'Missing data standard location.';
        }

        $dataStandardLocation = $GLOBALS['smwgElasticsearchConfig']['index_def']['data'];

        if ( !$this->checkDataStandardLocation( $dataStandardLocation ) ) {
            return 'Invalid location.';
        }

        $dataStandard = file_get_contents( $dataStandardLocation );

        if ( $dataStandard === false ) {
            return 'Could not read.';
        }

        $this->showForm( $this->getFormDescriptor( $dataStandard ) );

        return $dataStandard;
    }

    /**
     * Shows the edit form.
     *
     * @param array $descriptor
     * @return void
     */
    private function showForm( array $descriptor ): void {
        $form = HTMLForm::factory( 'ooui', $descriptor, $this->getContext() );

        $form->setSubmitCallback( [$this, 'formCallback'] );
        $form->setSubmitTextMsg( 'wikisearch-special-data-standard-submit-text' );
        $form->setSubmitDestructive();

        $form->showCancel();
        $form->setCancelTarget( $this->getFullTitle() );

        $form->setTokenSalt( 'wikisearchdatastandard' );

        $form->show();
    }

    /**
     * Returns the form descriptor for the edit form.
     *
     * @param string $dataStandard
     * @return void
     */
    private function getFormDescriptor( string $dataStandard ): array {
        return [
            'data' => [
                'type' => 'textarea',
                'rows' => 32,
                'default' => $dataStandard,
                'required' => true,
                'validation-callback' => [$this, 'checkDataStandard'],
                'filter-callback' => [$this, 'formatDataStandard']
            ]
        ];
    }

    /**
     * Validates the given data standard. This only validates the formatting, and not the actual contents of the data
     * standard.
     *
     * @param string $dataStandard
     * @return bool|string
     */
    private function checkDataStandard( string $dataStandard ) {
        if ( is_array( json_decode( $dataStandard ) ) ) {
            return true;
        }

        return 'Invalid JSON: ' . json_last_error_msg();
    }

    /**
     * @param string $dataStandard
     * @return string
     */
    private function formatDataStandard( string $dataStandard ): string {
        if ( !$this->checkDataStandard( $dataStandard ) ) {
            // Nothing to format if the standard is invalid
            return $dataStandard;
        }

        return json_encode( json_decode( $dataStandard ), JSON_PRETTY_PRINT );
    }

    /**
     * @param string $location
     * @return bool
     */
    private function checkDataStandardLocation( string $location ): bool {
        return $location !== $GLOBALS['smwgIP'] . '/data/elastic/smw-data-standard.json' // The location must not be the default SMW location
            && $location !== $GLOBALS['wgExtensionDirectory'] . '/WikiSearch/data_templates/smw-wikisearch-data-standard-template.json'; // The location must not be the template location
    }
}