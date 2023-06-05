<?php

namespace Luya;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Luya_Settings {

    private $option_name = 'luya_settings';
    private $page_title = 'Luya Settings';
    private $menu_title = 'Luya';
    private $capability = 'manage_options';
    private $menu_slug = 'luya-settings';
    private $settings = array();
    private $icon_url = 'dashicons-welcome-write-blog';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_options_page'));
        add_action('admin_init', array($this, 'initialize_settings'));
    }

    public function add_options_page() {
        add_menu_page($this->page_title, $this->menu_title, $this->capability, $this->menu_slug, array($this, 'display_settings_page'), $this->icon_url);
    }

    public function display_settings_page() { ?>
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
        add_settings_section('openai_settings', 'OpenAI Settings', null, $this->menu_slug);
        
        $fields = array(
            'luya-openai-api-key' => 'OpenAI API Key',
            'model' => 'OpenAI Model',
            'max_tokens' => 'Maximum Tokens',
            'temperature' => 'Temperature',
            'top_p' => 'Top P',
            'frequency_penalty' => 'Frequency Penalty',
            'presence_penalty' => 'Presence Penalty'
        );

        foreach ($fields as $field => $label) {
            add_settings_field($field, $label, array($this, 'display_field_callback'), $this->menu_slug, in_array($field, ['luya-openai-api-key']) ? 'api_key_section' : 'openai_settings', array('field' => $field));
        }
    }

    public function display_field_callback($args) {
        $field = $args['field'];
        $option = get_option($this->option_name);
        $value = isset($option[$field]) ? $option[$field] : '';

        if ($field === 'model') {
            $models = array(
                'gpt-4' => 'GPT-4',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                'text-davinci-003' => 'Davinci'
            );
            ?>
            <select name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($field); ?>]">
                <?php foreach ($models as $model_id => $label): ?>
                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($model_id, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php
        } else {
            ?>
            <input type="text" name="<?php echo $this->option_name; ?>[<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($value); ?>" />
            <?php
        }
    }
}