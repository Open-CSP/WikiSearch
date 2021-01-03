<?php
ini_set( 'display_errors', 1 );

require '/var/www/html/wiki1.31/extensions/SMWS/vendor/autoload.php';

use Elasticsearch\ClientBuilder;

use SMW\DIProperty;
use SMW\ApplicationFactory;



    $param1 = 'Class=News item';
    $param_filters = 'Title,Group';
     $param_title = 'Title';
     $param_exerpt = 'Snippet';

     $store = ApplicationFactory::getInstance()->getStore();

     $classIDProperty_params = explode("=", $param1);


     $classIDProperty = new DIProperty( $classIDProperty_params[0] );
     $classID = $store->getObjectIds()->getSMWPropertyID($classIDProperty);


     $titleIDProperty = new DIProperty( $param_title );
     $titleID = $store->getObjectIds()->getSMWPropertyID($titleIDProperty);

     $exerptIDProperty = new DIProperty( $param_exerpt );
     $exerptID = $store->getObjectIds()->getSMWPropertyID($exerptIDProperty);


     $filters = [];

     foreach (explode(",", $param_filters) as $key => $value) {

     $filterIDProperty = new DIProperty( $value );
     $filterID = $store->getObjectIds()->getSMWPropertyID($filterIDProperty);

       $filters[$value] = [
         'terms' => [
           'field' => 'P:' . $filterID . '.txtField.keyword'
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





       $total = json_encode($results['hits']['total']);
       $hits =  json_encode($results['hits']['hits']);
        $aggs = json_encode($results['aggregations']);


if( isset( $_POST['filter'] )){



 echo json_encode($_POST['filter']);

}
?>
