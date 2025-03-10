<?php
namespace Luya;

if (!defined('ABSPATH')) {
    exit;
}

class Luya_Settings {
    const OPTION_NAME = 'luya_settings';
    const MENU_SLUG = 'luya-settings';
    const CAPABILITY = 'manage_options';
    const ICON_URL = 'dashicons-welcome-write-blog';

    private $models = array(
        'gpt-4o' => 'GPT-4o',
        'o3-mini' => 'o3-mini',
        'o1' => 'o1',
        'o1-mini' => 'o1-mini'
    );

    public function __construct() {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'initialize_settings'));
            add_action('admin_notices', array($this, 'display_admin_notices'));
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Luya Settings', 'luya'),
            __('Luya', 'luya'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array($this, 'render_settings_page'),
            self::ICON_URL
        );
    }

    public function initialize_settings() {
        register_setting(
            self::OPTION_NAME,
            self::OPTION_NAME,
            array($this, 'sanitize_settings')
        );

        $this->add_settings_sections();
        $this->add_settings_fields();
    }

    private function add_settings_sections() {
        $sections = array(
            'api_settings' => __('API Configuration', 'luya'),
            'content_settings' => __('Content Generation', 'luya'),
            'post_settings' => __('Post Processing', 'luya'),
            'schedule_settings' => __('Schedule Settings', 'luya')
        );

        foreach ($sections as $id => $title) {
            add_settings_section(
                $id,
                $title,
                array($this, "render_{$id}_description"),
                self::MENU_SLUG
            );
        }
    }

    private function get_fields_config() {
        return array(
            // API Configuration
            'api_key' => array(
                'label' => __('OpenAI API Key', 'luya'),
                'type' => 'text',
                'section' => 'api_settings',
                'sanitize' => 'sanitize_text_field'
            ),
            'api_timeout' => array(
                'label' => __('API Timeout (seconds)', 'luya'),
                'type' => 'number',
                'section' => 'api_settings',
                'default' => 200,
                'sanitize' => 'absint'
            ),

            // Content Generation
            'model' => array(
                'label' => __('OpenAI Model', 'luya'),
                'type' => 'select',
                'section' => 'content_settings',
                'options' => $this->models,
                'default' => 'gpt-3.5-turbo'
            ),
            'max_tokens' => array(
                'label' => __('Maximum Tokens', 'luya'),
                'type' => 'number',
                'section' => 'content_settings',
                'default' => 5000,
                'sanitize' => 'absint'
            ),
            'temperature' => array(
                'label' => __('Temperature', 'luya'),
                'type' => 'number',
                'section' => 'content_settings',
                'default' => 0.7,
                'attrs' => array(
                    'step' => '0.1',
                    'min' => '0',
                    'max' => '1'
                )
            ),
            'top_p' => array(
                'label' => __('Top P', 'luya'),
                'type' => 'number',
                'section' => 'content_settings',
                'default' => 1.0,
                'attrs' => array(
                    'step' => '0.1',
                    'min' => '0',
                    'max' => '1'
                )
            ),
            'frequency_penalty' => array(
                'label' => __('Frequency Penalty', 'luya'),
                'type' => 'number',
                'section' => 'content_settings',
                'default' => 0.0,
                'attrs' => array(
                    'step' => '0.1',
                    'min' => '-2',
                    'max' => '2'
                )
            ),
            'presence_penalty' => array(
                'label' => __('Presence Penalty', 'luya'),
                'type' => 'number',
                'section' => 'content_settings',
                'default' => 0.0,
                'attrs' => array(
                    'step' => '0.1',
                    'min' => '-2',
                    'max' => '2'
                )
            ),
            'system_prompt' => array(
                'label' => __('System Prompt', 'luya'),
                'type' => 'textarea',
                'section' => 'content_settings',
                'default' => $this->get_default_system_prompt(),
                'sanitize' => 'wp_kses_post'
            ),
            'summary_prompt' => array(
                'label' => __('Summary Prompt', 'luya'),
                'type' => 'textarea',
                'section' => 'content_settings',
                'default' => $this->get_default_summary_prompt(),
                'sanitize' => 'wp_kses_post'
            ),

            // Post Processing
            'categories' => array(
                'label' => __('Categories', 'luya'),
                'type' => 'categories',
                'section' => 'post_settings',
                'sanitize' => array($this, 'sanitize_categories')
            ),
            'posts_per_batch' => array(
                'label' => __('Posts Per Batch', 'luya'),
                'type' => 'number',
                'section' => 'post_settings',
                'default' => 1,
                'attrs' => array(
                    'min' => '1',
                    'max' => '10'
                ),
                'sanitize' => 'absint'
            ),
            'post_status_flow' => array(
                'label' => __('Post Status Flow', 'luya'),
                'type' => 'select',
                'section' => 'post_settings',
                'options' => array(
                    'draft_to_publish' => __('Draft → Publish', 'luya'),
                    'draft_to_pending' => __('Draft → Pending → Publish', 'luya')
                ),
                'default' => 'draft_to_pending'
            ),
            'post_ordering' => array(
                'label' => __('Post Processing Order', 'luya'),
                'type' => 'select',
                'section' => 'post_settings',
                'options' => array(
                    'date_asc' => __('Oldest First', 'luya'),
                    'date_desc' => __('Newest First', 'luya')
                ),
                'default' => 'date_asc'
            ),

            // Schedule Settings
            'cron_interval' => array(
                'label' => __('Processing Interval (minutes)', 'luya'),
                'type' => 'number',
                'section' => 'schedule_settings',
                'default' => 5,
                'attrs' => array(
                    'min' => '1',
                    'max' => '60'
                ),
                'sanitize' => 'absint'
            ),
            'lock_timeout' => array(
                'label' => __('Lock Timeout (minutes)', 'luya'),
                'type' => 'number',
                'section' => 'schedule_settings',
                'default' => 15,
                'attrs' => array(
                    'min' => '5',
                    'max' => '60'
                ),
                'sanitize' => 'absint'
            )
        );
    }

    private function add_settings_fields() {
        $fields = $this->get_fields_config();

        foreach ($fields as $field_id => $field) {
            add_settings_field(
                $field_id,
                $field['label'],
                array($this, 'render_field'),
                self::MENU_SLUG,
                $field['section'],
                array_merge($field, array('field_id' => $field_id))
            );
        }
    }

    public function render_field($args) {
        $field_id = $args['field_id'];
        $type = $args['type'];
        $options = get_option(self::OPTION_NAME, array());
        $value = isset($options[$field_id]) ? $options[$field_id] : $args['default'];
        $attrs = isset($args['attrs']) ? $args['attrs'] : array();

        switch ($type) {
            case 'textarea':
                $this->render_textarea($field_id, $value);
                break;
            case 'select':
                $this->render_select($field_id, $value, $args['options']);
                break;
            case 'categories':
                $this->render_categories($field_id, $value);
                break;
            case 'number':
                $this->render_number($field_id, $value, $attrs);
                break;
            default:
                $this->render_text($field_id, $value, $attrs);
        }
    }

    private function render_textarea($field_id, $value) {
        printf(
            '<textarea name="%s[%s]" class="large-text" rows="5">%s</textarea>',
            esc_attr(self::OPTION_NAME),
            esc_attr($field_id),
            esc_textarea($value)
        );
    }

    private function render_select($field_id, $value, $options) {
        printf(
            '<select name="%s[%s]" class="regular-text">',
            esc_attr(self::OPTION_NAME),
            esc_attr($field_id)
        );
        
        foreach ($options as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($key, $value, false),
                esc_html($label)
            );
        }
        
        echo '</select>';
    }

    private function render_categories($field_id, $value) {
        $categories = get_categories(array('hide_empty' => false));
        
        printf(
            '<select name="%s[%s][]" multiple class="regular-text">',
            esc_attr(self::OPTION_NAME),
            esc_attr($field_id)
        );
        
        foreach ($categories as $category) {
            printf(
                '<option value="%d" %s>%s</option>',
                $category->term_id,
                selected(in_array($category->term_id, (array)$value), true, false),
                esc_html($category->name)
            );
        }
        
        echo '</select>';
    }

    private function render_number($field_id, $value, $attrs) {
        $attr_string = '';
        foreach ($attrs as $key => $val) {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($val));
        }
        
        printf(
            '<input type="number" name="%s[%s]" value="%s" class="regular-text"%s />',
            esc_attr(self::OPTION_NAME),
            esc_attr($field_id),
            esc_attr($value),
            $attr_string
        );
    }

    private function render_text($field_id, $value, $attrs) {
        $attr_string = '';
        foreach ($attrs as $key => $val) {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($val));
        }
        
        printf(
            '<input type="text" name="%s[%s]" value="%s" class="regular-text"%s />',
            esc_attr(self::OPTION_NAME),
            esc_attr($field_id),
            esc_attr($value),
            $attr_string
        );
    }

    public function render_settings_page() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'luya'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_NAME);
                do_settings_sections(self::MENU_SLUG);
                submit_button(__('Save Settings', 'luya'));
                ?>
            </form>
        </div>
        <?php
    }

    public function sanitize_settings($input) {
        $fields = $this->get_fields_config();
        $sanitized = array();

        foreach ($fields as $field_id => $field) {
            if (!isset($input[$field_id])) {
                continue;
            }

            if (isset($field['sanitize'])) {
                if (is_callable($field['sanitize'])) {
                    $sanitized[$field_id] = call_user_func($field['sanitize'], $input[$field_id]);
                } else {
                    $sanitized[$field_id] = $input[$field_id];
                }
            } else {
                $sanitized[$field_id] = sanitize_text_field($input[$field_id]);
            }
        }

        return $sanitized;
    }

    private function get_default_system_prompt() {
        return __(
            "The assistant is an experienced writer who produces detailed and informative " .
            "(2000+ words) articles about the topic. The assistant uses a human-like writing " .
            "style that is always formal and professional, utilizing active voice, " .
            "personification and varied sentence structures to create an engaging flow and " .
            "pace. The assistant must organize the content using Markdown formatting, " .
            "specifically the CommonMark syntax.",
            'luya'
        );
    }

    private function get_default_summary_prompt() {
        return __(
            "You're a professional editor with the expertise to condense lengthy articles " .
            "into clear, concise summaries. Your task is to extract the key points, main " .
            "arguments, and essential facts from the original content. This summary will " .
            "serve as the foundation for crafting a completely new article. Please ensure " .
            "that the summary is comprehensive enough to capture the essence of the article, " .
            "yet concise enough to serve as an effective guide for writing new content.",
            'luya'
        );
    }

    public function render_api_settings_description() {
        echo '<p>' . esc_html__('Configure your OpenAI API settings and timeout values.', 'luya') . '</p>';
    }

    public function render_content_settings_description() {
        echo '<p>' . esc_html__('Adjust content generation parameters and system prompts.', 'luya') . '</p>';
    }

    public function render_post_settings_description() {
        echo '<p>' . esc_html__('Configure how posts are processed and published.', 'luya') . '</p>';
    }

    public function render_schedule_settings_description() {
        echo '<p>' . esc_html__('Set up automated processing schedules and timeouts.', 'luya') . '</p>';
    }

    public function sanitize_categories($categories) {
        if (!is_array($categories)) {
            return array();
        }
        return array_map('absint', $categories);
    }

    public function display_admin_notices() {
        settings_errors(self::OPTION_NAME);
    }
}