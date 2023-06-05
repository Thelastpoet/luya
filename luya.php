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
         $this->init_hooks();
 
         new Luya_Settings();
     }
 
     private function includes() {
         require_once $this->PLUGIN_DIR . 'includes/class-luya-openai.php';
         require_once $this->PLUGIN_DIR . 'includes/class-luya-settings.php';
         require_once $this->PLUGIN_DIR . 'includes/class-luya-drafts.php';
         require_once $this->PLUGIN_DIR . 'includes/class-luya-publisher.php';
     }
 
     private function init_hooks() {
         add_action('admin_init', [$this, 'init']);
         
         if (!wp_next_scheduled('luya_cron')) {
             wp_schedule_event(time(), 'every_ten_minutes', 'luya_cron');
         }
         add_action('luya_cron', [$this, 'cron_callback']);
     }
 
     public function init() {
         $luya_settings = new Luya_Settings();
     }
 
     public function cron_callback() {
         $this->log("Cron job started.");
         
         $ai_generator = new OpenAIGenerator();
         $luya_drafts = new Luya_Drafts($ai_generator);
         $luya_publisher = new Luya_Publisher($luya_drafts);
         $drafts = $luya_drafts->fetch_drafts();
         
         if(empty($drafts)) {
             $this->log("No drafts found.");
             return;
         }
         
         foreach ($drafts as $draft) {
             $current_user_id = $draft->post_author;
     
             // Check if the post author can publish posts
             $user = get_userdata($current_user_id);
             if (in_array('author', (array) $user->roles) || in_array('editor', (array) $user->roles) || in_array('administrator', (array) $user->roles)) {
                 $summary = $luya_drafts->summarize_post($draft->ID);
                 $luya_drafts->rewrite_and_update_title($draft->ID);
                 $luya_drafts->update_content($draft->ID, $summary);
                 $luya_publisher->edit_and_publish_post($draft->ID, $current_user_id);
                 $this->log("Draft ID {$draft->ID} processed.");
             } else {
                 $this->log("The author of draft ID {$draft->ID} does not have the capability to publish posts.");
             }
         }
         
         $this->log("Cron job ended.");
     }    
 
     public function log($message) {
         if (WP_DEBUG === true) {
             if (is_array($message) || is_object($message)) {
                 error_log(print_r($message, true));
             } else {
                 error_log($message);
             }
         }
     }
     
 }
 
 // Add custom cron schedule
 function luya_add_cron_interval($schedules) {
     $schedules['every_ten_minutes'] = array(
         'interval' => 10*60,
         'display' => __('Every 10 minutes')
     );
 
     return $schedules;
 }
 
 add_filter('cron_schedules', 'luya_add_cron_interval');
 
 new Luya(); 