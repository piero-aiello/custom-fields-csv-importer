<?php

class CFCI_Processor {
    public static function process_batch($batch, &$updated, &$not_found) {
        foreach ($batch as $item) {
            $post_id = $item['id'];
            $meta_key = $item['meta_key'];
            $meta_value = $item['meta_value'];
            $allowed_post_types = $item['allowed_post_types'];

            $post = get_post($post_id);

            if (!$post) {
                $not_found[] = "Post ID {$post_id} not found.";
                continue;
            }

            if (!in_array($post->post_type, $allowed_post_types, true)) {
                $not_found[] = "Post ID {$post_id} skipped (post_type '{$post->post_type}' not allowed).";
                continue;
            }

            update_post_meta($post_id, $meta_key, $meta_value);

            $updated[] = "ID {$post_id} - {$meta_key} = {$meta_value}";
        }
    }
}
