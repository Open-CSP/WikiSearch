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



     function createPropertyObject($input, $store){

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


//print_r();

     $_class = createPropertyObject($classIDProperty_params[0] , $store);
     $_title = createPropertyObject($param_title , $store);
     $_exerpt = createPropertyObject($param_exerpt , $store);









     function add_months($months, DateTime $dateObject)
    {
        $next = new DateTime($dateObject->format('Y-m-d'));
        $next->modify('last day of +'.$months.' month');

        if($dateObject->format('d') > $next->format('d')) {
            return $dateObject->diff($next);
        } else {
            return new DateInterval('P'.$months.'M');
        }
    }

function endCycle($d1, $months)
    {
        $date = new DateTime($d1);

        // call second function to add the months
        $newDate = $date->add(add_months($months, $date));

        // goes back 1 day from date, remove if you want same day of month
        $newDate->sub(new DateInterval('P1D'));

        //formats final date to Y-m-d form
        $dateReturned = $newDate->format('Y-m-d');

        return $dateReturned;
    }

    $startDate = '1900-01-01'; // select date in Y-m-d format  2415020.0000000

// 2451544 = 2000- 1- 1
$tim = date("Y") - 2000;

//print_r($tim * 365);

$origin = new DateTime('2000/01/01');
$target = new DateTime(date("Y/m/d"));
$interval = $origin->diff($target)->format('%R%a');
//  Print_r($interval->format('%R%a') - 7);


$ranzes = [];

array_push($ranzes, [
  "key" => "Last Week",
  "from" => ($interval -7) + 2451544,
   "to" => $interval + 2451544
 ]);

 array_push($ranzes, [
   "key" => "Last month",
   "from" => ($interval - 31) + 2451544,
    "to" => $interval + 2451544
  ]);

  array_push($ranzes, [
    "key" => "Last Quarter",
    "from" => ($interval - 92) + 2451544,
     "to" => $interval + 2451544
   ]);

for ($i=0; $i < $tim; $i++) {
  $days = $tim - ($i - 1);
  $to = $days * 365;
  $from = $to - 365;
  $key = date("Y") - $i;
  array_push($ranzes, [
    "key" => strval($key),
    "from" => $from + 2451544,
     "to" => $to + 2451544
   ]);
};





// $rrrr = [
//    [
//     "key" => "2019",
//     "from" => 2458484,
//     "to"  => 2458848
//   ],
//   [
//     "key" => "2020",
//     "from" => 2458849,
//     "to" => 2459214
//   ]
//
// ];
$nMonths = 1; // choose how many months you want to move ahead
$final = endCycle($startDate, $nMonths); // output: 2014-07-02


     $filters = [];


//print_r(2415020 + $interval->format('%R%a'));
     $filters['Date'] =  [
     "date_range" => [
         "field" => "P:29.datField",
         "ranges" => $ranzes
     ]
     ];

//  $filters['Date']['date_range']["ranges"] = $ranzes;
$filtersIDs = [];

foreach (explode(",", $param_filters) as $key => $value) {
    $_filter = createPropertyObject($value , $store);

array_unshift($filtersIDs, $_filter['id'] );

  $filters[$value] = [
    'terms' => [
      'field' => 'P:' . $_filter['id'] . '.' . $_filter['type'] . '.keyword'
    ]
  ];
}

//print_r($ranzes);

// $filters['sort_bucket'] = [
//           "bucket_sort" => [
//             "sort" => [
//               ["date_range" => [ "order" => "desc" ] ]
//             ],
//             "size" => 3
//           ]
//         ];



     $hosts = [
         'localhost:9200',         // IP + Port
     ];
     $client = ClientBuilder::create()           // Instantiate a new ClientBuilder
                         ->setHosts($hosts)      // Set the hosts
                         ->build();              // Build the client object

     $params = [
         'index' => 'smw-data-' . strtolower( wfWikiID() ),
         "from" => 0,
         "size" => 10,
         'body' => [
             'query' => [
                 'bool' => [
                     'must' => [
                         [ 'match' => [ 'P:' . $_class['id'] . '.txtField' => $classIDProperty_params[1] ] ],
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
        $output = '<script src="https://cdn.jsdelivr.net/npm/vue"></script>';



      //  $content = return_output('some/file.php');


 $output .= str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/templates/app.html'));



    $output .= '<style>' . str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/smws.css')) . '</style>';


    $output .= '<script>' . str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/templates/filter.js')) . '</script>';

    $output .= '<script>' . str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/templates/hit.js')) . '</script>';



    $output .= '<script>var app = new Vue({ el: "#app", data:{';
    $output .= 'total: "' . $total . '",';
    $output .= 'hits: ' . $hits . ',';
    $output .= 'aggs: ' . $aggs . ',';
    $output .= 'size: ' .  '10' . ',';
    $output .= 'from: ' .  '0' . ',';
    $output .= 'selected: [] ,';
      $output .= 'open: [] ,';
    $output .= 'term: "" ,';
    $output .= 'loading: false ,';
    $output .= 'dates:' . json_encode($ranzes) . ',';
    $output .= 'main: "' . $param1 . '",';
    $output .= 'filterIDs:' . json_encode($filtersIDs) . ',';
    $output .= 'exerptID: ' . $_exerpt['id'] . ',';
    $output .= 'exerpt: "' . $param_exerpt . '",';
    $output .= 'titleID: ' . $_title['id'] ;
    $output .= '}, ' . str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . "/templates/main.js") ) . ' });</script>';



      return [ $output, 'noparse' => true, 'isHTML' => true ];
   }







}
