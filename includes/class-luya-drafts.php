<?php

namespace Luya;

use Luya\OpenAIGenerator;
use WP_Query;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;


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
        $settings = get_option('luya_settings');
        $categories = isset($settings['luya-categories']) ? $settings['luya-categories'] : array();
    
        $args = array(
            'post_status' => 'pending',
            'posts_per_page' => 1,
            'category__in' => $categories,
        );
    
        $query = new WP_Query($args);
    
        return $query->posts ? $query->posts[0] : null;
    }    

    // Process the drafts
    public function process_drafts($draft) {
        $current_user_id = $draft->post_author;

        $summary = $this->summarize_post($draft->ID);
       
        // Write the content using AI from the Summary
        $full_content = $this->write_content($summary);

        //Separate the title from content
        $title = $full_content['title'];
        $content = $full_content['content'];

        // Format the content
        $formatted_content = $this->format_content($content);

        // Update the post content
        $this->update_post($draft->ID, $title, $formatted_content, []);

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

    // write new content from AI API
    public function write_content($summary) {
        $content = $this->ai_generator->generate_completion($summary);

        // Extract the title from the first level 1 heading
        preg_match('/^#\s+(.*)$/m', $content, $matches);
        $title = $matches[1];

        if (strpos($title, '*') !== false) {
            $title = preg_replace('/^\*+|\*+$/', '', $title);
        }

        $content = preg_replace('/^#\s+.*$\n/m', '', $content);
        
        return array('title' => $title, 'content' => $content);
    }

    private function format_content($content) {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());

        $converter = new MarkdownConverter($environment);

        $formatted_content = $converter->convertToHtml($content);

        if (is_object($formatted_content)) {
            $formatted_content = (string) $formatted_content;
        }

        return $formatted_content;
    }    

    private function update_post($post_id, $title, $content, $args = []) {
        $post_data = array(
            'ID' => $post_id,
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_title' => $title,
            'post_content' => $content,
        );
    
        wp_update_post($post_data);
    }    

    private function publish_post($post_id) {
        $post_data = array(
            'ID' => $post_id,
            'post_status' => 'publish'
        );
    
        wp_update_post($post_data);
    }    

}