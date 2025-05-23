<?php

class CFCI_CSVHandler {
    public function handle_upload($file_path, array $allowed_post_types) {
        if (!is_readable($file_path)) {
            throw new Exception(__('The file cannot be read.', 'custom-fields-csv-importer'));
        }

        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            throw new Exception(__('Unable to open the CSV file.', 'custom-fields-csv-importer'));
        }

        $header = fgetcsv($handle, 0, ',');
        if ($header === false || count($header) < 3) {
            fclose($handle);
            throw new Exception(__('Invalid CSV format. It must contain at least 3 columns: ID, meta_key, meta_value.', 'custom-fields-csv-importer'));
        }

        $batch = [];
        $batch_size = 100;
        $updated = [];
        $not_found = [];

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $row = array_map('trim', $row);
            if (count($row) < 3) continue;

            list($id, $meta_key, $meta_value) = $row;

            if (!ctype_digit($id)) {
                $not_found[] = "Invalid ID: {$id}";
                continue;
            }

            $batch[] = [
                'id' => (int) $id,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value,
                'allowed_post_types' => $allowed_post_types
            ];

            if (count($batch) >= $batch_size) {
                CFCI_Processor::process_batch($batch, $updated, $not_found);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            CFCI_Processor::process_batch($batch, $updated, $not_found);
        }

        $wpdb->query('COMMIT');
        fclose($handle);

        $this->render_result($updated, $not_found);
    }


    public function render_preview($file_path, array $allowed_post_types) {
        if (!is_readable($file_path)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Preview failed. The file could not be read.', 'custom-fields-csv-importer') . '</p></div>';
            return;
        }
    
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Unable to open the CSV file.', 'custom-fields-csv-importer') . '</p></div>';
            return;
        }
    
        $header = fgetcsv($handle, 0, ',');
        if (!$header || count($header) < 3) {
            echo '<div class="notice notice-error"><p>' . esc_html__('CSV must have 3 columns: ID, meta_key, meta_value.', 'custom-fields-csv-importer') . '</p></div>';
            fclose($handle);
            return;
        }
    
        // Determina se mostrare tutte le righe
        $show_all = !empty($_POST['cfci_preview_all']);
    
        // Leggi tutte le righe in memoria
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = array_slice($row, 0, 3);
        }
        fclose($handle);
    
        echo '<h2>' . esc_html__('Preview (' . ($show_all ? count($rows) : 'first 10') . ' rows)', 'custom-fields-csv-importer') . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>ID</th><th>Post Type</th><th>Meta Key</th><th>Meta Value</th></tr></thead><tbody>';
    
        $rows_shown = 0;
        foreach ($rows as $row) {
            $row = array_map('esc_html', $row);
            $post_id = (int) $row[0];
            $post_type = '—';
            if ($post_id) {
                $post = get_post($post_id);
                if ($post) {
                    $post_type = esc_html($post->post_type);
                }
            }
    
            echo '<tr><td>' . $row[0] . '</td><td>' . $post_type . '</td><td>' . $row[1] . '</td><td>' . $row[2] . '</td></tr>';
    
            $rows_shown++;
            if (!$show_all && $rows_shown >= 10) break;
        }
    
        echo '</tbody></table>';
    
        // Bottone "Visualizza tutte"
        if (!$show_all && count($rows) > 10) {
            echo '<form method="post" style="margin-top: 1em;">';
            wp_nonce_field('cfci_import_action', 'cfci_import_nonce');
            echo '<input type="hidden" name="cfci_action" value="preview">';
            echo '<input type="hidden" name="csv_file_path" value="' . esc_attr($file_path) . '">';
            foreach ($allowed_post_types as $pt) {
                echo '<input type="hidden" name="cfci_post_type[]" value="' . esc_attr($pt) . '">';
            }
            echo '<input type="hidden" name="cfci_preview_all" value="1">';
            echo '<p><button type="submit" class="button">' . __('View All Rows', 'custom-fields-csv-importer') . '</button></p>';
            echo '</form>';
        }
    
        // Form di conferma importazione
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
            echo '<p><strong>
            <svg xmlns="http://www.w3.org/2000/svg" style="width: 1em; height: 1em; vertical-align: middle; margin-right: 0.3em;" fill="#46b450" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8.414 8.414a1 1 0 01-1.414 0L3.293 11.12a1 1 0 111.414-1.414l3.172 3.172 7.707-7.707a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>Import complete</strong></p>';
            echo '<p>' . sprintf(__('Custom fields updated for %d posts.', 'custom-fields-csv-importer'), count($updated)) . '</p>';
            echo '<details>';
            echo '<summary>' . sprintf(__('Details (%d rows processed)', 'custom-fields-csv-importer'), count($updated)) . '</summary>';
            echo '<pre style="background:#f9f9f9; padding:10px; border:1px solid #ccc;">' . esc_html(implode("\n", $updated)) . '</pre>';
            echo '</details>';
            echo '<p><a href="admin.php?page=cfci-import" class="button">← ' . esc_html__('Back to Import', 'custom-fields-csv-importer') . '</a></p>';
            echo '</div>';
    
            // CSV file export
            $filename = 'cfci-updated-' . date('Ymd-His') . '.csv';
            $upload_dir = wp_upload_dir();
            $cfci_dir = $upload_dir['basedir'] . '/cfci';
            if (!file_exists($cfci_dir)) wp_mkdir_p($cfci_dir);
            $filepath = $cfci_dir . '/' . $filename;
            $fh = fopen($filepath, 'w');
            fputcsv($fh, ['Post ID', 'Meta Key', 'Meta Value']);
            foreach ($updated as $line) {
                if (preg_match('/^ID (\d+) - ([^=]+) = (.*)$/', $line, $matches)) {
                    fputcsv($fh, [$matches[1], trim($matches[2]), trim($matches[3])]);
                }
            }
            fclose($fh);
            $url = $upload_dir['baseurl'] . '/cfci/' . $filename;
    
            echo '<p><a href="' . esc_url($url) . '" class="button">' .
                __('Download updated records as CSV', 'custom-fields-csv-importer') . '</a></p>';
            echo '<p><em>' . esc_html__('CSV files are stored in wp-content/uploads/cfci/', 'custom-fields-csv-importer') . '</em></p>';
        }
    
        if (!empty($not_found)) {
            echo '<div class="notice notice-warning" style="margin-top:2em;">';
            echo '<p>' . sprintf(__('%d records were skipped or not found.', 'custom-fields-csv-importer'), count($not_found)) . '</p>';
            echo '<p><strong>' . esc_html__('Details', 'custom-fields-csv-importer') . '</strong></p>';
            echo '<pre style="background:#fff8f1; padding:10px; border:1px solid #ffc107;">' . esc_html(implode("\n", $not_found)) . '</pre>';
            echo '</div>';
    
            // CSV for errors
            $filename = 'cfci-errors-' . time() . '.csv';
            $filepath = $cfci_dir . '/' . $filename;
            $fh = fopen($filepath, 'w');
            fputcsv($fh, ['Error']);
            foreach ($not_found as $error) {
                fputcsv($fh, [$error]);
            }
            fclose($fh);
            $url = $upload_dir['baseurl'] . '/cfci/' . $filename;
    
            echo '<p><a href="' . esc_url($url) . '" class="button">' .
                __('Download errors as CSV', 'custom-fields-csv-importer') . '</a></p>';
        }
    }
    
}
