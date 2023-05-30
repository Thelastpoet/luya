<?php

if ( !defined( 'ABSPATH' )) {
    exit; // Exit if accessed directly
}

class Luya_Settings {
    private $option_name = 'luya_settings';
    private $page_title = 'Luya Settings';
    private $menu_title = 'Luya';
    private $capability = 'manage_options';
    private $menu_slug = 'luya-settings';
    private $settings = array();

    public function __construct() {
        add_action('admin_menu', array($this, 'add_options_page'));
        add_action('admin_init', array($this, 'initialize_settings'));
    }

    public function add_options_page() {
        add_options_page($this->page_title, $this->menu_title, $this->capability, $this->menu_slug, array($this, 'display_settings_page'));
    }

    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_name);
                do_settings_sections($this->menu_slug);
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    public function initialize_settings() {
        register_setting($this->option_name, $this->option_name);
    
        add_settings_section('api_key_section', 'API Key', null, $this->menu_slug);
        add_settings_field('luya-openai-api-key', 'OpenAI API Key', array($this, 'display_api_key_field'), $this->menu_slug, 'api_key_section');
    
        add_settings_section('openai_settings', 'OpenAI Settings', null, $this->menu_slug);
        add_settings_field('model', 'OpenAI Model', array($this, 'display_model_field'), $this->menu_slug, 'openai_settings');
        add_settings_field('max_tokens', 'Maximum Tokens', array($this, 'display_max_tokens_field'), $this->menu_slug, 'openai_settings');
        add_settings_field('temperature', 'Temperature', array($this, 'display_temperature_field'), $this->menu_slug, 'openai_settings');
        add_settings_field('top_p', 'Top P', array($this, 'display_top_p_field'), $this->menu_slug, 'openai_settings');
        add_settings_field('frequency_penalty', 'Frequency Penalty', array($this, 'display_frequency_penalty_field'), $this->menu_slug, 'openai_settings');
        add_settings_field('presence_penalty', 'Presence Penalty', array($this, 'display_presence_penalty_field'), $this->menu_slug, 'openai_settings');
    }   

    public function display_api_key_field() {
        $option = get_option($this->option_name);
        $api_key = isset($option['luya-openai-api-key']) ? $option['luya-openai-api-key'] : '';
        ?>
        <input type="text" name="<?php echo $this->option_name; ?>[luya-openai-api-key]" value="<?php echo esc_attr($api_key); ?>" />
        <?php
    }

    public function display_model_field() {
        $option = get_option($this->option_name);
        $model = isset($option['model']) ? $option['model'] : 'text-davinci-003';
    
        $models = array(
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'text-davinci-003' => 'Davinci',
        );
        ?>
        <select name="<?php echo $this->option_name; ?>[model]">
            <?php foreach ($models as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($model, $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }    
    
    public function display_max_tokens_field() {
        $option = get_option($this->option_name);
        $max_tokens = isset($option['max_tokens']) ? (int) $option['max_tokens'] : 37;
        ?>
        <input type="number" name="<?php echo $this->option_name; ?>[max_tokens]" value="<?php echo esc_attr($max_tokens); ?>" />
        <?php
    }
    
    public function display_temperature_field() {
        $option = get_option($this->option_name);
        $temperature = isset($option['temperature']) ? (float) $option['temperature'] : 0.7;
        ?>
        <input type="number" step="0.1" name="<?php echo $this->option_name; ?>[temperature]" value="<?php echo esc_attr($temperature); ?>" />
        <?php
    }
    
    public function display_top_p_field() {
        $option = get_option($this->option_name);
        $top_p = isset($option['top_p']) ? (float) $option['top_p'] : 1.0;
        ?>
        <input type="number" step="0.1" name="<?php echo $this->option_name; ?>[top_p]" value="<?php echo esc_attr($top_p); ?>" />
        <?php
    }
    
    public function display_frequency_penalty_field() {
        $option = get_option($this->option_name);
        $frequency_penalty = isset($option['frequency_penalty']) ? (float) $option['frequency_penalty'] : 0.0;
        ?>
        <input type="number" step="0.1" name="<?php echo $this->option_name; ?>[frequency_penalty]" value="<?php echo esc_attr($frequency_penalty); ?>" />
        <?php
    }
    
    public function display_presence_penalty_field() {
        $option = get_option($this->option_name);
        $presence_penalty = isset($option['presence_penalty']) ? (float) $option['presence_penalty'] : 0.0;
        ?>
        <input type="number" step="0.1" name="<?php echo $this->option_name; ?>[presence_penalty]" value="<?php echo esc_attr($presence_penalty); ?>" />
        <?php
    }
}