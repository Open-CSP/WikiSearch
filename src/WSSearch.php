<?php

use Elasticsearch\ClientBuilder;
use SMW\DIProperty;
use SMW\ApplicationFactory;

class WSSearch{



  public static function dosearch($search_params) {

    $store = ApplicationFactory::getInstance()->getStore();

    function buildProperty($input, $store){

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


    $_class = buildProperty($search_params['class1'] , $store);
    $_title = buildProperty($search_params['title'] , $store);
    $_exerpt = buildProperty($search_params['exerpt'] , $store);



    $filters = [];

    $filtersIDs = [];


    //get the translations!!!
    $translations = [];
    // print_r(self::$orgfacets);
    // if(self::$orgfacets == "empty" ){
    //     self::$orgfacets = $search_params['facets'];
    // };

    foreach (explode(",", $search_params['facets']) as $key => $value) {
      $vars = explode("=", $value);
      $val = $vars[0];
      if($vars[1]){
        $translations[$val] = $vars[1];
      }
      $_filter = buildProperty($val , $store);

      array_unshift($filtersIDs, $_filter['id'] );

      $filters[$val] = [
        'terms' => [
          'field' => 'P:' . $_filter['id'] . '.' . $_filter['type'] . '.keyword'
        ]
      ];
    }



    $hosts = [
      'localhost:9200',         // IP + Port
    ];
    $client = ClientBuilder::create()           // Instantiate a new ClientBuilder
    ->setHosts($hosts)      // Set the hosts
    ->build();              // Build the client object


    if($search_params['from']){
      $from = $search_params['from'];
    }else{
      $from = 0;
    }


    if($search_params['dates']){
      $filters['Date'] =  [
        "date_range" => [
          "field" => "P:29.datField",
          "ranges" => $search_params['dates']
        ]
      ];
    }


    $params = [
      'index' => 'smw-data-' . strtolower( wfWikiID() ),
      "from" => $from,
      "size" => 10,
      'body' => [
        'query' => [
          'bool' => [
            'must' => [
              [ 'match' => [ 'P:' . $_class['id'] . '.txtField' => $search_params['class2'] ] ],
            ]
          ]
        ],
        "highlight" => [
          "pre_tags" => ["<b>"],
          "post_tags" => ["</b>"],
          "require_field_match" => false,
          "fields" => [
            'P:' . $_exerpt['id'] . '.' . $_exerpt['type'] => ["fragment_size" => 150, "number_of_fragments" => 1]

          ]
        ],
        'aggs' => $filters

      ]
    ];

    if($search_params['filters']){

      $infilters = json_decode($search_params['filters'], true);



      $nar = [];
      foreach ($infilters as $key => $value) {

        if($value['key']){

          $activefilter = buildProperty($value['key'] , $store);

          $ara = [
            "term" => [
              "P:" . $activefilter['id'] . "." . $activefilter['type'] . ".keyword" => $value['value']
            ]
          ];
          array_push($nar, $ara);
        }else{

          array_push($nar, $value);

        }
      }

      $params['body']['query']['bool']['filter'] = $nar;

    }

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

      array_push($params['body']['query']['bool']['must'], $sterm );

    }

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



      foreach ($results['aggregations'][$key]['buckets'] as $key3 => $value3) {



        $results['aggregations'][$key]['buckets'][$key3]['name'] = $list[$value3['key'] ];
      }



    }





    //end ugly code

    $total = json_encode($results['hits']['total']);
    $hits =  json_encode($results['hits']['hits']);
    $aggs = json_encode($results['aggregations']);

    $output = [ total => $total,
    hits => $hits,
    aggs => $aggs
  ];

  if($search_params['config']){

    $output['filterids'] = json_encode($filtersIDs);
    $output['titleid'] =  $_title['id'];
    $output['exerptid'] =  $_exerpt['id'];


  };


  return $output;
}
}
