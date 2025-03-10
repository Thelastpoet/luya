<?php

namespace Luya;

use WP_Post;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles post editing and publishing
 */
class Luya_Publisher {

    /**
     * Drafts handler instance
     *
     * @var Luya_Drafts
     */
    private $drafts;

    /**
     * Constructor
     *
     * @param Luya_Drafts $drafts Drafts handler instance
     */
    public function __construct(Luya_Drafts $drafts) {
        $this->drafts = $drafts;
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Edit and publish a post
     *
     * @param int $post_id Post ID to process
     * @return bool Success status
     */
    public function edit_and_publish_post($post_id) {
        $post_id = absint($post_id);
        
        if (!$post = get_post($post_id)) {
            $this->log_error(sprintf(
                __('Post ID %d not found', 'luya'),
                $post_id
            ));
            return false;
        }

        try {
            if (!current_user_can('edit_post', $post_id)) {
                throw new \Exception(__('Insufficient permissions to edit post', 'luya'));
            }

            $edit = $this->drafts->edit_post($post_id);
            if (!$edit) {
                throw new \Exception(__('Failed to edit post content', 'luya'));
            }

            if (!$this->drafts->update_content($post_id, wp_kses_post($edit))) {
                throw new \Exception(__('Failed to update post content', 'luya'));
            }

            if (!$this->drafts->publish_post($post_id)) {
                throw new \Exception(__('Failed to publish post', 'luya'));
            }

            do_action('luya_post_published', $post_id);
            return true;

        } catch (\Exception $e) {
            $this->log_error($e->getMessage());
            return false;
        }
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        error_log(sprintf('[Luya] %s', $message));
        add_settings_error(
            'luya_publisher',
            'luya_error',
            esc_html($message),
            'error'
        );
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        settings_errors('luya_publisher');
    }
}