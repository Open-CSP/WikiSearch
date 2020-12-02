<?php


use Elasticsearch\ClientBuilder;

use SMW\DIProperty;
use SMW\ApplicationFactory;


class SMWSHooks {
   public static function onParserFirstCallInit( Parser $parser ) {

      $parser->setFunctionHook( 'smws', [ self::class, 'renderSMWS' ] );
   }

   public static function renderSMWS( Parser $parser, $param1 = '', $param_filters = '', $param_title = '', $param_exerpt = '' ) {

     $store = ApplicationFactory::getInstance()->getStore();

     $classIDProperty_params = explode("=", $param1);


     $classIDProperty = new DIProperty( $classIDProperty_params[0] );
     $classID = $store->getObjectIds()->getSMWPropertyID($classIDProperty);
     $classType = $classIDProperty->findPropertyValueType();



     $titleIDProperty = new DIProperty( $param_title );
     $titleID = $store->getObjectIds()->getSMWPropertyID($titleIDProperty);
     $titleType = $titleIDProperty->findPropertyValueType();


     $exerptIDProperty = new DIProperty( $param_exerpt );
     $exerptID = $store->getObjectIds()->getSMWPropertyID($exerptIDProperty);
     $exerptType = $exerptIDProperty->findPropertyValueType();



     $filters = [];
     $filtersIDs = [];

     foreach (explode(",", $param_filters) as $key => $value) {

     $filterIDProperty = new DIProperty( $value );
     $filterID = $store->getObjectIds()->getSMWPropertyID($filterIDProperty);
     $filterType = $filterIDProperty->findPropertyValueType();

     if($filterType == "_txt"){
       $ftype = "txtField";
     }else{
       $ftype = "wpgField";
     }




     array_unshift($filtersIDs, $filterID );

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

     $params = [
         'index' => 'smw-data-' . strtolower( wfWikiID() ),
         "from" => 0,
         "size" => 2,
         'body' => [
             'query' => [
                 'bool' => [
                     'must' => [
                         [ 'match' => [ 'P:' . $classID . '.txtField' => $classIDProperty_params[1] ] ],
                     ]
                 ]
             ],
             'aggs' => $filters
         ]
     ];



     $results = $client->search($params);




        $scrollID = json_encode($results['_scroll_id']);
       $total = json_encode($results['hits']['total']);
       $hits =  json_encode($results['hits']['hits']);
        $aggs = json_encode($results['aggregations']);
        $output = '<script src="https://cdn.jsdelivr.net/npm/vue"></script>';




    $output .= str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/templates/app.app'));


    $output .= '<script>' . str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/templates/filter.js')) . '</script>';

    $output .= '<script>' . str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/templates/hit.js')) . '</script>';



    $output .= '<script>var app = new Vue({ el: "#app", data:{';
    $output .= 'total: "' . $total . '",';
    $output .= 'hits: ' . $hits . ',';
    $output .= 'aggs: ' . $aggs . ',';
    $output .= 'size: ' .  '2' . ',';
    $output .= 'from: ' .  '0' . ',';
    $output .= 'selected: [] ,';
    $output .= 'term: "" ,';
    $output .= 'main: "' . $param1 . '",';
    $output .= 'filterIDs:' . json_encode($filtersIDs) . ',';
    $output .= 'exerptID: ' . $exerptID . ',';
    $output .= 'titleID: ' . $titleID ;
    $output .= '}, ' . str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . "/templates/main.js") ) . ' });</script>';



      return [ $output, 'noparse' => true, 'isHTML' => true ];
   }







}
