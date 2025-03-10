<?php

namespace Luya;

use WP_Post;
use WP_Error;
use WP_Query;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles draft post processing and AI content generation
 */
class Luya_Drafts {
    /**
     * OpenAI generator instance
     *
     * @var OpenAIGenerator
     */
    private $ai_generator;

    /**
     * Constructor
     *
     * @param OpenAIGenerator $ai_generator OpenAI generator instance
     */
    public function __construct(OpenAIGenerator $ai_generator) {
        $this->ai_generator = $ai_generator;
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Fetches pending posts from selected categories
     *
     * @return WP_Post|null Post object or null if none found
     */
    public function luya_fetch_posts() {
        $settings = get_option('luya_settings');
        $categories = isset($settings['luya-categories']) ? array_map('absint', $settings['luya-categories']) : array();
    
        if (empty($categories)) {
            $this->log_error(__('No categories selected in Luya settings', 'luya'));
            return null;
        }
    
        $args = array(
            'post_status' => 'pending',
            'posts_per_page' => 1,
            'category__in' => $categories,
            'orderby' => 'date',
            'order' => 'ASC',
        );
    
        $query = new WP_Query($args);
        return $query->posts ? $query->posts[0] : null;
    }

    /**
     * Process draft posts with AI
     *
     * @param WP_Post $draft Draft post to process
     * @return bool Success status
     */
    public function process_drafts($draft) {
        if (!$draft instanceof WP_Post) {
            $this->log_error(__('Invalid draft post provided', 'luya'));
            return false;
        }

        try {
            $summary = $this->summarize_post($draft->ID);
            if (!$summary) {
                throw new \Exception(__('Failed to generate summary', 'luya'));
            }

            $full_content = $this->write_content($summary);
            if (!isset($full_content['title']) || !isset($full_content['content'])) {
                throw new \Exception(__('Failed to generate content', 'luya'));
            }

            $formatted_content = $this->format_content($full_content['content']);
            if (!$formatted_content) {
                throw new \Exception(__('Failed to format content', 'luya'));
            }

            if (!$this->update_post($draft->ID, $full_content['title'], $formatted_content)) {
                throw new \Exception(__('Failed to update post', 'luya'));
            }

            return $this->publish_post($draft->ID);

        } catch (\Exception $e) {
            $this->log_error($e->getMessage());
            return false;
        }
    }

    /**
     * Summarize post content using AI
     *
     * @param int $post_id Post ID
     * @return string|false Summary text or false on failure
     */
    public function summarize_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            $this->log_error(sprintf(__('Post ID %d not found', 'luya'), $post_id));
            return false;
        }

        $summary = $this->ai_generator->generate_summary($post->post_content);
        return wp_kses_post($summary);
    }

    /**
     * Generate new content from summary
     *
     * @param string $summary Content summary
     * @return array{title: string, content: string}|false
     */
    $prompt = "Based on this summary, create a complete article with a clear title. " .
              "Start the article with '# [Title]' as the first line, where [Title] is your proposed title. " .
              "The rest of the content should be well-structured using Markdown headings, paragraphs, and lists. " .
              "Here's the summary: " . $summary;
    
    // Use the existing generate_completion method
    $content = $this->ai_generator->generate_completion($prompt);
    if (!$content) {
        return false;
    }

    // Extract title from first H1 with improved regex pattern
    preg_match('/^\s*#\s+(.+?)(?:\s*\n|$)/m', $content, $matches);
    if (empty($matches[1])) {
        $this->log_error(__('No title found in generated content', 'luya'));
        return false;
    }

    $title = trim(preg_replace('/^\*+|\*+$/', '', $matches[1]));
    
    // Remove the title line from content
    $content = preg_replace('/^\s*#\s+.+?(?:\s*\n|$)/m', '', $content, 1);
    
    return array(
        'title' => sanitize_text_field($title),
        'content' => wp_kses_post($content)
    );

    /**
     * Format Markdown content to HTML
     *
     * @param string $content Markdown content
     * @return string|false HTML content or false on failure
     */
    private function format_content($content) {
        try {
            $environment = new Environment();
            $environment->addExtension(new CommonMarkCoreExtension());
            $converter = new MarkdownConverter($environment);
            
            $formatted_content = $converter->convertToHtml($content);
            return wp_kses_post((string) $formatted_content);

        } catch (\Exception $e) {
            $this->log_error(sprintf(
                __('Markdown conversion failed: %s', 'luya'),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Update post with new content
     *
     * @param int    $post_id Post ID
     * @param string $title   Post title
     * @param string $content Post content
     * @param array  $args    Additional arguments
     * @return bool Success status
     */
    private function update_post($post_id, $title, $content, $args = array()) {
        $post_data = wp_parse_args($args, array(
            'ID' => absint($post_id),
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_title' => $title,
            'post_content' => $content,
        ));
    
        $result = wp_update_post($post_data, true);
        
        if (is_wp_error($result)) {
            $this->log_error(sprintf(
                __('Failed to update post: %s', 'luya'),
                $result->get_error_message()
            ));
            return false;
        }
        
        return true;
    }

    /**
     * Publish post
     *
     * @param int $post_id Post ID
     * @return bool Success status
     */
    private function publish_post($post_id) {
        $result = wp_update_post(array(
            'ID' => absint($post_id),
            'post_status' => 'publish'
        ), true);
        
        if (is_wp_error($result)) {
            $this->log_error(sprintf(
                __('Failed to publish post: %s', 'luya'),
                $result->get_error_message()
            ));
            return false;
        }
        
        return true;
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        error_log(sprintf('[Luya] %s', $message));
        add_settings_error(
            'luya_drafts',
            'luya_error',
            esc_html($message),
            'error'
        );
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        settings_errors('luya_drafts');
    }
}