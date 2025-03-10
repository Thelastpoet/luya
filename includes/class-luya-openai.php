<?php

namespace Luya;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class OpenAIGenerator
 * Handles communication with OpenAI API for content generation
 * 
 * @package Luya
 */
class OpenAIGenerator {
    /**
     * Default configuration values
     */
    const DEFAULT_MODEL = 'o3-mini';
    const DEFAULT_MAX_TOKENS = 10000;
    const DEFAULT_TEMPERATURE = 0.7;
    const DEFAULT_TOP_P = 1.0;
    const DEFAULT_FREQUENCY_PENALTY = 0.0;
    const DEFAULT_PRESENCE_PENALTY = 0.0;
    const API_TIMEOUT = 200;
    
    /**
     * Supported chat-based models
     *
     * @var array
     */
    private $chat_models = array(
        'gpt-4o',
        'o3-mini',
        'o1',
        'o1-mini'
    );

    /**
     * Configuration settings
     *
     * @var array
     */
    private $config;

    /**
     * HTTP arguments for API requests
     *
     * @var array
     */
    private $http_args;

    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize_config();
        $this->validate_config();
        $this->setup_http_args();

        // Add error logging
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Initialize configuration from WordPress options
     */
    private function initialize_config() {
        $settings = get_option('luya_settings', array());
        
        $this->config = array(
            'api_key'           => sanitize_text_field($settings['luya-openai-api-key'] ?? ''),
            'model'             => sanitize_text_field($settings['model'] ?? self::DEFAULT_MODEL),
            'max_tokens'        => absint($settings['max_tokens'] ?? self::DEFAULT_MAX_TOKENS),
            'temperature'       => (float) ($settings['temperature'] ?? self::DEFAULT_TEMPERATURE),
            'top_p'            => (float) ($settings['top_p'] ?? self::DEFAULT_TOP_P),
            'frequency_penalty' => (float) ($settings['frequency_penalty'] ?? self::DEFAULT_FREQUENCY_PENALTY),
            'presence_penalty'  => (float) ($settings['presence_penalty'] ?? self::DEFAULT_PRESENCE_PENALTY),
        );
    }

    /**
     * Validate the configuration
     */
    private function validate_config() {
        if (empty($this->config['api_key'])) {
            wp_die(
                esc_html__('OpenAI API key is required. Please configure it in the Luya settings.', 'luya'),
                esc_html__('Configuration Error', 'luya'),
                array('back_link' => true)
            );
        }

        $this->validate_numeric_range('temperature', 0, 1);
        $this->validate_numeric_range('top_p', 0, 1);
        $this->validate_numeric_range('frequency_penalty', -2, 2);
        $this->validate_numeric_range('presence_penalty', -2, 2);
        $this->validate_positive_integer('max_tokens', 1, 32000);
    }

    /**
     * Validate numeric range
     *
     * @param string $field Field name
     * @param float  $min   Minimum value
     * @param float  $max   Maximum value
     */
    private function validate_numeric_range($field, $min, $max) {
        $value = $this->config[$field];
        if (!is_numeric($value) || $value < $min || $value > $max) {
            $message = sprintf(
                /* translators: 1: field name 2: minimum value 3: maximum value */
                __('%1$s must be between %2$f and %3$f', 'luya'),
                $field,
                $min,
                $max
            );
            $this->add_error_notice($message);
        }
    }

    /**
     * Validate positive integer
     *
     * @param string $field Field name
     * @param int    $min   Minimum value
     * @param int    $max   Maximum value
     */
    private function validate_positive_integer($field, $min, $max) {
        $value = $this->config[$field];
        if (!is_int($value) || $value < $min || $value > $max) {
            $message = sprintf(
                /* translators: 1: field name 2: minimum value 3: maximum value */
                __('%1$s must be an integer between %2$d and %3$d', 'luya'),
                $field,
                $min,
                $max
            );
            $this->add_error_notice($message);
        }
    }

    /**
     * Add error notice
     *
     * @param string $message Error message
     */
    private function add_error_notice($message) {
        add_settings_error(
            'luya_settings',
            'luya_error',
            esc_html($message),
            'error'
        );
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        settings_errors('luya_settings');
    }

    /**
     * Setup HTTP arguments for API requests
     */
    private function setup_http_args() {
        $this->http_args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->config['api_key'],
            ),
            'timeout' => self::API_TIMEOUT,
        );
    }

    /**
     * Get API URL for different endpoints
     *
     * @param string $endpoint API endpoint
     * @return string
     */
    private function get_api_url($endpoint) {
        $base_url = 'https://api.openai.com/v1/';
        $endpoints = array(
            'chat'       => $base_url . 'chat/completions',
            'completion' => $base_url . 'completions',
            'edit'      => $base_url . 'edits',
        );

        if (!isset($endpoints[$endpoint])) {
            /* translators: %s: endpoint name */
            wp_die(sprintf(__('Invalid endpoint: %s', 'luya'), esc_html($endpoint)));
        }

        return $endpoints[$endpoint];
    }

    /**
     * Make API request to OpenAI
     *
     * @param string $endpoint API endpoint
     * @param array  $body    Request body
     * @param array  $args    Additional arguments
     * @return string
     */
    private function generate($endpoint, $body, $args = array()) {
        $api_url = $this->get_api_url($endpoint);
        $request_body = $this->prepare_request_body($endpoint, $body, $args);
        
        $response = wp_remote_post(
            $api_url, 
            array_merge(
                $this->http_args, 
                array('body' => wp_json_encode($request_body))
            )
        );

        if (is_wp_error($response)) {
            $this->log_error('API request failed: ' . $response->get_error_message());
            return false;
        }

        return $this->parse_response($response);
    }

    /**
     * Prepare request body for API call
     *
     * @param string $endpoint API endpoint
     * @param array  $body    Request body
     * @param array  $args    Additional arguments
     * @return array
     */
    private function prepare_request_body($endpoint, $body, $args) {
        if ('edit' === $endpoint) {
            return $body;
        }

        $defaults = array(
            'max_tokens'        => $this->config['max_tokens'],
            'model'            => $this->config['model'],
            'temperature'      => $this->config['temperature'],
            'top_p'           => $this->config['top_p'],
            'frequency_penalty' => $this->config['frequency_penalty'],
            'presence_penalty'  => $this->config['presence_penalty'],
            'n'               => 1,
        );

        return array_merge($body, wp_parse_args($args, $defaults));
    }

    /**
     * Parse API response
     *
     * @param array $response WordPress HTTP API response
     * @return string|false
     */
    private function parse_response($response) {
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['error'])) {
            $this->log_error('API Error: ' . $response_body['error']['message']);
            return false;
        }

        if (isset($response_body['choices'][0]['text'])) {
            return $response_body['choices'][0]['text'];
        }
        
        if (isset($response_body['choices'][0]['message']['content'])) {
            return $response_body['choices'][0]['message']['content'];
        }

        $this->log_error('Unexpected API response structure');
        return false;
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        error_log(sprintf('[Luya] %s', $message));
        $this->add_error_notice($message);
    }

    /**
     * Generate completion from prompt
     *
     * @param string $prompt Prompt text
     * @param array  $args   Additional arguments
     * @return string|false
     */
    public function generate_completion($prompt, $args = array()) {
        $prompt = wp_kses_post($prompt);
        
        if (in_array($this->config['model'], $this->chat_models, true)) {
            return $this->generate_chat_completion($prompt, $args);
        }

        return $this->generate('completion', array('prompt' => $prompt), $args);
    }

    /**
     * Generate chat completion
     *
     * @param string $prompt Prompt text
     * @param array  $args   Additional arguments
     * @return string|false
     */
    private function generate_chat_completion($prompt, $args = array()) {
        $messages = array(
            array(
                'role'    => 'system',
                'content' => $this->get_system_prompt()
            ),
            array(
                'role'    => 'user',
                'content' => $prompt
            )
        );

        return $this->generate('chat', array('messages' => $messages), $args);
    }

    /**
     * Get system prompt for chat completion
     *
     * @return string
     */
    private function get_system_prompt() {
        return apply_filters(
            'luya_system_prompt',
            "The assistant is an experienced writer who produces detailed and informative " .
            "(2000+ words) articles about the topic. The assistant uses a human-like writing " .
            "style that is always formal and professional, utilizing active voice, " .
            "personification and varied sentence structures to create an engaging flow and " .
            "pace. The assistant must organize the content using Markdown formatting, " .
            "specifically the CommonMark syntax. Always start the content with '# [Title]' " .
            "where [Title] is a compelling and descriptive title for the article."
        );
    }

    /**
     * Generate summary from draft
     *
     * @param string $draft Draft content
     * @return string|false
     */
    public function generate_summary($draft) {
        $draft = wp_kses_post($draft);
        $instruction = $this->get_summary_instruction();
        
        if (in_array($this->config['model'], $this->chat_models, true)) {
            $messages = array(
                array(
                    'role'    => 'system',
                    'content' => $instruction
                ),
                array(
                    'role'    => 'user',
                    'content' => $draft
                )
            );
            return $this->generate('chat', array('messages' => $messages));
        }

        return $this->generate('completion', array(
            'prompt' => $instruction . " Here's the original article: " . $draft
        ));
    }

    /**
     * Get summary instruction
     *
     * @return string
     */
    private function get_summary_instruction() {
        return apply_filters(
            'luya_summary_instruction',
            "You're a professional editor with the expertise to condense lengthy articles " .
            "into clear, concise summaries. Your task is to extract the key points, main " .
            "arguments, and essential facts from the original content. This summary will " .
            "serve as the foundation for crafting a completely new article. Please ensure " .
            "that the summary is comprehensive enough to capture the essence of the article, " .
            "yet concise enough to serve as an effective guide for writing new content"
        );
    }
}