<?php

class CFCI_CSVHandler {
    public function handle_upload($file_path, array $allowed_post_types) {
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (! $wp_filesystem->exists($file_path)) {
            throw new Exception(esc_html__('The file cannot be read.', 'custom-fields-csv-importer'));
        }

        $content = $wp_filesystem->get_contents($file_path);
        if ($content === false) {
            throw new Exception(esc_html__('Unable to open the CSV file.', 'custom-fields-csv-importer'));
        }

        $lines = explode(PHP_EOL, $content);
        $batch = [];
        $batch_size = 100;
        $updated = [];
        $not_found = [];

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query('START TRANSACTION');


        foreach ($lines as $index => $line) {
            if (trim($line) === '') continue;

            $row = str_getcsv($line);
            if ($index === 0) {
                if (count($row) < 3) {
                    throw new Exception(esc_html__('Invalid CSV format. It must contain at least 3 columns: ID, meta_key, meta_value.', 'custom-fields-csv-importer'));
                }
                continue;
            }

            $row = array_map('trim', $row);
            list($id, $meta_key, $meta_value) = array_pad($row, 3, '');

            if (!ctype_digit($id)) {
                $not_found[] = esc_html(sprintf(
                    /* translators: %s is the invalid ID */
                    __('Invalid ID: %s', 'custom-fields-csv-importer'), $id));
                continue;
            }

            $batch[] = [
                'id' => (int) $id,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value,
                'allowed_post_types' => $allowed_post_types
            ];

            if (count($batch) >= $batch_size) {
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                CFCI_Processor::process_batch($batch, $updated, $not_found);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            CFCI_Processor::process_batch($batch, $updated, $not_found);
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query('COMMIT');

        $this->render_result($updated, $not_found);
    }

    public function render_preview($file_path, array $allowed_post_types) {
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (! $wp_filesystem->exists($file_path)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Preview failed. The file could not be read.', 'custom-fields-csv-importer') . '</p></div>';
            return;
        }

        $content = $wp_filesystem->get_contents($file_path);
        if ($content === false) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Unable to open the CSV file.', 'custom-fields-csv-importer') . '</p></div>';
            return;
        }

        $lines = explode(PHP_EOL, $content);
        $rows = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') continue;

            $row = str_getcsv($line);
            if ($index === 0) {
                if (count($row) < 3) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('CSV must have 3 columns: ID, meta_key, meta_value.', 'custom-fields-csv-importer') . '</p></div>';
                    return;
                }
                continue;
            }

            $rows[] = array_slice($row, 0, 3);
        }

        $show_all = !empty($_POST['cfci_preview_all']) && check_admin_referer('cfci_import_action', 'cfci_import_nonce');

       
        /* translators: %s is the number of rows or the text "first 10" */
        $translated_text = esc_html__('Preview (%s rows)', 'custom-fields-csv-importer');
        $preview_title = sprintf($translated_text, esc_html($show_all ? count($rows) : 'first 10'));
        echo '<h2>' . esc_html($preview_title) . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>ID</th><th>Post Type</th><th>Meta Key</th><th>Meta Value</th></tr></thead><tbody>';

        $rows_shown = 0;
        foreach ($rows as $row) {
            $post_id = (int) $row[0];
            $post_type = '&mdash;';
            if ($post_id) {
                $post = get_post($post_id);
                if ($post) {
                    $post_type = esc_html($post->post_type);
                }
            }

            echo '<tr><td>' . esc_html($row[0]) . '</td><td>' . esc_html($post_type) . '</td><td>' . esc_html($row[1]) . '</td><td>' . esc_html($row[2]) . '</td></tr>';

            $rows_shown++;
            if (!$show_all && $rows_shown >= 10) break;
        }

        echo '</tbody></table>';

        if (!$show_all && count($rows) > 10) {
            echo '<form method="post" style="margin-top: 1em;">';
            wp_nonce_field('cfci_import_action', 'cfci_import_nonce');
            echo '<input type="hidden" name="cfci_action" value="preview">';
            echo '<input type="hidden" name="csv_file_path" value="' . esc_attr($file_path) . '">';
            foreach ($allowed_post_types as $pt) {
                echo '<input type="hidden" name="cfci_post_type[]" value="' . esc_attr($pt) . '">';
            }
            echo '<input type="hidden" name="cfci_preview_all" value="1">';
            echo '<p><button type="submit" class="button">' . esc_html__('View All Rows', 'custom-fields-csv-importer') . '</button></p>';
            echo '</form>';
        }

        echo '<form method="post" style="margin-top: 2em;">';
        wp_nonce_field('cfci_import_action', 'cfci_import_nonce');
        echo '<input type="hidden" name="cfci_action" value="import">';
        echo '<input type="hidden" name="csv_file_path" value="' . esc_attr($file_path) . '">';
        foreach ($allowed_post_types as $pt) {
            echo '<input type="hidden" name="cfci_post_type[]" value="' . esc_attr($pt) . '">';
        }
        echo '<p class="submit">';
        submit_button(__('Confirm Import', 'custom-fields-csv-importer'), 'primary', 'cfci_import');
        echo '</p></form>';
    }

    private function render_result($updated, $not_found) {
        echo '<h2 style="margin-top:2em;">' . esc_html__('Import complete', 'custom-fields-csv-importer') . '</h2>';

        if (!empty($updated)) {
            echo '<div class="notice notice-success" style="border-left: 4px solid #46b450; padding: 1em; background-color: #f6fff8;">';
            echo '<p><strong>' . esc_html__('Import complete', 'custom-fields-csv-importer') . '</strong></p>';
            echo '<p>' . sprintf(
                /* translators: %d is the number of updated posts */
                esc_html__('Custom fields updated for %d posts.', 'custom-fields-csv-importer'),
                count($updated)
            ) . '</p>';

            echo '<details><summary>' . sprintf(
                /* translators: %d is the number of rows processed */
                esc_html__('Details (%d rows processed)', 'custom-fields-csv-importer'),
                count($updated)
            ) . '</summary>';
            echo '<pre style="background:#f9f9f9; padding:10px; border:1px solid #ccc;">' . esc_html(implode("\n", $updated)) . '</pre>';
            echo '</details>';
            echo '<p><a href="admin.php?page=cfci-import" class="button">&larr; ' . esc_html__('Back to Import', 'custom-fields-csv-importer') . '</a></p>';
            echo '</div>';
        }

        if (!empty($not_found)) {
            echo '<div class="notice notice-warning" style="margin-top:2em;">';
            echo '<p>' . sprintf(
                /* translators: %d is the number of skipped or not found records */
                esc_html__('%d records were skipped or not found.', 'custom-fields-csv-importer'),
                count($not_found)
            ) . '</p>';
            echo '<p><strong>' . esc_html__('Details', 'custom-fields-csv-importer') . '</strong></p>';
            echo '<pre style="background:#fff8f1; padding:10px; border:1px solid #ffc107;">' . esc_html(implode("\n", $not_found)) . '</pre>';
            echo '</div>';
        }
    }
}
