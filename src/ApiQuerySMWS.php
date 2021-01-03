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

  $classIDProperty_params = explode("=", $paramz['class']);


		$search_params = [
 			 class1 => $classIDProperty_params[0],
 			 class2 => $classIDProperty_params[1],
 			 facets => $paramz['aggs'],
 			 title  => $paramz['title'],
 			 exerpt => $paramz['exerpt'],
			 term   => $paramz['term'],
			 from   => $paramz['from'],
			 dates => json_decode($paramz['dates']),
			 filters => $paramz['filter']

 		 ];


 	  $output = WSSearch::dosearch($search_params);


		$this->getResult()->addValue( 'result', 'total' , $output['total'] );
		$this->getResult()->addValue( 'result', 'hits' , $output['hits'] );
		$this->getResult()->addValue( 'result', 'aggs' , $output['aggs'] );

	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'class' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'filter' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'range' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'dates' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'exerpt' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'aggs' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'term' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'from' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'features' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
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
