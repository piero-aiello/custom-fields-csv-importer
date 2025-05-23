<?php

class CFCI_AdminPage
{
    private static $instance;

    public static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'toplevel_page_cfci-import') return;

        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
        wp_add_inline_script('select2', 'jQuery(document).ready(function($){
            $(".cfci-select2").select2();
            if (window.location.search.includes("cfci_action=import")) {
                $(".cfci-select2").val(null).trigger("change");
                $("#csv_file").val("");
            }
        });');
    }

    public function register_menu()
    {
        add_menu_page(
            __('Custom Fields CSV Importer', 'custom-fields-csv-importer'),
            __('Custom Fields CSV Importer', 'custom-fields-csv-importer'),
            'manage_options',
            'cfci-import',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page()
    {
        $post_types = get_post_types(['public' => true], 'objects');
        $selected_post_types = $_POST['cfci_post_type'] ?? [];

?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Import Custom Fields from CSV', 'custom-fields-csv-importer'); ?></h1>

            <div class="notice notice-info is-dismissible" style="margin-top: 15px;">
                <p>
                    <?php echo esc_html__('Note: This beta version only supports importing plain text values. Complex ACF fields (arrays, repeaters, objects) are not currently supported.', 'custom-fields-csv-importer'); ?>
                </p>
            </div>

            <p><?php _e('This tool allows you to update custom fields for existing posts using a CSV file. ...', 'custom-fields-csv-importer'); ?></p>

            <p>
                <a href="<?php echo esc_url(CFCI_URL . 'assets/sample.csv'); ?>" class="button button-secondary">
                    <?php _e('Download sample file', 'custom-fields-csv-importer'); ?>
                </a>
            </p>

            <form method="post" enctype="multipart/form-data" style="margin-top: 20px; max-width: 600px;">
                <?php wp_nonce_field('cfci_import_action', 'cfci_import_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cfci_post_type"><?php _e('Select Post Types', 'custom-fields-csv-importer'); ?></label></th>
                        <td>
                            <select id="cfci_post_type" name="cfci_post_type[]" class="cfci-select2" multiple="multiple" required style="width:100%;">
                                <?php foreach ($post_types as $type): ?>
                                    <option value="<?php echo esc_attr($type->name); ?>"
                                        <?php echo in_array($type->name, (array) $selected_post_types, true) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($type->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="csv_file"><?php _e('CSV File', 'custom-fields-csv-importer'); ?></label></th>
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

    private function handle_form_submission()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = $_POST['cfci_action'] ?? 'preview';

        if (!isset($_POST['cfci_import_nonce']) || !wp_verify_nonce($_POST['cfci_import_nonce'], 'cfci_import_action')) {
            wp_die(__('Security check failed.', 'custom-fields-csv-importer'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'custom-fields-csv-importer'));
        }

        $selected_post_types = isset($_POST['cfci_post_type']) ? array_map('sanitize_text_field', (array) $_POST['cfci_post_type']) : [];

        try {
            $handler = new CFCI_CSVHandler();

            if ($action === 'preview') {
                // Save uploaded file temporarily
                if (empty($_FILES['csv_file']['tmp_name'])) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('No file selected.', 'custom-fields-csv-importer') . '</p></div>';
                    return;
                }

                $upload_dir = wp_upload_dir();
                $cfci_dir = $upload_dir['basedir'] . '/cfci';
                if (!file_exists($cfci_dir)) {
                    wp_mkdir_p($cfci_dir);
                }

                $tmp_path = $cfci_dir . '/preview_' . time() . '.csv';
                if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmp_path)) {
                    throw new Exception(__('Failed to save temporary CSV file.', 'custom-fields-csv-importer'));
                }

                $handler->render_preview($tmp_path, $selected_post_types);
                return;
            }

            if ($action === 'import') {
                $csv_path = sanitize_text_field($_POST['csv_file_path'] ?? '');
                if (!file_exists($csv_path)) {
                    throw new Exception(__('Temporary CSV file not found.', 'custom-fields-csv-importer'));
                }

                $handler->handle_upload($csv_path, $selected_post_types);
                unlink($csv_path); // Clean up after import
                return;
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Error:', 'custom-fields-csv-importer') . ' ' . esc_html($e->getMessage()) . '</p></div>';
            error_log('CFCI Error: ' . $e->getMessage());
        }
    }
}
