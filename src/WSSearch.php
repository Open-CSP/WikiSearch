<?php

use Elasticsearch\ClientBuilder;
use SMW\DIProperty;
use SMW\ApplicationFactory;

class WSSearch{

  public static function dosearch($search_params) {

    $store = ApplicationFactory::getInstance()->getStore();

    //function to translate SMW properties to interal ids, and get the property type
    function buildPropertyObject($input, $store){
      $IDProperty = new DIProperty( $input );
      $ID = $store->getObjectIds()->getSMWPropertyID($IDProperty);
      $Type = $IDProperty->findPropertyValueType();

      if($Type == "_txt"){
        $ftype = "txtField";
      }else{
        $ftype = "wpgField";
      }
      return [id => $ID, type => $ftype, name => $input];
    }


    // if from api call get original parser function parameters from the page
    if($search_params['page']){

      $pagetitle = Title::newfromText($search_params['page']);
      $revision = Revision::newFromTitle( $pagetitle );
      if ( $revision == null ) {
        return '';
      }
      $content = $revision->getContent( Revision::RAW );
      $text = $content->getNativeData();

      //add regex for matching parser function {{#WSSearch:}}

      $search_params['facets'] = explode("|", $text)[1];
      $search_params['title'] = explode("|", $text)[2];
      $search_params['exerpt'] = explode("|", $text)[3];

      $exploood =  explode("=", explode(":", explode("|", $text)[0])[1]);
      $search_params['class1'] = $exploood[0];
      $search_params['class2'] = $exploood[1];
    }


    $_class = buildPropertyObject($search_params['class1'] , $store);
    $_title = buildPropertyObject($search_params['title'] , $store);
    $_exerpt = buildPropertyObject($search_params['exerpt'] , $store);

    $filters = [];
    $filtersIDs = [];
    $translations = [];

    //create aggs query
    foreach (explode(",", $search_params['facets']) as $key => $value) {
      $vars = explode("=", $value);
      $val = $vars[0];
      if($vars[1]){
        $translations[$val] = $vars[1];
      }
      $_filter = buildPropertyObject($val , $store);

      array_unshift($filtersIDs, $_filter['id'] );

      $filters[$val] = [
        'terms' => [
          'field' => 'P:' . $_filter['id'] . '.' . $_filter['type'] . '.keyword'
        ]
      ];
    }

    if($search_params['from']){
      $from = $search_params['from'];
    }else{
      $from = 0;
    }

    //create date aggs query
    if($search_params['dates']){
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
      if($search_params['filters']){
        $infilters = json_decode($search_params['filters'], true);
        foreach ($infilters as $key => $value) {
          if($value['key']){
            $activefilter = buildPropertyObject($value['key'] , $store);
            $termfield = [
              "term" => [
                "P:" . $activefilter['id'] . "." . $activefilter['type'] . ".keyword" => $value['value']
              ]
            ];
            array_push($params['body']['query']["constant_score"]['filter']['bool']['must'][0]['bool']['filter'], $termfield );
          }else{
            array_push($params['body']['query']["constant_score"]['filter']['bool']['must'][0]['bool']['filter'], $value);
          }
        }
      }

      //create search term query
      if($search_params['term']){

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



      // ugly code for property translation

      if($_SERVER['SERVER_NAME'] == 'localhost'){
        $endPoint = 'http://localhost/wiki1.31/api.php';
      }else{
        $endPoint = 'https://' . $_SERVER['SERVER_NAME'] . '/api.php';
      }


      foreach ($translations as $key => $value) {
        $vars = explode(":", $value);

        $params4 = [
          "action" => "ask",
          "query" => "[[Class::" . $vars[0] . "]]|?" . $key . "|?" . $vars[1] ."|sort=" . $vars[1] . "|order=asc|link=none",
          "format" => "json"
        ];

        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, $endPoint );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params4 ) );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_COOKIEJAR, "cookie.txt" );
        curl_setopt( $ch, CURLOPT_COOKIEFILE, "cookie.txt" );

        $outputs = curl_exec( $ch );
        curl_close( $ch );
        $list = [];
        $d = $vars[1];

        foreach (json_decode($outputs)->query->results as $key2 => $value2) {
          if($value2->printouts->$d[0]->fulltext){
            if($value2->printouts->$key[0]->fulltext){
              $list[$value2->printouts->$key[0]->fulltext] = $value2->printouts->$d[0]->fulltext;
            }else{
              $list[$value2->printouts->$key[0]] = $value2->printouts->$d[0]->fulltext;
            }
          }else{
            if($value2->printouts->$key[0]->fulltext){
              $list[$value2->printouts->$key[0]->fulltext] = $value2->printouts->$d[0];
            }else{
              $list[$value2->printouts->$key[0]] = $value2->printouts->$d[0];
            }
          }
        }
        //add translations to the aggrigation buckets
        foreach ($results['aggregations'][$key]['buckets'] as $key3 => $value3) {
          $results['aggregations'][$key]['buckets'][$key3]['name'] = $list[$value3['key'] ];
        }
      }
      //end ugly code


    $output = [ total => $results['hits']['total'],
      hits => $results['hits']['hits'],
      aggs => $results['aggregations']
    ];

    // extra data for vue init
    if(!$search_params['page']){
      $output['filterIDs'] = $filtersIDs;
      $output['titleID'] =  $_title['id'];
      $output['exerptID'] =  $_exerpt['id'];
    };
    return $output;
  }
}
