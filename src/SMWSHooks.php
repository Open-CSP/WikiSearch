<?php

class SMWSHooks {

 public static $smwsloaded = "false";


  public static function onParserFirstCallInit( Parser $parser ) {

    $parser->setFunctionHook( 'smws', [ self::class, 'renderSMWS' ] );
  }

  public static function renderSMWS( Parser $parser, $param1 = '', $param_filters = '', $param_title = '', $param_exerpt = '') {

self::$smwsloaded = "true";

//create date range


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

    $classIDProperty_params = explode("=", $param1);


    $search_params = [
      class1 => $classIDProperty_params[0],
      class2 => $classIDProperty_params[1],
      facets => $param_filters,
      title  => $param_title,
      exerpt => $param_exerpt,
      dates => $ranzes,
      config => true
    ];


    $dno = WSSearch::dosearch($search_params);


    $total = $dno['total'];
    $hits =  $dno['hits'];
    $aggs = $dno['aggs'];




  //  $output = '<script src="https://cdn.jsdelivr.net/npm/vue"></script>';



    //  $content = return_output('some/file.php');
      $output .= "<script>var vueinitdata = { total: " . $total ;
      $output .= ", hits: ". $hits ;
      $output .= ", aggs: " . $aggs ;
      $output .= ", orgaggs: '" . $param_filters ;
      $output .= "', dates:" . json_encode($ranzes) ;
      $output .= ",  main: '" . $param1 ;
      $output .= "',  filterIDs:" . $dno['filterids'] ;
      $output .= ",  exerptID: '" . $dno['exerptid'] ;
      $output .= "',    exerpt: '" . $param_exerpt ;
      $output .= "',   titleID: '" . $dno['titleid'] . "'}</script>";



    $output .= str_replace( array("\r", "\n"),"", file_get_contents(__DIR__ . '/templates/app.html'));

      return [ $output, 'noparse' => true, 'isHTML' => true ];
    }




  public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
    if(self::$smwsloaded == "true"){
   $out->addModules( 'ext.app' );
 }
}

}
