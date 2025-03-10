<?php

namespace Luya;

/**
 * Handles scheduled tasks and cron jobs
 */
class Luya_Cron {
    const CRON_HOOK = 'luya_cron';
    const RUNNING_FLAG = 'luya_cron_running';
    const SCHEDULE = 'every_five_minutes';
    const INTERVAL = 300; // 5 minutes in seconds

    /**
     * @var Luya_Drafts
     */
    protected $luya_drafts;

    public function __construct(Luya_Drafts $luya_drafts) {
        $this->luya_drafts = $luya_drafts;
        $this->initialize();
    }

    /**
     * Initialize cron hooks and schedules
     */
    private function initialize() {
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        add_action(self::CRON_HOOK, array($this, 'process_drafts'));
        add_action('admin_init', array($this, 'maybe_schedule_cron'));
        register_deactivation_hook(LUYA_PLUGIN_FILE, array($this, 'deactivate_cron'));
    }

    /**
     * Schedule cron if not already scheduled
     */
    public function maybe_schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), self::SCHEDULE, self::CRON_HOOK);
        }
    }

    /**
     * Process drafts via cron
     */
    public function process_drafts() {
        if ($this->is_cron_running()) {
            $this->log_message('Cron job already running');
            return;
        }

        $this->set_cron_running();

        try {
            $draft = $this->luya_drafts->luya_fetch_posts();
            
            if (is_null($draft)) {
                $this->log_message('No drafts found to process');
                return;
            }

            $result = $this->luya_drafts->process_drafts($draft);
            
            if (!$result) {
                throw new \Exception('Failed to process draft: ' . $draft->ID);
            }

            $this->log_message(sprintf(
                'Successfully processed draft ID: %d',
                $draft->ID
            ));

        } catch (\Exception $e) {
            $this->log_message('Error: ' . $e->getMessage());
        } finally {
            $this->clear_cron_running();
        }
    }

    /**
     * Add custom cron interval
     *
     * @param array $schedules Existing schedules
     * @return array
     */
    public function add_cron_interval($schedules) {
        $schedules[self::SCHEDULE] = array(
            'interval' => self::INTERVAL,
            'display' => __('Every 5 minutes', 'luya')
        );
        return $schedules;
    }

    /**
     * Check if cron is already running
     *
     * @return bool
     */
    private function is_cron_running() {
        $running = get_option(self::RUNNING_FLAG);
        if (!$running) {
            return false;
        }

        // Clear stale lock after 15 minutes
        if (time() - $running > 900) {
            $this->clear_cron_running();
            return false;
        }

        return true;
    }

    /**
     * Set cron running flag
     */
    private function set_cron_running() {
        update_option(self::RUNNING_FLAG, time());
    }

    /**
     * Clear cron running flag
     */
    private function clear_cron_running() {
        delete_option(self::RUNNING_FLAG);
    }

    /**
     * Log message with timestamp
     *
     * @param string $message Message to log
     */
    private function log_message($message) {
        error_log(sprintf(
            '[Luya Cron %s] %s',
            wp_date('Y-m-d H:i:s'),
            $message
        ));
    }

    /**
     * Clean up on plugin deactivation
     */
    public function deactivate_cron() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        $this->clear_cron_running();
    }
}