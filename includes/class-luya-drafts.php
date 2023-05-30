<?php

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
    public function delete_content($post_id) {
        $this->update_post($post_id, array('post_content' => ''));
    }

    // Updates a post with new content
    public function update_content($post_id, $new_content) {
        $this->update_post($post_id, array('post_content' => $new_content));
    }

    // Publishes a post
    public function publish_post($post_id) {
        $current_time = current_time('mysql');
        $this->update_post($post_id, array('post_status' => 'publish', 'post_date' => $current_time, 'post_date_gmt' => get_gmt_from_date($current_time)));
    }

    // Updates post data using wp_update_post
    private function update_post($post_id, $data) {
        $data['ID'] = $post_id;
        wp_update_post($data);
    }

    // Get the post title
    public function get_title($post_id) {
        $post = get_post($post_id);
        return $post->post_title;
    }

    // Rewrite and update the post title using OpenAI
    public function rewrite_and_update_title($post_id) {
        $title = $this->get_title($post_id);
        error_log("Rewriting title for post $post_id: $title");
        $new_title = $this->rewrite_title($title);
        error_log("New title: $new_title");
        $this->update_title($post_id, $new_title);
    }

    // Rewrites a title using OpenAI
    public function rewrite_title($title) {
        // Provide an instruction to the AI
        $instruction = "Provide a unique title similar to: " . $title;

        // Generate a new title using the AI
        $new_title = $this->ai_generator->generate_completion($instruction);

        // Return the new title
        return $new_title;
    }

    // Updates a post with new title
    public function update_title($post_id, $new_title) {
        $this->update_post($post_id, array('post_title' => $new_title));
    }

    // Summarizes a post using OpenAI
    public function summarize_post($post_id) {
        $post = get_post($post_id);
        if($post) {
            $summary = $this->ai_generator->generate_summary($post->post_content);
            return $summary;
        }
        return false;
    }

    // Edits a post using OpenAI
    public function edit_post($post_id) {
        $post = get_post($post_id);
        if ($post) {
            // Generate a completion using the post content as the prompt
            $edit = $this->ai_generator->generate_completion($post->post_content);
            return $edit;
        }
        return false;
    }
}