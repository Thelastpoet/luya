<?php

/**
 * Plugin Name: Luya
 * Plugin URI: https://nabaleka.com
 * Description: This plugin fetches draft WordPress posts, summarizes them with OpenAI, creates new content from the summaries, and publishes the posts.
 * Version: 1.0.0
 * Author: Ammanulah Emmanuel
 * Author URI: https://ammanulah.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luya
 * Domain Path: /languages
 */

 namespace Luya;

 if (!defined('ABSPATH')) {
     exit; // Exit if accessed directly.
 }

 class Luya {
 
    const VERSION = '1.0.0';
    public $PLUGIN_DIR;
    public $PLUGIN_URL;
 
    public function __construct() {
        $this->PLUGIN_DIR = plugin_dir_path(__FILE__);
        $this->PLUGIN_URL = plugin_dir_url(__FILE__);
 
        $this->includes();
 
        new Luya_Settings();
        new Luya_Cron();
    }
 
    private function includes() {
        require_once $this->PLUGIN_DIR . 'includes/class-luya-openai.php';
        require_once $this->PLUGIN_DIR . 'includes/class-luya-settings.php';
        require_once $this->PLUGIN_DIR . 'includes/class-luya-drafts.php';
        require_once $this->PLUGIN_DIR . 'includes/class-luya-publisher.php';
        require_once $this->PLUGIN_DIR . 'includes/cron.php';
    }
}
 
new Luya();