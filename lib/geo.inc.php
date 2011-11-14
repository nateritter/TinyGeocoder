<?php
class Geo {
  public $last_status, $statuses, $last_error, $latlon, $location_address;

  private $yahoo_app_key = ''; //"ryp0qZfV34Hnz.xGLzFZ8NFuxGPqenBIpPTZcBRUK0Qmyt5gRu4tmUh5Kbkd9RWFDA--";
  private $cloudmade_api_key = ''; //"7a4e3ba6f5cf46e2914883f3371c90de";
  private $dbname = "db/tinygeo.sqlite";
  private $db;
  private $returned_from;
  private $google_output_type = "json";
  private $yahoo_output_type = "P";
  private $debug = false;

  function __construct($debug = false, $config = array()) {
    // Instanciate the sqlite db
    $this->db = new PDO('sqlite:' . $this->dbname);
    // Let system throw the warnings if debug is set to true
    if ($debug) {
      $this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
    }
    // Set the yahoo and cloudmade api key
    $this->yahoo_app_key = $config['yahoo_app_key'];
    $this->cloudmade_api_key = $config['cloudmade_api_key'];
    # error code dictionary
    # Google ref: http://code.google.com/apis/maps/documentation/javascript/v2/reference.html
    $this->statuses = array(
      'OK'  => 'Success',
      'ZERO_RESULTS' => 'No results found',
      'OVER_QUERY_LIMIT' => 'Query limit over',
      'REQUEST_DENIED' => 'Request denied',
      'INVALID_REQUEST' => 'Invalid input parameters',
      '200' => 'Success',
      '400' => 'Nope, there\'s nothing there (invalid?).',
      '403' => 'Bummer, we\'ve had too many queries and one of our data sources has decided not to work. Please <a href="mailto:info@tinygeocoder.com">let us know</a>.',
      '500' => 'Ouch, there was a server error.',
      '503' => 'Ouch, there was a server error with the data provider.',
      '601' => 'Gotta have a query to .. well, query.',
      '602' => 'Address isn\'t known.',
      '603' => 'Address isn\'t available.',
      '604' => 'Not sure of the directions.',
      '610' => 'Um, huh? Possible bad or invalid key.',
      '620' => 'Bummer, we\'ve had too many queries to handle. Please <a href="mailto:info@tinygeocoder.com">let us know</a>.',
    );
    if ($debug) {
      $this->debug = true;
    }
  }

  function get_latlon($q, $urlencoded = false) {
    if ($this->debug) {
      echo "<strong>get_latlon($q, $urlencoded);</strong>\n";
      echo '<pre>'; print_r($this); echo '</pre>';
    }
    if ($urlencoded == false) {
      $q = urlencode($q);
    }
    $this->q = $q;
    $this->save_attempt($q);
    $c = $this->get_cached_geo($q);
    if (!empty($c)) {
      $this->returned_from = "database";
      return $this->latlon;
    } else {
      $r = $this->geocode_google($q);
      if (!$r) {
        $r = $this->geocode_yahoo($q);
        if (!$r) {
          $r = $this->geocode_cloudmade($q);
          // if (!$r) {
          //   $r = $this->geocode_geocoderus($q);
          // }
        }
      } 
      if ($this->debug) {
        echo "<strong>get_latlon($q, $urlencoded); // end of function </strong>\n";
        echo '<pre>'; print_r($this); echo '</pre>';
      }
      if (!empty($this->lat) && !empty($this->lon)) {
        return $this->lat.','.$this->lon; 
      } else {
        $c = count($this->last_status) -1;
        if ($c<0) { $c = 0; }
        return $this->last_error[$c];
      }
    }
  }

  function get_address($q, $urlencoded = false) {
    // if ($urlencoded == false) {
    //   $q = urlencode($q);
    // }
    $this->q = $q;
    $this->save_attempt($q);
    $c = $this->get_cached_reverse_geo($q);
    if (!empty($c)) {
      $this->returned_from = "database";
      return $this->location_address;
    } else {
      $r = $this->reverse_geocode_google($q);
      if (!$r) {
        $r = $this->reverse_geocode_yahoo($q);
        if (!$r) {
          $r = $this->reverse_geocode_cloudmade($q);
        }
      }
    }
    if ($this->debug) {
      echo "<strong>get_address($q, $urlencoded); // end of function </strong>\n";
      echo '<pre>'; print_r($this); echo '</pre>';
    }
    if (!empty($this->location_address)) {
      return $this->location_address;
    } else {  
      $c = count($this->last_status) -1;
      if ($c<0) { $c = 0; }
      return $this->last_error[$c];
    }
  }

  private function save_attempt($q) {
    $q = strtolower($q);
    $q = trim($q);

    # prepare the statement to insert the row for the attempt made at the api
    $stmt = $this->db->prepare("INSERT INTO ips (id, q, created, ip) VALUES (NULL, :q, :created, :ip)");

    // Bind the parameters
    $stmt->bindParam(':q', $q, PDO::PARAM_STR);
    $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $stmt->bindParam(':created', date('Y-m-d H:i:s'));
    // Execute the insert statement
    if ($stmt->execute()) {
      return false;
    } else {
      return true;
    }
  }

  private function geocode_cloudmade($q) {
    // If not api key is set for cloudmade - then return false
    if (empty($this->cloudmade_api_key)) {
      return false;
    }
    $key = strtoupper($this->cloudmade_api_key);
    $last_url = "http://geocoding.cloudmade.com/$key/geocoding/v2/find.js?query=".$q."&return_geometry=false";
    $this->last_url[] = $last_url;
    $r = $this->get_geo($last_url);
    if ($this->debug) {
      echo "<strong>geocode_cloudmade($q);</strong>\n";
      echo '<pre>'; print_r($r); echo '</pre>';
      echo '<pre>'; htmlentities($r); echo '</pre>';
    }
    if (!empty($r)) {
      $rstr = (string) $r;
      $r = json_decode($r);
    }
    if (!empty($r) && $rstr != '{}') {
      $this->exact_response[] = $r;
      $this->lat = $r->features[0]->centroid->coordinates[0];
      $this->lon = $r->features[0]->centroid->coordinates[1];
      if (!empty($this->lat) && !empty($this->lon)) {
        if (!$this->save_geo($q, $this->lat.','.$this->lon, null, "cloudmade")) {
          if (!empty($this->http_code)) {
            $this->last_status[] = $this->http_code;
          } else {
            $this->last_status[] = '200';
          }
          $this->last_error[] = "couldn't save geo";
          $this->returned_from[] = "cloudmade";
          return false;
        } 
      } else {
        if (!empty($this->http_code)) {
          $this->last_status[] = $this->http_code;
        } else {
          $this->last_status[] = '400';
        }
        $this->last_error[] = "either lat or lon returned empty from cloudmade";
        $this->returned_from[] = "cloudmade";
        return false;
      }
    } else {
      if (!empty($this->http_code)) {
        $this->last_status[] = $this->http_code;
      } else {
        $this->last_status[] = '400';
      }
      $this->last_error[] = "couldn't get geo from cloudmade";
      $this->returned_from[] = "cloudmade";
      return false;
    }
    $this->last_status[] = '200';
    $this->last_error[] = null;
    $this->returned_from[] = "cloudmade";
    return true;
  }

  private function reverse_geocode_cloudmade($q) {
    // If not api key is set for cloudmade - then return false
    if (empty($this->cloudmade_api_key)) {
      return false;
    }
    $key = strtoupper($this->cloudmade_api_key);
    $last_url = "http://geocoding.cloudmade.com/$key/geocoding/v2/find.js?around=".$q."&distance=closest&object_type=address&return_location=true";
    $this->last_url[] = $last_url;
    $r = $this->get_geo($last_url);
    $this->last_status[] = $this->http_code;
    $c = count($this->last_status) -1;
    if ($c<0) { $c = 0; }
    if (!empty($r) && $this->last_status[$c] == '200') {
      $r = json_decode($r);
      $this->exact_response[] = $r;
      $addr = $this->object_to_array($r->features[0]->properties);
      $loc = $this->object_to_array($r->features[0]->location);
      $housenum = @$addr['addr:housenumber'];
      $street = (!empty($addr['addr:street'])) ? $addr['addr:street'] : @$loc['road'];
      $city = (!empty($addr['addr:city'])) ? $addr['addr:city'] : @$loc['city'];
      $state = (!empty($addr['is_in:state_code'])) ? $addr['is_in:state_code'] : @$loc['county'];
      $zip = (!empty($addr['addr:postcode'])) ? $addr['addr:postcode'] : @$loc['postcode'];
      if (!empty($housenum) && !empty($street) && !empty($city) && !empty($state) && !empty($zip)) {
        $this->location_address = $housenum.' '.$street.', '.$city.', '.$state.' '.$zip;
        $return = $this->save_reverse_geo($q, $this->location_address, null, "cloudmade");
        if (!$return) {
          $this->last_error[] = "couldn't save address";
          // $this->returned_from[] = "cloudmade";
          // return false;
        }
      } else {
        $this->last_error[] = "couldn't get complete address from cloudmade";
        $this->returned_from[] = "cloudmade";
        return false;
      }
    } else {
      $this->last_error[] = "couldn't get geo from cloudmade";
      $this->returned_from[] = "cloudmade";
      return false;
    }
    $this->last_error[] = null;
    $this->returned_from[] = "cloudmade";
    return true;
  }

  private function geocode_geocoderus($q) {
    // $base = $PHP_SELF ."?address=" . $q;
    // $last_url = "http://nateritter:sp3xed@geocoder.us/member/service/csv?address=" . (urlencode($q));
    $last_url = "http://geocoder.us/service/csv/geocode?address=" . $q; //(urlencode($q));
    if ($gm = @fopen($last_url,"r")) {
      // Special csv code instead of using get_geo();
      if ($tmp = fgetcsv($gm, 8000)) {
        fclose($gm);
        $la = $tmp["0"];
        $lo = $tmp["1"];

        if ($this->debug) {
          echo "<strong>geocode_geocoderus($q);</strong>\n";
          echo '<pre>'; print_r($last_url); echo '</pre>';
          echo '<pre>'; print_r($tmp); echo '</pre>';
        }

        $this->last_url[] = $last_url;
        $this->exact_response[] = $tmp;

        if ($la && $lo) {
          $this->lat = $la;
          $this->lon = $lo;
          $this->latlon = $la.','.$lo;
          if (!$this->save_geo($q, $this->lat.','.$this->lon, null, "geocoder.us")) {
            $this->last_error[] = "couldn't save geo";
          }
        } else {
          $this->last_status[] = '610';
          $this->last_error[] = "couldn't get geo from geocoder.us";
          $this->returned_from[] = "geocoder.us";
          // $this->last_error[] = $this->last_status.' : '.$this->statuses[$this->last_status];
          return false;
        }
      } else {
        $this->last_status[] = '610';
        $this->last_error[] = "couldn't get geo from geocoder.us (fgetcsv failed)";
        $this->returned_from[] = "geocoder.us";
        return false;
      }
    } else {
      $this->last_status[] = '503';
      $this->last_error[] = "Failed to open stream.";
      $this->returned_from[] = "geocoder.us";
      return false;
    }
    $this->last_status[] = '200';
    $this->last_error[] = null;
    $this->returned_from[] = "geocoder.us";
    return true;
  }

  private function geocode_google($q) {
    $output = $this->google_output_type;

    $last_url = "http://maps.googleapis.com/maps/api/geocode/$output?address=$q&sensor=false";
    $this->last_url[] = $last_url;
    $r = $this->get_geo($last_url);
    $r = htmlentities($r, ENT_NOQUOTES);
    $r = json_decode($r);

    if ($this->debug) {
      echo "<strong>geocode_google($q);</strong>\n";
      echo '<pre>'; print_r($r); echo '</pre>';
    }
    $this->exact_response[] = $r;
    $this->last_status[] = (int)$r->status;
    $c = count($this->last_status) -1;
    if ($c<0) { $c = 0; }
    if ($this->last_status[$c] == 'OK') {
      $this->lat = $r->results[0]->geometry->location->lat;
      $this->lon = $r->results[0]->geometry->location->lng;
      $this->latlon = $this->lat.','.$this->lon; # I have no idea why $this->latlon isn't being set properly

      if (!$this->save_geo($q, $this->lat.','.$this->lon, null, "google"))
      {
        $this->last_error[] = "couldn't save geo";
      }
    } else {
      $this->returned_from[] = "google";
      $c = count($this->last_status) -1;
      if ($c<0) { $c = 0; }
      $this->last_error[] = $this->last_status[$c].' : '.$this->statuses[$this->last_status[$c]];
      return false;
    }
    $this->last_error[] = null;
    $this->returned_from[] = "google";
    return true;
  }

  private function reverse_geocode_google($q) {
    $output = $this->google_output_type;
    $last_url = "http://maps.googleapis.com/maps/api/geocode/$output?latlng=$q&sensor=false&oe=utf8";
    $this->last_url[] = $last_url;
    $r = $this->get_geo($last_url);
    $r = htmlentities($r, ENT_NOQUOTES);
    $r = json_decode($r);

    $this->exact_response[] = $r;
    $this->last_status[] = (int)$r->status;
    $c = count($this->last_status) -1;
    if ($c<0) { $c = 0; }
    if ($this->last_status[$c] == 'OK') {
      $this->location_address = (string)$r->results[0]->formatted_address;
      $return = $this->save_reverse_geo($q, $this->location_address, null, "google");
      if (!$return)
      {
        $this->last_error[] = "couldn't save address";
      }
    } else {
      $this->returned_from[] = "google";
      $c = count($this->last_status) -1;
      if ($c<0) { $c = 0; }
      $this->last_error[] = $this->last_status[$c].' : '.$this->statuses[$this->last_status[$c]];
      return false;
    }
    $this->last_error[] = null;
    $this->returned_from[] = "google";
    return true;
  }

  private function geocode_yahoo($q) {
    // If not api key is set for yahoo - then return false
    if (empty($this->yahoo_app_key)) {
      return false;
    }
    $output = $this->yahoo_output_type;
    $key = $this->yahoo_app_key;
    $last_url = "http://where.yahooapis.com/geocode?q=$q&appid=$key&flags=$output";

    $this->last_url[] = $last_url;
    $r = $this->get_geo($last_url);
    
    if ($this->debug) {
      echo "<strong>geocode_yahoo($q);</strong>\n";
      echo '<pre>'; echo htmlentities($r); echo '</pre>';
    }
    // Unserialize the response
    $r = unserialize($r);
    
    // If there is no error and if we have latitude/longitude
    if ($r['ResultSet']['Error'] == 0 && !empty($r['ResultSet']['Result'][0]['latitude'])) {
      $this->exact_response[] = $r;
      $this->last_status[] = '200';
      $this->lat = $r['ResultSet']['Result'][0]['latitude'];
      $this->lon = $r['ResultSet']['Result'][0]['longitude'];

      if (!empty($this->lat) && !empty($this->lon)) {
        $this->latlon = $this->lat.','.$this->lon; # I have no idea why $this->latlon isn't being set properly
        if (!$this->save_geo($q, $this->lat.','.$this->lon, null, "yahoo"))
        {
          $this->last_error[] = "couldn't save geo";
          $this->returned_from[] = "yahoo";
          return false;
        }
      }
      $this->last_error[] = null;
      $this->returned_from[] = "yahoo";
      return true;
    } else {
      // If Error is not 0 then some error was returned
      $this->last_status[] = $r['ResultSet']['Error'];
      $this->returned_from[] = "yahoo";
      $this->last_error[] = $r['ResultSet']['ErrorMessage'];
      return false;
    }
  }

  private function reverse_geocode_yahoo($q) {
    // If not api key is set for yahoo - then return false
    if (empty($this->yahoo_app_key)) {
      return false;
    }
    $lat = substr($q, 0, strpos($q, ","));
    $lon = substr($q, strpos($q, ",")+1);
    $output = $this->yahoo_output_type;
    $key = $this->yahoo_app_key;
    $last_url = "http://where.yahooapis.com/geocode?q=$q&appid=$key&flags=$output&gflags=R";

    $this->last_url[] = $last_url;
    $r = $this->get_geo($last_url);
    
    // Unserialize the response
    $r = unserialize($r);
    
    // If there is no error and if we have latitude/longitude
    if ($r['ResultSet']['Error'] == 0 && !empty($r['ResultSet']['Result'][0]['latitude'])) {
      $this->exact_response[] = $r;
      $this->last_status[] = '200';
      $address = array();
      // Build the address array. It will comprise of street, city, state, postcode and country code.
      if (!empty($r['ResultSet']['Result'][0]['street'])) {
        $address[] = $r['ResultSet']['Result'][0]['street'];
      }
      
      if (!empty($r['ResultSet']['Result'][0]['city'])) {
        $address[] = $r['ResultSet']['Result'][0]['city'];
      }

      if (!empty($r['ResultSet']['Result'][0]['postal'])) {
        $postal = $r['ResultSet']['Result'][0]['postal'];
      }
      
      if (!empty($r['ResultSet']['Result'][0]['statecode'])) {
        if (!empty($postal)) {
          $address[] = $r['ResultSet']['Result'][0]['statecode'] . ' ' . $postal;
        } else {
          $address[] = $r['ResultSet']['Result'][0]['statecode'];
        }
      } else  if (!empty($r['ResultSet']['Result'][0]['state'])) {
        if (!empty($postal)) {
          $address[] = $r['ResultSet']['Result'][0]['state'] . ' ' . $postal;
        } else {
          $address[] = $r['ResultSet']['Result'][0]['state'];
        }
      }
      
      if (!empty($r['ResultSet']['Result'][0]['countrycode'])) {
        $address[] = $r['ResultSet']['Result'][0]['countrycode'];
      }
      // Implode the address array into a string
      $this->location_address = implode(', ', $address);
      // Save the reverse geo data
      $return = $this->save_reverse_geo($q, $this->location_address, null, "yahoo");
      
      if (!$return)
      {
        $this->last_error[] = "couldn't save address";
        $this->returned_from[] = "yahoo";
        return false;
      }

      $this->last_error[] = null;
      $this->returned_from[] = "yahoo";
      return true;
    } else {
      // If Error is not 0 then some error was returned
      $this->last_status[] = $r['ResultSet']['Error'];
      $this->returned_from[] = "yahoo";
      $this->last_error[] = $r['ResultSet']['ErrorMessage'];
      return false;
    }
  }

  private function get_cached_geo($q) {
    $q = strtolower($q);
    $q = trim($q);

    # get data
    $stmt = $this->db->prepare("SELECT * FROM geo WHERE location_name = :location_name LIMIT 1");
    $stmt->bindParam(':location_name', $q, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ((!$row) or (empty($row))) {
      return false;
    } else {
      $this->latlon = $row['location_geo'];
      $this->location_name = $row['location_name'];
      $this->id = $row['id'];
      $this->override = $row['override'];
    }
    return $row;
  }

  private function get_cached_reverse_geo($q) {
    $q = strtolower($q);
    $q = trim($q);

    # get data
    $stmt = $this->db->prepare("SELECT * FROM geo WHERE location_geo = :q AND location_address IS NOT NULL AND location_address != '' LIMIT 1");
    $stmt->bindParam(':q', $q, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ((!$row) or (empty($row))) {
      return false;
    } else {
      $this->latlon = $row['location_geo'];
      $this->location_name = $row['location_name'];
      $this->location_address = $row['location_address'];
      $this->id = $row['id'];
      $this->override = $row['override'];
    }
    return $row;

  }

  private function save_geo($q, $geo, $override = false, $source = null) {
    // echo 'q:<pre>'; print_r($q); echo '</pre>';
    // echo 'geo:<pre>'; print_r($geo); echo '</pre>';
    // echo 'override:<pre>'; print_r($override); echo '</pre>';
    // echo 'source:'; print_r($source); die();
    # Make sure it's formatted and cleaned
    $q = strtolower($q);
    $q = trim($q);
    $override = (int)$override;

    # check to make sure it's not already saved
    $r = $this->get_cached_geo($q);
    if ($geo == ",") {
      $this->last_error[] = 'lat/lon was not set.';
      return false;
    } elseif ($r) { # already saved
      return true; # hijack and just return true. it's already saved
    } else {
      $stmt = $this->db->prepare('INSERT INTO geo 
          (id, location_name, location_geo, source, created, override) 
          VALUES (NULL, :location_name, :location_geo, :source, :created, :override)');
      $stmt->bindParam(':location_name', $q, PDO::PARAM_STR);
      $stmt->bindParam(':location_geo', $geo, PDO::PARAM_STR);
      $stmt->bindParam(':source', $source, PDO::PARAM_STR);
      $stmt->bindParam(':created', date('Y-m-d H:i:s'));
      $stmt->bindParam(':override', $override);


      if (!$stmt->execute()) {
        $this->last_error[] = 'unable to save the geo data';
        return false;
      } else {
        $this->id = $this->db->lastInsertId();
        return true;
      }
    }
    # should have returned true or false already
  }

  private function save_reverse_geo($q, $address, $override = false, $source = null) {
    // echo 'q:<pre>'; print_r($q); echo '</pre>';
    // echo 'geo:<pre>'; print_r($geo); echo '</pre>';
    // echo 'override:<pre>'; print_r($override); echo '</pre>';
    // echo 'source:'; print_r($source); die();
    # Make sure it's formatted and cleaned
    $q = strtolower($q);
    $q = trim($q);
    $q = str_replace(" ", "", $q);
    $override = (int)$override;

    # check to make sure it's not already saved
    $r = $this->get_cached_reverse_geo($q);
    if ($address == ",") {
      $this->last_error[] = 'lat/lon was not set.';
      return false;
    } elseif ($r) { # already saved
      return true; # hijack and just return true. it's already saved
    } else {

      $stmt = $this->db->prepare("INSERT INTO geo
                      (id, location_geo, location_name, location_address, source, created, override) VALUES 
                      (NULL, :location_geo, '', :location_address, :source, :created, :override)");
      $stmt->bindParam(':location_geo', $q, PDO::PARAM_STR);
      $stmt->bindParam(':location_address', $address, PDO::PARAM_STR);
      $stmt->bindParam(':source', $source, PDO::PARAM_STR);
      $stmt->bindParam(':created', date('Y-m-d H:i:s'));
      $stmt->bindParam(':override', $override);

      if (!$stmt->execute()) {
        $this->last_error[] = 'unable to save reverse geo data';
        return false;
      } else {
        $this->id = $this->db->lastInsertId();
        return true;
      }

    }
    # should have returned true or false already
  }

  private function get_geo($url, $http_post=false, $credentials=false, $headers=false ) {
    # $credentials format = "username:password"
    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_URL, $url);
    if ($credentials) {
      curl_setopt($curl_handle, CURLOPT_USERPWD, $credentials);
    }
    if ($http_post) {
      curl_setopt($curl_handle, CURLOPT_POST, true);
    }
    if ($headers) {
      curl_setopt($curl_handle, CURLOPT_HEADER, true);
    }
    $httpheader = array("X-Forwarded-For" => $_SERVER["REMOTE_ADDR"]);
    curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $httpheader);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($curl_handle);
    $this->http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
    curl_close($curl_handle);
    // echo '<pre>URL: '; print_r($url); echo '</pre>';
    // echo '<pre>HTTP_CODE: '; print_r($this->http_code); echo '</pre>';
    // echo '<pre>DATA: '; print_r($data); echo '</pre>';
    // echo '<pre>HTTPHEADER: '; print_r($httpheader); echo '</pre>';
    // die();
    return $data;
  }

  // Changes objects to arrays and removes weird charcters
  private function object_to_array($mixed) {
    if(is_object($mixed)) $mixed = (array) $mixed;
    if(is_array($mixed)) {
        $new = array();
        foreach($mixed as $key => $val) {
            $key = preg_replace("/^\\0(.*)\\0/","",$key);
            $new[$key] = $this->object_to_array($val);
        }
    }
    else $new = $mixed;
    return $new;
  }
}
?>