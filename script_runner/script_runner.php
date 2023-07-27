<?php
/*
Plugin Name: Python Script Runner
Description: Lists all Python scripts in the "scripts" folder and allows them to be executed. Also, lists all .txt files in the same folder. Allows setting a cron job for each script. Manages a list of URLs.
Author: OpenAI
Version: 1.0
*/

add_action('admin_menu', 'python_script_runner_menu');

function python_script_runner_menu(){
  add_menu_page( 'Python Script Runner', 'Python Script Runner', 'manage_options', 'python-script-runner', 'python_script_runner_page', '', 200 );
}

function save_cron_setting($script_name, $minutes) {
  $settings = get_option('python_script_runner_cron_settings', array());
  $settings[$script_name] = $minutes;
  update_option('python_script_runner_cron_settings', $settings);
}

function get_cron_setting($script_name) {
  $settings = get_option('python_script_runner_cron_settings', array());
  return isset($settings[$script_name]) ? $settings[$script_name] : '';
}

function python_script_runner_page(){
  echo "<p>exec enabled: " . (function_exists('exec') ? 'Yes' : 'No') . "</p>";
  echo "<p>shell_exec enabled: " . (function_exists('shell_exec') ? 'Yes' : 'No') . "</p>";
  
  $plugin_dir = plugin_dir_path( __FILE__ ) . 'scripts/';
  $plugin_url = plugin_dir_url( __FILE__ ) . 'scripts/';
  echo "<p>Plugin dir: $plugin_dir</p>";
  
  if ($handle = opendir($plugin_dir)) {
   echo "<h2>Python Scripts:</h2>";
   while (false !== ($entry = readdir($handle))) {
    if ($entry != "." && $entry != ".." && pathinfo($entry, PATHINFO_EXTENSION) == 'py') {
      $minutes = get_cron_setting($entry);
      echo "<p>$entry <a href='?page=python-script-runner&run=$entry'>Run</a> 
      <form method='post'>
        <input type='hidden' name='script_name' value='$entry'>
        <input type='text' name='minutes' placeholder='Enter minutes...' value='$minutes' required>
        <input type='submit' name='set_cron' value='Set Cron'>
      </form></p>";
    }
   }
   rewinddir($handle);  // Reset directory pointer
   
   echo "<h2>Text Files:</h2>";
   while (false !== ($entry = readdir($handle))) {
    if ($entry != "." && $entry != ".." && pathinfo($entry, PATHINFO_EXTENSION) == 'txt') {
      echo "<p><a href='" . esc_url($plugin_url . $entry) . "' target='_blank'>$entry</a></p>";
    }
   }
   rewinddir($handle);  // Reset directory pointer
   
   echo "<h2>ICS Files:</h2>";
   while (false !== ($entry = readdir($handle))) {
    if ($entry != "." && $entry != ".." && pathinfo($entry, PATHINFO_EXTENSION) == 'ics') {
      echo "<p><a href='" . esc_url($plugin_url . $entry) . "' target='_blank'>$entry</a></p>";
    }
   }
   closedir($handle);
  }

  if(isset($_GET['run'])) {
    chdir($plugin_dir);  // Change working directory to the scripts directory
    $command = '/usr/local/bin/python3 ' . escapeshellarg($_GET['run']) . ' 2>&1';
    $output = shell_exec($command);
    echo "<pre>$output</pre>";
  }

  if(isset($_POST['set_cron'])) {
    $script_name = $_POST['script_name'];
    $minutes = intval($_POST['minutes']);
    $hook_name = 'run_python_script_' . sanitize_file_name($script_name);

    // Remove existing cron job for this script
    wp_clear_scheduled_hook($hook_name);

    // Schedule new cron job
    if (! wp_next_scheduled($hook_name)) {
      wp_schedule_event(time(), 'custom_interval', $hook_name, array($script_name));
    }

    // Set custom cron schedule
    add_filter('cron_schedules', function($schedules) use ($minutes) {
      $schedules['custom_interval'] = array(
        'interval' => $minutes * 60,  // Convert minutes to seconds
        'display'  => "Every $minutes minutes",
      );
      return $schedules;
    });

    // Save cron setting
    save_cron_setting($script_name, $minutes);

    echo "<p>Set cron job to run $script_name every $minutes minutes.</p>";
  }

  // Add action for each cron job
  foreach (glob($plugin_dir . '*.py') as $script_path) {
    $script_name = basename($script_path);
    $hook_name = 'run_python_script_' . sanitize_file_name($script_name);
    add_action($hook_name, function($script_name) use ($plugin_dir) {
      chdir($plugin_dir);  // Change working directory to the scripts directory
      $command = '/usr/local/bin/python3 ' . escapeshellarg($script_name) . ' 2>&1';
      shell_exec($command);
    });
  }

  // URL list
  echo "<h2>URLs:</h2>";
  echo "<form method='post'><input type='text' name='new_url' placeholder='Enter URL...' required><input type='submit' name='add_url' value='Add URL'></form>";
  $urls = get_option('python_script_runner_urls', array());
  $file = fopen($plugin_dir . 'urls.txt', 'w');
  foreach ($urls as $url) {
     fwrite($file, $url . "\n");
  }
  fclose($file);
  $urls = get_option('python_script_runner_urls', array());
  foreach ($urls as $url) {
    echo "<p>$url <a href='?page=python-script-runner&delete_url=" . urlencode($url) . "'>Delete</a></p>";
  }

  // Add URL
  if (isset($_POST['add_url'])) {
    $new_url = $_POST['new_url'];
    if (!in_array($new_url, $urls)) {
      $urls[] = $new_url;
      update_option('python_script_runner_urls', $urls);
      echo "<p>Added URL: $new_url</p>";
    }
  }

  // Delete URL
  if (isset($_GET['delete_url'])) {
    $delete_url = $_GET['delete_url'];
    if (($key = array_search($delete_url, $urls)) !== false) {
      unset($urls[$key]);
      update_option('python_script_runner_urls', $urls);
      echo "<p>Deleted URL: $delete_url</p>";
    }
  }
}


?>