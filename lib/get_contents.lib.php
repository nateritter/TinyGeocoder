<?php
function get_contents($url, $credentials = null, $http_post = false)
{
  # $credentials format = "username:password"
  $curl_handle = curl_init();
  curl_setopt($curl_handle, CURLOPT_URL, $url);
  if ($credentials) {
    curl_setopt($curl_handle, CURLOPT_USERPWD, $credentials);
  }
  if ($http_post) {
    curl_setopt($curl_handle, CURLOPT_POST, true);
  }
  curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
  $data = curl_exec($curl_handle);
  curl_close($curl_handle);
  return $data;
}
?>