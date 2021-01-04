<?php

use Elasticsearch\ClientBuilder;
use SMW\DIProperty;
use SMW\ApplicationFactory;

class WSSearch{

  //function to translate SMW properties to interal ids, and get the property type

  private static function buildPropertyObject($input, $store){
    $IDProperty = new DIProperty( $input );
    $ID = $store->getObjectIds()->getSMWPropertyID($IDProperty);
    $Type = $IDProperty->findPropertyValueType();

    if($Type == "_txt"){
      $ftype = "txtField";
    }else{
      $ftype = "wpgField";
    }
    return ["id" => $ID, "type" => $ftype, "name" => $input];
  }


  public static function dosearch($search_params) {

    $store = ApplicationFactory::getInstance()->getStore();

    // if from api  get original parser function parameters from the page
    if(isset($search_params['page'])){

      $pagetitle = Title::newfromText($search_params['page']);
      $revision = Revision::newFromTitle( $pagetitle );
      if ( $revision == null ) {
        return '';
      }
      $content = $revision->getContent( Revision::RAW );
      $text = $content->getNativeData();

      //FUTURE add regex for matching parser function {{#WSSearch:}}

      $search_params['facets'] = explode("|", $text)[1];
      $search_params['title'] = explode("|", $text)[2];
      $search_params['exerpt'] = explode("|", $text)[3];

      $exploood =  explode("=", explode(":", explode("|", $text)[0])[1]);
      $search_params['class1'] = $exploood[0];
      $search_params['class2'] = $exploood[1];
    }


    $_class = self::buildPropertyObject($search_params['class1'] , $store);
    $_title = self::buildPropertyObject($search_params['title'] , $store);
    $_exerpt = self::buildPropertyObject($search_params['exerpt'] , $store);

    $filters = [];
    $filtersIDs = [];
    $translations = [];

    //create aggs query
    foreach (explode(",", $search_params['facets']) as $key => $value) {
      $vars = explode("=", $value);
      $val = $vars[0];
      if(isset($vars[1])){
        $translations[$val] = $vars[1];
      }
      $_filter = self::buildPropertyObject($val , $store);

      array_unshift($filtersIDs, $_filter['id'] );

      $filters[$val] = [
        'terms' => [
          'field' => 'P:' . $_filter['id'] . '.' . $_filter['type'] . '.keyword'
        ]
      ];
    }

    if(isset($search_params['from'])){
      $from = $search_params['from'];
    }else{
      $from = 0;
    }

    //create date aggs query
    if(isset($search_params['dates'])){
      $filters['Date'] =  [
        "date_range" => [
          "field" => "P:29.datField",
          "ranges" => $search_params['dates']
        ]
      ];
    }

    //create elastic query
    $params = [
      'index' => 'smw-data-' . strtolower( wfWikiID() ),
      "from" => $from,
      "size" => 10,
      'body' => [
        'query' => [
          "constant_score"=> [
            "filter"=> [
              'bool' => [
                'must' => [[
                  'bool' => [
                    'filter' => [
                      [ 'term' => [ 'P:' . $_class['id'] . '.txtField.keyword' => $search_params['class2'] ] ],
                    ]
                  ]
                ]
                ]]
              ]

            ]
          ],
          "highlight" => [
            "pre_tags" => ["<b>"],
            "post_tags" => ["</b>"],
            "fields" => [
              'text_raw' => ["fragment_size" => 150, "number_of_fragments" => 1]

            ]
          ],
          'aggs' => $filters
        ]
      ];


      //create active filters query
      if(isset($search_params['filters'])){
        $infilters = $search_params['filters'];
        foreach ($infilters as $key => $value) {
          if(!isset($value['range'])){
            $activefilter = self::buildPropertyObject($value['key'] , $store);
            $termfield = [
              "term" => [
                "P:" . $activefilter['id'] . "." . $activefilter['type'] . ".keyword" => $value['value']
              ]
            ];
            array_push($params['body']['query']["constant_score"]['filter']['bool']['must'][0]['bool']['filter'], $termfield );
          }else{

             unset($value["value"] );
              unset($value["key"] );
            array_push($params['body']['query']["constant_score"]['filter']['bool']['must'][0]['bool']['filter'], $value);
          }
        }
      }

      //create search term query if search term is not empty
      if(isset($search_params['term']) && $search_params['term']){

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
                "query" => "*" . $search_params['term'] . "*",
                "minimum_should_match" => 1
              ]
            ]
          ]
        ];

        array_push($params['body']['query']["constant_score"]['filter']['bool']['must'], $sterm );

      }


      // do the Elasticsearch
      $hosts = [
        'localhost:9200',
      ];
      $client = ClientBuilder::create()->setHosts($hosts)->build();
      $results = $client->search($params);

      //join facet translations

      foreach ($results['aggregations'] as $key => $value) {
        if(isset($translations[$key])){
          $vars = explode(":", $translations[$key]);
          //  translate namsepace id
          if($vars[0] = "namespace"){
            foreach ($results['aggregations'][$key]['buckets'] as $key3 => $value3) {
              $namespace = MWNamespace::getCanonicalName($value3['key']);
              $results['aggregations'][$key]['buckets'][$key3]['name'] = $namespace;
            }
          }
          //add future translations here
        }
      }


      //output


      $output = [ "total" => $results['hits']['total'],
      "hits" => $results['hits']['hits'],
      "aggs" => $results['aggregations']
    ];

    // extra data for vue init
    if(!isset($search_params['page'])){
      $output['filterIDs'] = $filtersIDs;
      $output['titleID'] =  $_title['id'];
      $output['exerptID'] =  $_exerpt['id'];
    };
    return $output;
  }
}
