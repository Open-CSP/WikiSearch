<?php

class SMWSHooks {

  public static $smwsloaded = "false";

  public static function onParserFirstCallInit( Parser $parser ) {

    $parser->setFunctionHook( 'smws', [ self::class, 'renderSMWS' ], Parser::SFH_OBJECT_ARGS );
  }
//$parser, $param1 = '', $param_filters = '', $param_title = '', $param_exerpt = ''
  public static function renderSMWS( Parser $parser, PPFrame $frame, array $args) { //array $args

    //set true for onBeforePageDisplay hook
    self::$smwsloaded = "true";


if(isset($args[0])) {
  $param1 =   trim( $frame->expand($args[0]));
  unset($args[0]);
  $param_results = [];
  $param_filters = [];
  foreach ($args as $key => $value) {
    $p_val = trim( $frame->expand($value));
    if($p_val[0] == "?"){
     array_push($param_results,  substr($p_val, 1));
    }else{
     array_push($param_filters,  $p_val);
    }
  }
}



    //create date range

    // 2451544 = 2000- 1- 1
    $timestamp = 2451544;
    $date_start = date("Y") - 2000;
    $origin = new DateTime('2000/01/01');
    $target = new DateTime(date("Y/m/d"));
    $interval = $origin->diff($target)->format('%R%a');

    $date_ranges = [];

    array_push($date_ranges, [
      "key" => "Last Week",
      "from" => ($interval -7) + $timestamp,
      "to" => $interval + $timestamp
    ]);

    array_push($date_ranges, [
      "key" => "Last month",
      "from" => ($interval - 31) + $timestamp,
      "to" => $interval + $timestamp
    ]);

    array_push($date_ranges, [
      "key" => "Last Quarter",
      "from" => ($interval - 92) + $timestamp,
      "to" => $interval + $timestamp
    ]);

    for ($i=0; $i < $date_start; $i++) {
      $days = $date_start - ($i - 1);
      $to = $days * 365;
      $from = $to - 365;
      $key = date("Y") - $i;
      array_push($date_ranges, [
        "key" => strval($key),
        "from" => $from + $timestamp,
        "to" => $to + $timestamp
      ]);
    };

    $classIDProperty_params = explode("=", $param1);


    $search_params = [
      "class1" => $classIDProperty_params[0],
      "class2" => $classIDProperty_params[1],
      "facets" => $param_filters,
      "outputs" =>  $param_results,
      "dates" => $date_ranges,
    ];

    //check url parameters
    if(isset($_GET["term"])){
      $search_params["term"] = $_GET["term"];
    }

    if(isset($_GET["filters"])){
      $urlfilters = [];
      $urlfiltersout = [];
      $activefilters =  explode("~", $_GET["filters"]);
      foreach ($activefilters as $key => $value) {
          $filteritem = explode("-", $value);
           $rangeitem = explode("_", $filteritem[0]);
          if($rangeitem[0] == "range"){ //hier bezig
           $ranges = explode("_", $filteritem[1]);
            array_push($urlfilters,   ["range" => ["P:29.datField" => ["gte" => $ranges[0], "lte" => $ranges[1] ] ] ]);
            array_push($urlfiltersout,   ["key" => $rangeitem[2], "value" => $rangeitem[1], "range" => ["P:29.datField" => ["gte" => $ranges[0], "lte" => $ranges[1] ] ] ]);
          }else{
            array_push($urlfilters,   ["value" => $filteritem[1], "key" => $filteritem[0] ]);
            array_push($urlfiltersout,  ["value" => $filteritem[1], "key" => $filteritem[0] ]);
        }
      }
     $search_params["filters"] = $urlfilters;
    }

    $output_params = WSSearch::dosearch($search_params);
    $output_params['dates'] = $date_ranges;

    if(isset($_GET["term"])){
      $output_params['term'] = $_GET["term"];
    }

    if(isset($_GET["filters"])){
        $output_params['selected'] = $urlfiltersout ;
    }else{
        $output_params['selected'] = [];
    }


  //  print_r($output_params['aggs']);

    $output = "<script>var vueinitdata = " . json_encode($output_params) . "</script>";
    $output .= str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/templates/app.html'));

    return [ $output, 'noparse' => true, 'isHTML' => true ];
  }

  public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
    if(self::$smwsloaded == "true"){
      $out->addModules( 'ext.app' );
    }
  }

}
