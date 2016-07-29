<?php

// RouteReferenceClass.php
// Anthony Miles Flynn
// (29/07/16)
// Program downloads an ordered sequence of stops for all bus routes in the TfL
// network, in both directions.  These are saved to the database table provided
// as a parameter in the constructor method

include '/data/individual_project/php/modules/DatabaseClass.php';
include '/data/individual_project/php/modules/HttpClientClass.php';

class RouteReference {
  private $database;
  private $DBH;
  private $route_reference_table;

  // Constructor
  public function __construct($route_table="route_reference") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->route_reference_table = $route_table;
  }

  // Complete update of route_reference table
  public function update_data() {
    // Get a list of all bus lines:
    $api_url = "https://api.tfl.gov.uk/Line/Mode/bus";
    $json_array = $this->download_json($api_url);
    $linename_array = $this->get_all_linenames($json_array);

    // For each bus line (in each direction), extract ordered stop sequence
    $database_insert_array = array(); // stores info to insert into database
    $number_linenames = count($linename_array); // total number of bus lines
    $count = 0;

    $this->DBH->beginTransaction();
    $this->delete_database_contents();

    foreach($linename_array as $linename) {
      $this->get_stops($linename, 1, $database_insert_array); //outbound
      $this->get_stops($linename, 2, $database_insert_array); //inbound
      $count++;
      echo "saved to array: ".$linename."\n";

      // save records every 5 bus lines (to avoid array getting too big)
      if($count % 5 == 0 || $count == $number_linenames) {
        $this->insert_route_reference($database_insert_array);
    	$database_insert_array = array();
      }
    }
    $this->DBH->commit();
  }

  // Function returns the correct direction in words for a given TfL directionid
  private function direction_from_id($directionid) {
    if($directionid == 1) {
      return "outbound";
    } else {
      return "inbound";
    }
  }

  // Function constructs an appropriate API URL and extracts the ordered stop
  // sequence from the data returned.  This is then added to the database insert
  // array ready for insertion in the database
  private function get_stops($linename, $directionid, &$database_insert_array) {
    $direction = $this->direction_from_id($directionid);
    $api_url = "https://api.tfl.gov.uk/Line/".$linename."/Route/Sequence/"
  	      .$direction."?serviceTypes=regular,night&app_id=c02bf3c4&"
	      ."app_key=5b3139aa0ef741b65ae823475f46a8b7";

    $json_array = $this->download_json($api_url);

    $ordered_line_routes = $json_array['orderedLineRoutes'];

    if(count($ordered_line_routes) !== 0) {
      $ordered_naptanid = $ordered_line_routes[0]['naptanIds']; // ordered stops

      $stop_number = 0;

      foreach($ordered_naptanid as $stopcode2) {
        $details = array('linename'=>$linename,
      	      	         'directionid'=>$directionid,
		         'stopcode2'=>$stopcode2,
		         'stopnumber'=>$stop_number);
        $database_insert_array[] = $details;
        $stop_number++;
      }
    }
  }

  // Function inserts the array of new route reference data into the database
  private function insert_route_reference($database_insert_array) {
    $sql = "INSERT INTO $this->route_reference_table (linename,directionid,"
               ."stopcode2,stopnumber) "
	       ."VALUES (:linename, :directionid, :stopcode2, :stopnumber)";

    $save_route = $this->DBH->prepare($sql);

    foreach($database_insert_array as $entry) {
      $save_route->bindValue(':linename', $entry['linename'],
			     PDO::PARAM_STR);
      $save_route->bindValue(':directionid', $entry['directionid'],
			     PDO::PARAM_INT);
      $save_route->bindValue(':stopcode2', $entry['stopcode2'],
      			     PDO::PARAM_STR);
      $save_route->bindValue(':stopnumber', $entry['stopnumber'],
			     PDO::PARAM_INT);
      $save_route->execute();
    }
  }

  // Function deletes all old data from the route reference database
  private function delete_database_contents() {
    $sql = "DELETE FROM $this->route_reference_table";
    $this->database->execute_sql($sql);
  }

  // Function returns an array of line names from the data returned by the API
  private function get_all_linenames($json_array) {
    $all_linenames = array();

    foreach($json_array as $linename) {
      $all_linenames[] = $linename['name'];
    } 
    return $all_linenames;
  }

  // Function downloads the data from the API, saves to a file and returns the
  // data as an array
  private function download_json($api_url) {
    $fp = fopen("route_reference_data.txt","w+");

    $Http = new HttpClient($api_url, $fp);
    $Http->start_data_collection();
    $Http->close_connection();
    $downloaded_data = file_get_contents("route_reference_data.txt");
    fclose($fp); // close file handler
    return json_decode($downloaded_data, true);
  }

}

?>