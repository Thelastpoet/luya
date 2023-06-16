<?php

namespace Luya;

class Luya_Cron {
    protected $luya_drafts;

    public function __construct(Luya_Drafts $luya_drafts) {
        $this->luya_drafts = $luya_drafts;
        
        add_filter('cron_schedules', array(__CLASS__, 'luya_add_cron_interval'));

        if (!wp_next_scheduled('luya_cron')) {
            wp_schedule_event(time(), 'every_five_minutes', 'luya_cron');
        }
        add_action('luya_cron', [$this, 'cron_callback']);
    }

    public function cron_callback() {
        // Set that a cron is running and set the value to the time it is running on 
        update_option('luya_cron_running', time());
        
        $draft = $this->luya_drafts->luya_fetch_posts();

        if (is_null($draft)) {
            error_log("No drafts or pending posts found.");
            return;
        }
        
        $this->luya_drafts->process_drafts($draft);
    
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