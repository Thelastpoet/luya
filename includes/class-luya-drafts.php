<?php

namespace Luya;

use Luya\OpenAIGenerator;
use WP_Query;

if ( !defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Luya_Drafts {
    private $ai_generator;

    public function __construct(OpenAIGenerator $ai_generator) {
        $this->ai_generator = $ai_generator;
    }

    // Fetches all draft posts
    public function fetch_drafts() {
        $args = array(
            'post_status' => 'draft',
            'posts_per_page' => -1, // fetch all drafts
        );

        $query = new WP_Query($args);

        return $query->posts;
    }

    // Deletes the content of a post
    public function delete_content(int $post_id) {
        $post_id = intval($post_id);
        $this->update_post($post_id, array('post_content' => ''));
    }

    // Updates a post with new content
    public function update_content(int $post_id, string $new_content) {
        $post_id = intval($post_id);
        $new_content = sanitize_text_field($new_content);
        $formatted_content = $this->format_content($new_content);
        $this->update_post($post_id, array('post_content' => $formatted_content));
    }   

    // Publishes a post
    public function publish_post(int $post_id) {
        $post_id = intval($post_id);
        $current_time = current_time('mysql');
        $this->update_post($post_id, array('post_status' => 'publish', 'post_date' => $current_time, 'post_date_gmt' => get_gmt_from_date($current_time)));
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
    public function get_title(int $post_id) {
        $post_id = intval($post_id);
        $post = get_post($post_id);
        return $post ? $post->post_title : false;
    }

    // Rewrite and update the post title using OpenAI
    public function rewrite_and_update_title(int $post_id) {
        $post_id = intval($post_id);
        $title = $this->get_title($post_id);
        $new_title = $this->rewrite_title($title);
        $this->update_title($post_id, $new_title);
    }

    // Rewrites a title using OpenAI
    public function rewrite_title(string $title) {
        $title = sanitize_text_field($title);
        // Provide an instruction to the AI
        $instruction = "Provide a unique title similar to: " . $title;

        // Generate a new title using the AI
        $new_title = $this->ai_generator->generate_completion($instruction);

        // Return the new title
        return $new_title;
    }

    // Updates a post with new title
    public function update_title(int $post_id, string $new_title) {
        $post_id = intval($post_id);
        $new_title = sanitize_text_field($new_title);
        $this->update_post($post_id, array('post_title' => $new_title));
    }

    // Summarizes a post using OpenAI
    public function summarize_post(int $post_id) {
        $post_id = intval($post_id);
        $post = get_post($post_id);
        if($post) {
            $summary = $this->ai_generator->generate_summary($post->post_content);
            return $summary;
        }
        return false;
    }

    // Edits a post using OpenAI
    public function edit_post(int $post_id) {
        $post_id = intval($post_id);
        $post = get_post($post_id);
        if ($post) {
            // Generate a completion using the post content as the prompt
            $edit = $this->ai_generator->generate_completion($post->post_content);
            return $edit;
        }
        return false;
    }

    public function format_content(string $content) {
        // Split text by full stops
        $sentences = explode('.', $content);
        
        // Remove empty elements
        $sentences = array_filter($sentences);
        
        // Initialize an empty string for the new content
        $new_content = '';
        
        // Split text into paragraphs every 3 sentences
        for ($i = 0; $i < count($sentences); $i+=1) {
            $paragraph = implode('.', array_slice($sentences, $i, 1));
            $new_content .= "<p>{$paragraph}.</p>";
        }
        
        return $new_content;
    }    
}