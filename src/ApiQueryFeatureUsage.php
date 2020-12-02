<?php
class ApiQueryFeatureUsage extends ApiQueryBase {

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'afu' );
	}

	/** @inheritDoc */
	public function execute() {
		$params = $this->extractRequestParams();



		$this->getResult()->addValue( 'query', $params['start'], 'hoi2' );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'start' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'end' => [
				ApiBase::PARAM_TYPE => 'timestamp',
			],
			'agent' => [
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
			'action=query&meta=featureusage'
				=> 'apihelp-query+featureusage-example-simple',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:ApiFeatureUsage';
	}

}
