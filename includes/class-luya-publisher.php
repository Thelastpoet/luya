<?php

namespace Luya;

use Luya\Luya_Drafts;

if ( !defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Luya_Publisher {
    private $drafts;

    public function __construct(Luya_Drafts $drafts) {
        $this->drafts = $drafts;
    }

    public function edit_and_publish_post(int $post_id) {
        // Edit the post with OpenAI
        $edit = $this->drafts->edit_post($post_id);
        if($edit) {
            // If the edit was successful, update the content
            $this->drafts->update_content($post_id, $edit);
            // And then publish the post
            $this->drafts->publish_post($post_id);
            return true;
        }
        return false;
    }
}