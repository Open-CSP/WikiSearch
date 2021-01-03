<?php

class ApiQuerySMWS extends ApiQueryBase {


	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'sm' );
	}

	/** @inheritDoc */
	public function execute() {
		$paramz = $this->extractRequestParams();

		$search_params = [
			 "term"   => $paramz['term'],
			 "from"   => $paramz['from'],
			 "dates" => json_decode($paramz['dates']),
			 "filters" => $paramz['filter'],
			 "page" => $paramz['page']
 		 ];


 	  $output = WSSearch::dosearch($search_params);


		$this->getResult()->addValue( 'result', 'total' , $output['total'] );
		$this->getResult()->addValue( 'result', 'hits' , json_encode($output['hits']) );
		$this->getResult()->addValue( 'result', 'aggs' , json_encode($output['aggs']) );

	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'page' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'filter' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'dates' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'term' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'from' => [
				ApiBase::PARAM_TYPE => 'string',
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&meta=smws'
				=> 'apihelp-query+featureusage-example-simple',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:ApiFeatureUsage';
	}

}
