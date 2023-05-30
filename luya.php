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

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Luya_Plugin {

    const VERSION = '1.0.0';
    const PLUGIN_DIR = __DIR__;
    const PLUGIN_URL = __FILE__;

    public function __construct() {
        $this->includes();
        $this->init_hooks();

        new Luya_Settings();
    }

    private function includes() {
        require_once self::PLUGIN_DIR . '/includes/class-luya-openai.php';
        require_once self::PLUGIN_DIR . '/includes/class-luya-settings.php';
        require_once self::PLUGIN_DIR . '/includes/class-luya-drafts.php';
        require_once self::PLUGIN_DIR . '/includes/class-luya-publisher.php';
    }

    private function init_hooks() {
        add_action('admin_init', [$this, 'init']);
        
        if (!wp_next_scheduled('luya_cron')) {
            wp_schedule_event(time(), 'hourly', 'luya_cron');
        }
        add_action('luya_cron', [$this, 'cron_callback']);
    }

    public function init() {
        // Settings
        $luya_settings = new Luya_Settings();
    }

    public function cron_callback() {
        $ai_generator = new OpenAIGenerator();
        $luya_drafts = new Luya_Drafts($ai_generator);
        $luya_publisher = new Luya_Publisher($luya_drafts);
        $drafts = $luya_drafts->fetch_drafts();

        foreach ($drafts as $draft) {
            // Get the draft's author ID
            $current_user_id = $draft->post_author;

            $summary = $luya_drafts->summarize_post($draft->ID);
            $luya_drafts->rewrite_and_update_title($draft->ID);
            $luya_drafts->update_content($draft->ID, $summary);
            $luya_publisher->edit_and_publish_post($draft->ID, $current_user_id);
        }
    }
}

new Luya_Plugin();