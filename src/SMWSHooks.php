<?php

class SMWSHooks {

  public static $smwsloaded = "false";

  public static function onParserFirstCallInit( Parser $parser ) {

    $parser->setFunctionHook( 'smws', [ self::class, 'renderSMWS' ] );
  }

  public static function renderSMWS( Parser $parser, $param1 = '', $param_filters = '', $param_title = '', $param_exerpt = '') {

    //set true for onBeforePageDisplay hook
    self::$smwsloaded = "true";

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
      class1 => $classIDProperty_params[0],
      class2 => $classIDProperty_params[1],
      facets => $param_filters,
      title  => $param_title,
      exerpt => $param_exerpt,
      dates => $date_ranges,
    ];


    $output_params = WSSearch::dosearch($search_params);
    $output_params['exerpt'] = $param_exerpt;
    $output_params['dates'] = $date_ranges;



    $output .= "<script>var vueinitdata = " . json_encode($output_params) . "</script>";
    $output .= str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/templates/app.html'));

    return [ $output, 'noparse' => true, 'isHTML' => true ];
  }

  public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
    if(self::$smwsloaded == "true"){
      $out->addModules( 'ext.app' );
    }
  }

}
