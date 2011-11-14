<?php
// First check if the service has been installed or not
// If includes/config.php is not present then redirect to install.php
if (!file_exists('includes/config.php')) {
  header('Location: install.php');
  exit;
}
$title = "Tiny Geo-coder | The fastest way to find latitude and longitude"; 
$desc = "Tiny Geo-coder is the simplest, fastest way to find coordinates for a location.";
$keywords = "geocoder, geo, code, coding, lat, long, lon, latitude, longitude, gps";
require_once 'includes/header.php'; 
require_once 'includes/nav.php';
?>
      <div class="wrap background">

        <!-- Content area starts -->
        <div id="content" class="left-col wrap">

          <!--- Post Starts -->
          <div class="post wrap">
            <div class="post-meta left-col">

            </div>
            <div class="post-content right-col">
              <p>The service is installed and running fine...Click <a href="install.php">here</a> to configure API keys.</p>
              <h3>Basic Usage</h3>
              <h4>Geocoding service API</h4>
              <blockquote>
                <?php
                  $base = dirname($_SERVER['PHP_SELF']);
                  if ($base == '/') {
                    $base = '';
                  }
                  $url = "http://{$_SERVER['HTTP_HOST']}{$base}/create-api.php";
                ?>
                <a href="<?php echo $url . "?q=Perris,CA"; ?>"><?php echo $url . "?q=Perris,CA"; ?></a>
              </blockquote>
              And the response will be...
              <blockquote>
                <p>33.790348,-117.226085</p>
              </blockquote>

              <h4>Reverse geocoding service API</h4>
              <blockquote>
                <a href="<?php echo $url . "?g=33.790348,-117.226085"; ?>"><?php echo $url . "?g=33.790348,-117.226085"; ?></a>
              </blockquote>
              And the response will be...
              <blockquote>
                <p>498 N Perris Blvd, Perris, CA 92570, USA</p>
              </blockquote>
            </div>
          </div>
          <!--- Post Ends -->

        </div>
        <!-- Content area ends -->
      </div>
    </div>
<?php require_once 'includes/footer.php'; ?>