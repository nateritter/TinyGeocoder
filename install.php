<?php
// If includes/config.php is present then include it
if (file_exists('includes/config.php')) {
  include('includes/config.php');
  // Check the permission of config file
  if (!is_writable('includes/config.php')) {
    $make_writable = true;
  } elseif (isset($_POST['yahoo_app_key']) && isset($_POST['cloudmade_api_key'])) {
  // If the install form has been submitted - write the config array to the config.php
    $file_data = <<<EOT
<?php
// Yahoo app key
\$config['yahoo_app_key'] = '{$_POST['yahoo_app_key']}';
// Cloudmade api key
\$config['cloudmade_api_key'] = '{$_POST['cloudmade_api_key']}';
EOT;
    // Write the data to the config.php file
    file_put_contents('includes/config.php', $file_data);
    $installed = true;
    $config = $_POST;
  }
} else {
  $create_config = true;
}
$title = "Tiny Geo-coder | Installation"; 
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
              <?php if (isset($create_config) || isset($make_writable)): ?>
              <h1>Installation</h1>
              <ul>
                <?php if (!isset($make_writable)): ?>
                <li>Copy <tt>includes/config.sample.php</tt> to <tt>includes/config.php</tt></li>
                <?php endif; ?>
                <li>Change the permissions of <tt>includes/config.php</tt> so that it is writable by web server</li>
              </ul>
              <div class="retry"><a href="install.php">Retry</a>
              <?php else: ?>
                <h1>API Keys</h1>
                <form method="post" action="install.php">
                  <?php if (isset($installed)): ?>
                  <div class="success">The service has been installed successfully. Click <a href="index.php">here</a> to continue.</div>
                  <?php endif; ?>
                  <div>
                    <label for="yahoo_key">Yahoo App Key (Optional): </label>
                    <input name="yahoo_app_key" id="yahoo_key" size="100%" value="<?php echo $config['yahoo_app_key']; ?>" />
                  </div>
                  <div>
                    <label for="cloudmade_api_key">Cloudmade Key  (Optional): </label>
                    <input name="cloudmade_api_key" id="cloudmade_api_key" size="100%" value="<?php echo $config['cloudmade_api_key']; ?>" />
                  </div>
                  <div>
                    <br />
                    <input type="submit" name="install" />
                    or <a href="/">skip this for now</a> (you an always come back later)
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <!--- Post Ends -->

        </div>
        <!-- Content area ends -->
      </div>
    </div>
<?php require_once 'includes/footer.php'; ?>