<?php
/**
 * Plugin Name: WordPress Cloudflare R2 Integration
 * Description: Connects WordPress to Cloudflare R2 service for file uploads and access.
 * Version: 1.4
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cloudflare_R2_Integration {
    private $options;

    public function __construct() {
        $this->options = get_option('wp_cloudflare_r2_settings');
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('wp_handle_upload', array($this, 'handle_r2_upload'), 10, 2);
        add_filter('wp_get_attachment_url', array($this, 'get_r2_attachment_url'), 10, 2);
        add_action('wp_ajax_upload_to_r2', array($this, 'ajax_upload_to_r2'));
    }

    public function add_plugin_page() {
        add_menu_page(
            'Cloudflare R2 Settings', 
            'Cloudflare R2', 
            'manage_options', 
            'wp-cloudflare-r2-settings', 
            array($this, 'create_admin_page'),
            'dashicons-cloud-upload'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_wp-cloudflare-r2-settings' !== $hook) {
            return;
        }
        wp_enqueue_style('wp-cloudflare-r2-admin-css', plugins_url('admin-style.css', __FILE__));
        wp_enqueue_script('wp-cloudflare-r2-admin-js', plugins_url('admin-script.js', __FILE__), array('jquery'), null, true);
        wp_localize_script('wp-cloudflare-r2-admin-js', 'wpCloudflareR2Ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('upload_to_r2_nonce')
        ));
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-cloud-upload"></span> Cloudflare R2 Integration</h1>
            <div class="r2-admin-container">
                <div class="r2-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('wp_cloudflare_r2_settings_group');
                        do_settings_sections('wp-cloudflare-r2-settings');
                        submit_button('Save Settings');
                        ?>
                    </form>
                </div>
                <div class="r2-admin-sidebar">
                    <div class="r2-admin-box">
                        <h3>Upload to R2</h3>
                        <form id="r2-upload-form" enctype="multipart/form-data">
                            <div class="file-input-wrapper">
                                <input type="file" name="file" id="r2-file-input" required>
                                <label for="r2-file-input" class="file-input-label">Choose File</label>
                            </div>
                            <button type="submit" class="button button-primary">Upload to R2</button>
                        </form>
                        <div id="r2-upload-progress" class="hidden">
                            <div class="progress-bar"><div class="progress-bar-fill"></div></div>
                            <span class="progress-text">Uploading: 0%</span>
                        </div>
                        <div id="r2-upload-result"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function page_init() {
        register_setting('wp_cloudflare_r2_settings_group', 'wp_cloudflare_r2_settings', array($this, 'sanitize'));

        add_settings_section(
            'wp_cloudflare_r2_settings_section',
            'R2 Configuration',
            array($this, 'print_section_info'),
            'wp-cloudflare-r2-settings'
        );

        $fields = array(
            'account_id' => 'Account ID',
            'access_key_id' => 'Access Key ID',
            'secret_access_key' => 'Secret Access Key',
            'bucket_name' => 'Bucket Name'
        );

        foreach ($fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                array($this, 'field_callback'),
                'wp-cloudflare-r2-settings',
                'wp_cloudflare_r2_settings_section',
                array('field' => $field, 'label' => $label)
            );
        }
    }

    public function sanitize($input) {
        $new_input = array();
        $fields = array('account_id', 'access_key_id', 'secret_access_key', 'bucket_name');
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $new_input[$field] = sanitize_text_field($input[$field]);
            }
        }
        return $new_input;
    }

    public function print_section_info() {
        echo '<p>Enter your Cloudflare R2 credentials below:</p>';
    }

    public function field_callback($args) {
        $field = $args['field'];
        $label = $args['label'];
        $value = isset($this->options[$field]) ? esc_attr($this->options[$field]) : '';
        $type = $field === 'secret_access_key' ? 'password' : 'text';
        echo "<input type='$type' id='$field' name='wp_cloudflare_r2_settings[$field]' value='$value' class='regular-text' />";
        if ($field === 'secret_access_key') {
            echo "<br><small>Leave blank to keep the existing secret key.</small>";
        }
    }

    public function handle_r2_upload($file, $context) {
        if (empty($this->options['account_id']) || empty($this->options['access_key_id']) || 
            empty($this->options['secret_access_key']) || empty($this->options['bucket_name'])) {
            return $file;
        }

        $upload_result = $this->upload_to_r2($file);

        if (!is_wp_error($upload_result)) {
            $file['url'] = $upload_result;
            $file['file'] = $upload_result;
            unlink($file['file']);
        }

        return $file;
    }

    public function get_r2_attachment_url($url, $post_id) {
        $bucket_name = $this->options['bucket_name'];
        $account_id = $this->options['account_id'];

        if (strpos($url, $bucket_name) !== false) {
            $file_name = basename($url);
            return "https://{$account_id}.r2.cloudflarestorage.com/{$bucket_name}/{$file_name}";
        }

        return $url;
    }

    public function ajax_upload_to_r2() {
        check_ajax_referer('upload_to_r2_nonce', 'security');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('You do not have permission to upload files.');
            return;
        }

        if (!isset($_FILES['file'])) {
            wp_send_json_error('No file was uploaded.');
            return;
        }

        $file = $_FILES['file'];
        $upload_result = $this->upload_to_r2($file);

        if (is_wp_error($upload_result)) {
            wp_send_json_error($upload_result->get_error_message());
        } else {
            wp_send_json_success('File uploaded successfully. URL: ' . $upload_result);
        }
    }

    private function upload_to_r2($file) {
        if (!function_exists('curl_init') || !function_exists('curl_exec')) {
            return new WP_Error('curl_missing', 'cURL is required for R2 uploads.');
        }

        $account_id = $this->options['account_id'];
        $access_key_id = $this->options['access_key_id'];
        $secret_access_key = $this->options['secret_access_key'];
        $bucket_name = $this->options['bucket_name'];

        if (empty($account_id) || empty($access_key_id) || empty($secret_access_key) || empty($bucket_name)) {
            return new WP_Error('missing_credentials', 'R2 credentials are not set.');
        }

        $file_name = $file['name'];
        $file_path = isset($file['file']) ? $file['file'] : $file['tmp_name'];
        $content_type = "application/octet-stream";

        $datetime = gmdate('Ymd\THis\Z');
        $date = substr($datetime, 0, 8);
        $payload_hash = hash('sha256', file_get_contents($file_path));

        $endpoint = "https://{$account_id}.r2.cloudflarestorage.com/{$bucket_name}/{$file_name}";

        // ************* TASK 1: CREATE A CANONICAL REQUEST *************
        $canonical_uri = "/{$bucket_name}/{$file_name}";
        $canonical_querystring = '';
        $canonical_headers = "content-type:{$content_type}\nhost:{$account_id}.r2.cloudflarestorage.com\nx-amz-content-sha256:{$payload_hash}\nx-amz-date:{$datetime}\n";
        $signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonical_request = "PUT\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";

        // ************* TASK 2: CREATE THE STRING TO SIGN *************
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = "{$date}/auto/s3/aws4_request";
        $string_to_sign = "{$algorithm}\n{$datetime}\n{$credential_scope}\n" . hash('sha256', $canonical_request);

        // ************* TASK 3: CALCULATE THE SIGNATURE *************
        $signing_key = $this->getSignatureKey($secret_access_key, $date, 'auto', 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        // ************* TASK 4: ADD SIGNING INFORMATION TO THE REQUEST *************
        $authorization_header = "{$algorithm} Credential={$access_key_id}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

        $headers = array(
            "Host: {$account_id}.r2.cloudflarestorage.com",
            "Content-Type: {$content_type}",
            "x-amz-content-sha256: {$payload_hash}",
            "x-amz-date: {$datetime}",
            "Authorization: {$authorization_header}"
        );

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($file_path));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code === 200) {
            return $endpoint;
        } else {
            return new WP_Error('upload_failed', 'Failed to upload file to R2. HTTP Code: ' . $http_code);
        }
    }

    private function getSignatureKey($key, $date, $regionName, $serviceName) {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return $kSigning;
    }
}

$wp_cloudflare_r2_integration = new WP_Cloudflare_R2_Integration();