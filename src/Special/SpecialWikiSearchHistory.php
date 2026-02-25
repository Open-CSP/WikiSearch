<?php

namespace WikiSearch\Special;

use HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutput;
use OOUI\ButtonGroupWidget;
use OOUI\ButtonWidget;
use PermissionsError;
use SpecialPage;
use Status;
use WikiSearch\SearchHistoryStore;
use WikiSearch\WikiSearchServices;

/**
 * Implements Special:WikiSearchHistory.
 */
class SpecialWikiSearchHistory extends SpecialPage {
    private SearchHistoryStore $searchHistoryStore;

    public function __construct() {
		parent::__construct( 'WikiSearchHistory', 'wikisearch-view-history' );

        $this->searchHistoryStore = WikiSearchServices::getSearchHistoryStore();
	}

	/**
	 * @inheritDoc
	 * @throws PermissionsError
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();

        $this->getOutput()->clearHTML();
        $this->getOutput()->enableOOUI();

        $this->getOutput()->addParserOutput( $this->render( $subPage ) );
	}

    private function render( ?string $subPage ): ParserOutput {
        [ $limit, $offset ] = $this->getOutput()->getRequest()->getLimitOffsetForUser( $this->getOutput()->getUser() );

        $table = $this->getTable( $offset, $limit );
        $navigationBar = $this->getNavigationBar( $offset, $limit );

        $pout = new ParserOutput;
        $pout->addModuleStyles( [ 'oojs-ui.styles.icons-movement' ] );
        $pout->setText( wfMessage( 'wikisearch-view-history-intro' )->parse() . $table . $navigationBar );

        return $pout;
    }

    /**
     * Returns a table containing the applied policies.
     *
     * @param int $offset
     * @param int $limit
     * @return string
     */
    private function getTable( int $offset, int $limit ): string {
        $colHeaders = [ $this->msg( 'wikisearch-history-aggregation-query-column' ), $this->msg( 'wikisearch-history-aggregation-occurrences-column' ) ];
        $rows = [];

        foreach ( $this->searchHistoryStore->getAggregationsByConds( [], $offset, $limit ) as $aggregation ) {
            $formattedQuery = $this->formatQuery( $aggregation->search_query );

            $rows[] = [ $formattedQuery, $aggregation->search_occurrences ];
        }

        return $this->formatAsTable( $rows, $colHeaders );
    }

    /**
     * Returns a navigation bar to switch between table pages.
     *
     * @param int $offset
     * @param int $limit
     * @return ButtonGroupWidget
     */
    private function getNavigationBar( int $offset, int $limit ): ButtonGroupWidget {
        $types = [];

        if ( $offset > 0 ) {
            $types['prev'] = max( $offset - $limit, 0 );
        }

        if ( $offset + $limit < $this->searchHistoryStore->getAggregationCountByConds( [] ) ) {
            $types['next'] = $offset + $limit;
        }

        $buttons = [];
        $title = $this->getOutput()->getTitle();

        foreach ( $types as $type => $newOffset ) {
            $buttons[] = new ButtonWidget( [
                'flags' => [ 'progressive' ],
                'framed' => true,
                'label' => $this->msg( 'table_pager_' . $type )->text(),
                'href' => $title->getLinkURL( [ 'offset' => $newOffset, 'limit' => $limit ] ),
                'icon' => $type === 'prev' ? 'previous' : $type
            ] );
        }

        return new ButtonGroupWidget( [
            'items' => $buttons,
        ] );
    }

    /**
     * Formats the given array as an HTML table.
     *
     * @param string[][] $array The array to format as an HTML table.
     * @param string[] $colHeaders
     * @param string[] $rowHeaders
     * @return string
     */
    private function formatAsTable( array $array, array $colHeaders = [], array $rowHeaders = [] ): string {
        $array = array_map( static fn ( $value ) => is_array( $value ) ? $value : [ $value ], $array );

        $wikitext = '<table class="wikitable" style="width: 100%">';

        if ( count( $colHeaders ) > 0 ) {
            $wikitext .= '<tr>';

            if ( count( $rowHeaders ) > 0 ) {
                $wikitext .= '<th></th>';
            }

            $wikitext .= implode( '', array_map( static fn ( $v ) => "<th>$v</th>", $colHeaders ) );
            $wikitext .= '</tr>';
        }

        foreach ( array_values( $array ) as $i => $row ) {
            $wikitext .= '<tr>';

            if ( isset( $rowHeaders[$i] ) ) {
                $wikitext .= '<th scope="col">';
                $wikitext .= $rowHeaders[$i];
                $wikitext .= '</th>';
            }

            $cols = array_map( fn ( $v ) => '<td>' . $v . '</td>', $row );

            $wikitext .= implode( '', $cols );
            $wikitext .= '</tr>';
        }

        $wikitext .= '</table>';

        return $wikitext;
    }

    private function formatQuery( string $query ) {
        if ( $query === "" ) {
            return "<i>" . $this->msg( 'wikisearch-empty-string' )->text() . "</i>";
        }

        if ( strlen( $query ) > 128 ) {
            return substr( $query, 0, 125 ) . '...';
        }

        return htmlspecialchars( $query, ENT_QUOTES, 'UTF-8' );
    }
}
