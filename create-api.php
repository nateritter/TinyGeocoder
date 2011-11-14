<?php
// Set the error_reporting to E_ALL
error_reporting(E_ALL);
// Include the config file
require_once 'includes/config.php';
// Include the mail library which processes the geocoding requests
require_once 'lib/geo.inc.php';
// If we have debug parameter in the URL then set the debug mode
$debug = (!empty($_GET['debug'])) ? true : false;
$geo = new Geo($debug, $config);

// Get either the coordinates or address depending on the current request parameter
if (!empty($_GET['q'])) {
  $r = $geo->get_latlon($_GET['q']);
} elseif (!empty($_GET['g'])) {
  $r = $geo->get_address($_GET['g']);
}

// If there were errors then display the same
$c = count($geo->last_status) -1;
if ($c<0) { $c = 0; }
if (!empty($geo->last_error[$c])) {
  echo $geo->last_error[$c];
} else {
  // If callabck method was specified then call it
  if (!empty($_GET['callback'])) {
    header( 'Content-Type: application/javascript' );
    if(!empty($_GET['q'])) {
      echo $_GET['callback']."([".$r."]);";
    } elseif (!empty($_GET['g'])) {
      echo $_GET['callback']."(['".$r."']);";
    }
  } else {
    // If no callback was specified, output the result in raw format
    echo $r;
  }
}
?>