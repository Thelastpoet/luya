<?php

namespace Luya;

use Luya\OpenAIGenerator;
use WP_Query;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Luya_Drafts {
    private $ai_generator;

    public function __construct(OpenAIGenerator $ai_generator) {
        $this->ai_generator = $ai_generator;
    }

    // Fetches all draft or pending posts
    public function luya_fetch_posts() {
        $args = array(
            'post_status' => array('draft', 'pending'),
            'posts_per_page' => -1,
        );

        $query = new WP_Query($args);

        return $query->posts ? $query->posts[0] : null;
    }

    // Process the drafts
    public function process_drafts($draft) {
        $current_user_id = $draft->post_author;

        if (!$this->user_can_publish($current_user_id)) {
            error_log("User {$current_user_id} is not allowed to publish posts.");
            return;
        }

        error_log("User {$current_user_id} is allowed to publish posts.");

        $summary = $this->summarize_post($draft->ID);
        $this->rewrite_and_update_title($draft->ID);
        $this->update_content($draft->ID, $summary);
        $this->publish_post($draft->ID);
    }

    // Summarizes a post using OpenAI
    public function summarize_post(int $post_id) {
        $post_id = intval($post_id);
        $post = get_post($post_id);
        if ($post) {
            $summary = $this->ai_generator->generate_summary($post->post_content);
            return $summary;
        }
        return false;
    }

    // Updates a post with new content
    public function update_content(int $post_id, string $new_content) {
        $post_id = intval($post_id);
        $new_content = ($new_content);
        $this->update_post($post_id, array('post_content' => wp_kses_post($new_content)));
    }

    // Rewrite and update the post title using OpenAI
    public function rewrite_and_update_title(int $post_id) {
        $post_id = intval($post_id);
        $title = $this->get_title($post_id);
        $new_title = $this->rewrite_title($title);
        $this->update_title($post_id, $new_title);
    }

    // Publishes a post
    public function publish_post(int $post_id) {
        $post_id = intval($post_id);
        $current_time = current_time('mysql');
        $this->update_post($post_id, array('post_status' => 'publish', 'post_date' => $current_time, 'post_date_gmt' => get_gmt_from_date($current_time)));
    }

    // Check if user can publish
    private function user_can_publish($user_id) {
        $user = get_userdata($user_id);
        $roles_that_can_publish = ['author', 'editor', 'administrator'];

        foreach ($roles_that_can_publish as $role) {
            if (in_array($role, (array)$user->roles)) {
                return true;
            }
        }

        return false;
    }

    // Updates post data using wp_update_post
    private function update_post(int $post_id, array $data) {
        $post_id = intval($post_id);
        $data['ID'] = $post_id;

        wp_update_post($data, true);

        if (is_wp_error($post_id)) {
            $errors = $post_id->get_error_messages();
            foreach ($errors as $error) {
                _e($error, 'luya');
            }
        }
    }

    // Get the post title
    private function get_title(int $post_id) {
        $post_id = intval($post_id);
        $post = get_post($post_id);
        return $post ? $post->post_title : false;
    }

    // Rewrites a title using OpenAI
    private function rewrite_title(string $title) {
        $title = sanitize_text_field($title);

        $instruction = "Generate a single, unique, straightforward, and neutral alternative title for the following news article, while maintaining a news-style tone and accurately representing the content of the article: " . $title;

        $new_title = $this->ai_generator->generate_completion($instruction);

        // Check if the new title contains quotes
        if (strpos($new_title, '"') !== false || strpos($new_title, '"') !== false) {
            // Remove quotes from the new title
            $new_title = str_replace(['"', '"'], '', $new_title);
        }

        $new_title = mb_convert_case($new_title, MB_CASE_TITLE, "UTF-8");

        // Remove trailing period if exists
        $new_title = rtrim($new_title, '.');

        return $new_title;
    }

    // Updates a post with new title
    private function update_title(int $post_id, string $new_title) {
        $post_id = intval($post_id);
        $new_title = sanitize_text_field($new_title);
        $this->update_post($post_id, array('post_title' => wp_strip_all_tags($new_title)));
    }
}