<?php

namespace Luya;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class OpenAIGenerator {
    private $api_key;
    private $model;
    private $max_tokens;
    private $temperature;
    private $top_p;
    private $frequency_penalty;
    private $presence_penalty;
    private $http_args;

    public function __construct() {
        $settings = get_option('luya_settings', array());

        $this->api_key = isset($settings['luya-openai-api-key']) ? $settings['luya-openai-api-key'] : '';
        $this->model = isset($settings['model']) ? $settings['model'] : 'text-davinci-003';
        $this->max_tokens = isset($settings['max_tokens']) ? (int) $settings['max_tokens'] : 5000;
        $this->temperature = isset($settings['temperature']) ? (float) $settings['temperature'] : 0.7;
        $this->top_p = isset($settings['top_p']) ? (float) $settings['top_p'] : 1.0;
        $this->frequency_penalty = isset($settings['frequency_penalty']) ? (float) $settings['frequency_penalty'] : 0.0;
        $this->presence_penalty = isset($settings['presence_penalty']) ? (float) $settings['presence_penalty'] : 0.0;

        $this->http_args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'timeout' => 200,
        );
    }

    private function get_api_url($endpoint) {
        $base_url = 'https://api.openai.com/v1/';
        $endpoints = array(
            'chat' => $base_url . 'chat/completions',
            'completion' => $base_url . 'completions',
            'edit' => $base_url . 'edits',
        );
        return $endpoints[$endpoint];
    }

    private function generate($endpoint, $body, $args = array()) {
        $api_url = $this->get_api_url($endpoint);
        
        if ($endpoint !== 'edit') {
            $defaults = array(
                'max_tokens' => $this->max_tokens,
                'model' => $this->model,
                'temperature' => $this->temperature,
                'top_p' => $this->top_p,
                'frequency_penalty' => $this->frequency_penalty,
                'presence_penalty' => $this->presence_penalty,
                'n' => 1,
            );

            $args = wp_parse_args($args, $defaults);

            $request_body = array_merge($body, $args);
        } else {
            $request_body = $body;
        }

        $response = wp_remote_post($api_url, array_merge($this->http_args, array('body' => wp_json_encode($request_body))));

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
           
        if (isset($response_body['choices'][0]['text'])) {
            return $response_body['choices'][0]['text'];
        } elseif (isset($response_body['choices'][0]['message']['content'])) {
            return $response_body['choices'][0]['message']['content'];
        }

        throw new \Exception('Unexpected API response');
    }
   
    public function generate_completion($prompt, $args = array()) {
        if ($this->model == 'gpt-4' || $this->model == 'gpt-3.5-turbo' || $this->model == 'gpt-4-32k' || $this->model == 'gpt-3.5-turbo-16k') {
            // Chat Model
            $messages = array(
                array(
                    "role" => "system", 
                    "content" => "The Assistant is a skilled news journalist. Your task is to craft engaging and SEO-optimized news articles, capturing the essence of events with a unique editorial voice. Incorporate relevant quotes and ensure the content is structured coherently using CommonMark Markdown. Accuracy, clarity, and conciseness are paramount."
                ),
                array("role" => "user", "content" => $prompt)
            );
            $body = array('messages' => $messages);
            return $this->generate('chat', $body, $args);
        } else {
            // Traditional model: use prompt
            return $this->generate('completion', array('prompt' => $prompt), $args);
        }
    }

    public function generate_summary($draft) {
        // Create a standard instruction
        $instruction = "You're a professional editor with the expertise to condense lengthy news articles into clear, concise summaries. Please preserve any statistics, names, and key facts from the original content.";

        if ($this->model == 'gpt-4' || $this->model == 'gpt-3.5-turbo' || $this->model == 'gpt-4-32k' || $this->model == 'gpt-3.5-turbo-16k') {
            // Chat Model
            $messages = array(
                array(
                    "role" => "system", 
                    "content" => $instruction
                ),
                array("role" => "user", "content" => $draft)
            );
            $body = array('messages' => $messages);
            return $this->generate('chat', $body);
        } else {
            // Traditional model: use draft as prompt
            $prompt = $instruction . " Here's the original article: " . $draft;
            return $this->generate('completion', array('prompt' => $prompt));
        }
    }
}