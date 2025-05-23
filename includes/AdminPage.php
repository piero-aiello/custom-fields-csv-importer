<?php

class CFCI_AdminPage {
    private static $instance;

    public static function get_instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_cfci-import') return;

        // ⚠️ WordPress.org non consente risorse esterne. Usa file locali in produzione
        wp_enqueue_style('cfci-select2', plugins_url('assets/select2.min.css', __FILE__), [], '4.1.0');
        wp_enqueue_script('cfci-select2', plugins_url('assets/select2.min.js', __FILE__), ['jquery'], '4.1.0', true);
        wp_add_inline_script('cfci-select2', 'jQuery(document).ready(function($){
            $(".cfci-select2").select2();
            if (window.location.search.includes("cfci_action=import")) {
                $(".cfci-select2").val(null).trigger("change");
                $("#csv_file").val("");
            }
        });');
    }

    public function register_menu() {
        add_menu_page(
            esc_html__('Custom Fields CSV Importer', 'custom-fields-csv-importer'),
            esc_html__('Custom Fields CSV Importer', 'custom-fields-csv-importer'),
            'manage_options',
            'cfci-import',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page() {
        $post_types = get_post_types(['public' => true], 'objects');
        $selected_post_types = [];

            if (
                isset($_POST['cfci_import_nonce']) &&
                wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cfci_import_nonce'])), 'cfci_import_action') &&
                isset($_POST['cfci_post_type'])
            ) {
                $selected_post_types = array_map('sanitize_text_field', (array) wp_unslash($_POST['cfci_post_type']));
            }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Import Custom Fields from CSV', 'custom-fields-csv-importer'); ?></h1>

            <div class="notice notice-info is-dismissible" style="margin-top: 15px;">
                <p>
                    <?php esc_html_e('Note: This beta version only supports importing plain text values. Complex ACF fields (arrays, repeaters, objects) are not currently supported.', 'custom-fields-csv-importer'); ?>
                </p>
            </div>

            <p><?php esc_html_e('This tool allows you to update custom fields for existing posts using a CSV file.', 'custom-fields-csv-importer'); ?></p>

            <p>
                <a href="<?php echo esc_url(CFCI_URL . 'assets/sample.csv'); ?>" class="button button-secondary">
                    <?php esc_html_e('Download sample file', 'custom-fields-csv-importer'); ?>
                </a>
            </p>

            <form method="post" enctype="multipart/form-data" style="margin-top: 20px; max-width: 600px;">
                <?php wp_nonce_field('cfci_import_action', 'cfci_import_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cfci_post_type"><?php esc_html_e('Select Post Types', 'custom-fields-csv-importer'); ?></label></th>
                        <td>
                            <select id="cfci_post_type" name="cfci_post_type[]" class="cfci-select2" multiple="multiple" required style="width:100%;">
                                <?php foreach ($post_types as $type): ?>
                                    <option value="<?php echo esc_attr($type->name); ?>" <?php selected(in_array($type->name, (array) $selected_post_types, true)); ?>>
                                        <?php echo esc_html($type->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="csv_file"><?php esc_html_e('CSV File', 'custom-fields-csv-importer'); ?></label></th>
                        <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required /></td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="hidden" name="cfci_action" value="preview" />
                    <input type="submit" name="cfci_preview" class="button button-primary" value="<?php esc_attr_e('Preview Import', 'custom-fields-csv-importer'); ?>" />
                </p>
            </form>

            <?php $this->handle_form_submission(); ?>
        </div>
        <?php
    }

    private function handle_form_submission() {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['cfci_action']) ? sanitize_text_field(wp_unslash($_POST['cfci_action'])) : 'preview';

        $nonce = isset($_POST['cfci_import_nonce']) ? sanitize_text_field(wp_unslash($_POST['cfci_import_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'cfci_import_action')) {
            wp_die(esc_html__('Security check failed.', 'custom-fields-csv-importer'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'custom-fields-csv-importer'));
        }

        $selected_post_types = isset($_POST['cfci_post_type']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['cfci_post_type'])) : [];

        try {
            $handler = new CFCI_CSVHandler();

            if ($action === 'preview') {
                if (empty($_FILES['csv_file']['tmp_name'])) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('No file selected.', 'custom-fields-csv-importer') . '</p></div>';
                    return;
                }

                $upload = wp_handle_upload($_FILES['csv_file'], ['test_form' => false]);

                if (isset($upload['error'])) {
                    throw new Exception($upload['error']);
                }

                $tmp_path = $upload['file'];
                $handler->render_preview($tmp_path, $selected_post_types);
                return;
            }

            if ($action === 'import') {
                $csv_path = isset($_POST['csv_file_path']) ? sanitize_text_field(wp_unslash($_POST['csv_file_path'])) : '';
                if (!file_exists($csv_path)) {
                    throw new Exception(__('Temporary CSV file not found.', 'custom-fields-csv-importer'));
                }

                $handler->handle_upload($csv_path, $selected_post_types);
                wp_delete_file($csv_path);
                return;
            }

        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Error:', 'custom-fields-csv-importer') . ' ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
}
