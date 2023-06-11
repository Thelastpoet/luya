<?php
namespace Luya;

class Luya_Cron {
    public function __construct() {
        add_filter('cron_schedules', array(__CLASS__, 'luya_add_cron_interval'));

        if (!wp_next_scheduled('luya_cron')) {
            wp_schedule_event(time(), 'every_five_minutes', 'luya_cron');
        }
        add_action('luya_cron', [$this, 'cron_callback']);
    }

    public function cron_callback() {
        // Check if there is an already running cron job
        $luya_cron_running = get_option('luya_cron_running', false);

        if ($luya_cron_running != false) {
            // Check if the cron is running for more than 5 minutes
            if (time() - $luya_cron_running > 300) {
                // More than 5 minutes, delete the option
                delete_option('luya_cron_running');
            } else {
                // Less than 5 minutes, exit
                return;
            }
        }

        // Set that a cron is running and set the value to the time it is running on 
        update_option('luya_cron_running', time());

        $ai_generator = new OpenAIGenerator();
        $luya_drafts = new Luya_Drafts($ai_generator);
        $luya_publisher = new Luya_Publisher($luya_drafts);
        $drafts = $luya_drafts->luya_fetch_posts();
        
        if(empty($drafts)) {
            error_log("No drafts or pending posts found.");
            return;
        }
        
        // Take only the first draft from the list
        $draft = $drafts[0];
        
        $current_user_id = $draft->post_author;

        // Check if the post author can publish posts
        $user = get_userdata($current_user_id);
        
        if (in_array('author', (array) $user->roles) || in_array('editor', (array) $user->roles) || in_array('administrator', (array) $user->roles)) {
            $summary = $luya_drafts->summarize_post($draft->ID);
            $luya_drafts->rewrite_and_update_title($draft->ID);
            $luya_drafts->update_content($draft->ID, $summary);
            $luya_publisher->edit_and_publish_post($draft->ID, $current_user_id);
        } else {
            error_log("User {$current_user_id} is not allowed to publish posts.");
        }

        // Delete the option flag that the cron job is running
        delete_option('luya_cron_running');
    }

    // Add custom cron schedule
    public static function luya_add_cron_interval($schedules) {
        $schedules['every_five_minutes'] = array(
            'interval' => 5*60,
            'display' => __('Every 5 minutes')
        );
    
        return $schedules;
    }
}
