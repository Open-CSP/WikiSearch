<?php


use Elasticsearch\ClientBuilder;

use SMW\DIProperty;
use SMW\ApplicationFactory;


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






		    $param1 = $paramz['class'];
		    $param_filters = $paramz['aggs'];
		     $param_title = $paramz['title'];
		     $param_exerpt = $paramz['exerpt'];

		     $store = ApplicationFactory::getInstance()->getStore();

		     $classIDProperty_params = explode("=", $param1);


		     $classIDProperty = new DIProperty( $classIDProperty_params[0] );
		     $classID = $store->getObjectIds()->getSMWPropertyID($classIDProperty);


		     $titleIDProperty = new DIProperty( $param_title );
		     $titleID = $store->getObjectIds()->getSMWPropertyID($titleIDProperty);

		     $exerptIDProperty = new DIProperty( $param_exerpt );
		     $exerptID = $store->getObjectIds()->getSMWPropertyID($exerptIDProperty);
				 $exerptType = $exerptIDProperty->findPropertyValueType();
				 if($exerptType == "_txt"){
					 $extype = "txtField";
				 }else{
					 $extype = "wpgField";
				 }


		     $filters = [];




		     foreach (explode(",", $param_filters) as $key => $value) {

		     $filterIDProperty = new DIProperty( trim($value) );
		     $filterID = $store->getObjectIds()->getSMWPropertyID($filterIDProperty);
				 $filterType = $filterIDProperty->findPropertyValueType();
				 if($filterType == "_txt"){
		       $ftype = "txtField";
		     }else{
		       $ftype = "wpgField";
		     }


		       $filters[$value] = [
		         'terms' => [
							 'field' => 'P:' . $filterID . '.' . $ftype . '.keyword'
		         ]
		       ];
		     }

		     $hosts = [
		         'localhost:9200',         // IP + Port
		     ];
		     $client = ClientBuilder::create()           // Instantiate a new ClientBuilder
		                         ->setHosts($hosts)      // Set the hosts
		                         ->build();              // Build the client object


if($paramz['from']){
	$from = $paramz['from'];
}else{
	$from = 0;
}

$filters['Date'] =  [
"date_range" => [
		"field" => "P:29.datField",
		"ranges" => json_decode($paramz['dates'], true)
]
];



		     $params = [
		         'index' => 'smw-data-' . strtolower( wfWikiID() ),
						 "from" => $from,
	           "size" => 10,
		         'body' => [
		             'query' => [
		                 'bool' => [
		                     'must' => [
		                         [ 'match' => [ 'P:' . $classID . '.txtField' => $classIDProperty_params[1] ] ],
		                     ]
									 ]
		             ],
								 "highlight" => [
									 "pre_tags" => ["<b>"],
                   "post_tags" => ["</b>"],
									   "require_field_match" => false,
											"fields" => [
													'P:' . $exerptID . '.' . $extype => ["fragment_size" => 150, "number_of_fragments" => 1]

											]
										],
		             'aggs' => $filters

		         ]
		     ];


if($paramz['filter']){

	$infilters = json_decode($paramz['filter'], true);



$nar = [];
foreach ($infilters as $key => $value) {

if($value['key']){

	$zzIDProperty = new DIProperty( $value['key'] );
	$zzID = $store->getObjectIds()->getSMWPropertyID($zzIDProperty);
	$zzType = $zzIDProperty->findPropertyValueType();

	if($zzType == "_txt"){
		$ztype = "txtField";
	}else{
		$ztype = "wpgField";
	}

   $ara = [
		 "term" => [
			 "P:" . $zzID . "." . $ztype . ".keyword" => $value['value']
		 ]
	 ];
	 array_push($nar, $ara);
}else{

 array_push($nar, $value);

}
}






//{ "range": { "P:29.datField": { "gte": 2459176.0000000, "lte": 2459182.0000000}}}


$params['body']['query']['bool']['filter'] = $nar;

}

if($paramz['term']){

$sterm = [
			 "bool" => [
					 "must" => [
							 "query_string" => [
									 "fields" => [
											 "subject.title^8",
											 "text_copy^5",
											 "text_raw",
											 "attachment.title^3",
											 "attachment.content"
									 ],
									 "query" => "*" . $paramz['term'] . "*",
									 "minimum_should_match" => 1
							 ]
					 ]
			 ]
	 ];

array_push($params['body']['query']['bool']['must'], $sterm );

}




		     $results = $client->search($params);







		       $total = json_encode($results['hits']['total']);
		       $hits =  json_encode($results['hits']['hits']);
		        $aggs = json_encode($results['aggregations']);


//$total = $paramz['range'];

//json_decode($paramz['dates'], true);



		$this->getResult()->addValue( 'result', 'total' , $total );
		$this->getResult()->addValue( 'result', 'hits' , $hits );
		$this->getResult()->addValue( 'result', 'aggs' , $aggs );

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
