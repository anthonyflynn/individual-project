<?php

include ('/data/individual_project/php/modules/ProcessArrivalDataClass.php');
include ('/data/individual_project/php/modules/LinkTimesDateClass.php');
include ('/data/individual_project/php/modules/LinkTimesDayClass.php');

$backup_time = time();

// Remove duplicate arrivals data and delete negative linktimes
$process_arrivals = new ProcessArrivals($backup_time);
$process_arrivals->process_data();

// Extract average linktimes by hour for the previous date
$linktimes_date = new LinkTimesDate($backup_time);
$linktimes_date->complete_update();

// Update average linktimes by hour for the day of the week on which the 
// previous day occurs
$linktimes_day = new LinkTimesDay($backup_time);
$linktimes_day->complete_update();

?>