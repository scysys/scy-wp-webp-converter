<?php
/*
Plugin Name: Scy: WebP Converter
Plugin URI: https://github.com/scysys/scy-wp-webp-converter
Description: Converts uploads to WebP and serves them dynamically.
Version: 1.0.0
Author: scysys
Author URI: https://github.com/scysys/
License: GPLv3 or later
Text Domain: scy-webp-converter
*/

if (!defined('ABSPATH')) {
    exit;
}

class WPWebPConverter
{

    public function __construct()
    {

        add_filter('wp_generate_attachment_metadata', [$this, 'convert_to_webp'], 10, 2);
        add_filter('the_content', [$this, 'filter_image_urls']);
        add_filter('post_thumbnail_html', [$this, 'filter_image_urls']);
        add_filter('wp_get_attachment_image_src', [$this, 'filter_attachment_image_src'], 10, 4);
        add_filter('manage_media_columns', [$this, 'add_webp_column']);
        add_action('manage_media_custom_column', [$this, 'render_webp_column'], 10, 2);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']);
        add_action('wp', [$this, 'schedule_conversion_check']);
        add_action('webp_conversion_cron', [$this, 'convert_all_existing_images']);
        add_action('admin_post_generate_webp_now', [$this, 'generate_webp_now']);
        add_action('admin_post_run_webp_converter_cron', [$this, 'run_webp_converter_cron']);
        add_action('wp_ajax_webp_regen_init', [$this, 'webp_regen_init']);
        add_action('wp_ajax_webp_regen_next', [$this, 'webp_regen_next']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_nonce']);
    }

    public function convert_to_webp($metadata, $attachment_id)
    {

        $dir = wp_upload_dir();
        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $s) {
                if (!empty($s['file'])) {
                    $path = $dir['basedir'] . '/' . $s['file'];
                    $this->generate_webp_file($path);
                }
            }
        }
        $original = get_attached_file($attachment_id);
        if ($original) {
            $this->generate_webp_file($original);
        }
        return $metadata;
    }

    private function generate_webp_file($path)
    {

        $info = pathinfo($path);
        $ext  = strtolower($info['extension']);
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            return;
        }
        $webp = $path . '.webp';
        if (file_exists($webp)) {
            return;
        }
        $method  = get_option('webp_converter_method', 'gd');
        $quality = (int) get_option('webp_converter_quality', 82);
        if ($method === 'gd') {
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $img = imagecreatefromjpeg($path);
            } else {
                $img = imagecreatefrompng($path);
            }
            if ($img) {
                imagewebp($img, $webp, $quality);
                imagedestroy($img);
            }
        } elseif ($method === 'imagick' && class_exists('Imagick')) {
            $im = new Imagick($path);
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($quality);
            $im->writeImage($webp);
            $im->clear();
            $im->destroy();
        } elseif ($method === 'gmagick' && class_exists('Gmagick')) {
            $gm = new Gmagick($path);
            $gm->setimageformat('webp');
            $gm->setCompressionQuality($quality);
            $gm->write($webp);
            $gm->destroy();
        }
    }

    public function filter_image_urls($content)
    {

        return preg_replace_callback(
            '/(https?:\/\/[^\s"]+\.(?:jpg|jpeg|png))(?!\.webp)/i',

            function ($matches) {
                $webp_url  = $matches[1] . '.webp';
                $webp_file = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $webp_url);
                if (!file_exists($webp_file)) {
                    $original = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $matches[1]);
                    $this->generate_webp_file($original);
                }
                if (file_exists($webp_file)) {
                    return $webp_url;
                }
                return $matches[1];
            },
            $content
        );
    }

    public function filter_attachment_image_src($image, $attachment_id, $size, $icon)
    {

        if (!empty($image[0]) && !preg_match('/\.webp$/i', $image[0])) {
            $webp_url  = $image[0] . '.webp';
            $webp_file = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $webp_url);
            if (file_exists($webp_file)) {
                $image[0] = $webp_url;
            }
        }
        return $image;
    }

    public function add_webp_column($columns)
    {

        $columns['webp_status'] = __('WebP Status', 'scy-webp-converter');
        return $columns;
    }

    public function render_webp_column($column_name, $attachment_id)
    {

        if ($column_name !== 'webp_status') {
            return;
        }
        $path = get_attached_file($attachment_id);
        if (!$path) {
            return;
        }
        $info = pathinfo($path);
        $ext  = strtolower($info['extension']);
        if ($ext === 'webp') {
            echo '<span style="color: green;">Already WebP</span>';
            return;
        }
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            echo '<span style="color: orange;">Not supported</span>';
            return;
        }
        $webp_file = $path . '.webp';
        if (file_exists($webp_file)) {
            $orig_url   = str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $path);
            $webp_url   = str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $webp_file);
            $orig_size  = filesize($path);
            $webp_size  = filesize($webp_file);
            $orig_human = $this->format_file_size($orig_size);
            $webp_human = $this->format_file_size($webp_size);
            $saved      = $orig_size ? round((1 - ($webp_size / $orig_size)) * 100, 2) : 0;
            echo '<span style="color: green;">Exists</span><br>';
            echo '<strong>Original:</strong> <a href="' . esc_url($orig_url) . '" target="_blank" rel="noopener" style="color: #097dff;">' . esc_html($orig_human) . '</a><br>';
            echo '<strong>WebP:</strong> <a href="' . esc_url($webp_url) . '" target="_blank" rel="noopener" style="color: #097dff;">' . esc_html($webp_human) . ' (' . esc_html($saved) . '% saved)</a>';
        } else {
            $gen_url = wp_nonce_url(
                admin_url('admin-post.php?action=generate_webp_now&file=' . urlencode($path)),
                'generate_webp_now'
            );
            echo '<a href="' . esc_url($gen_url) . '" style="color: red;">Missing (Generate now)</a>';
        }
    }

    private function format_file_size($size)
    {

        if ($size >= 1048576) {
            return round($size / 1048576, 2) . ' MB';
        } elseif ($size >= 1024) {
            return round($size / 1024, 2) . ' KB';
        }
        return $size . ' B';
    }

    public function generate_webp_now()
    {

        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission.');
        }
        check_admin_referer('generate_webp_now');
        if (isset($_GET['file'])) {
            $file_path = sanitize_text_field(wp_unslash($_GET['file']));
            $this->generate_webp_file($file_path);
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url('upload.php'));
        exit;
    }

    public function add_settings_page()
    {

        add_options_page(
            'WebP Converter',
            'WebP Converter',
            'manage_options',
            'webp-converter',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page()
    {

        $limit             = get_option('webp_converter_cron_limit', 25);
        $freq              = get_option('webp_converter_cron_frequency', 'hourly');
        $method            = get_option('webp_converter_method', 'gd');
        $quality           = (int) get_option('webp_converter_quality', 82);
        $gd_available      = extension_loaded('gd') ? 'Available' : 'Not Available';
        $imagick_available = class_exists('Imagick') ? 'Available' : 'Not Available';
        $gmagick_available = class_exists('Gmagick') ? 'Available' : 'Not Available';
        $labels            = [
            'every_five_minutes'    => 'Every 5 Minutes',
            'every_fifteen_minutes' => 'Every 15 Minutes',
            'every_thirty_minutes'  => 'Every 30 Minutes',
            'hourly'                => 'Hourly',
            'twicedaily'            => 'Twice Daily',
            'daily'                 => 'Daily',
        ];
        $freq_print        = isset($labels[$freq]) ? $labels[$freq] : $freq;
        echo '<div class="wrap">';
        echo '<h1>WebP Converter Settings</h1>';
        echo '<p>This plugin automatically converts images to WebP format and serves them dynamically. A cron job runs hourly by default to check for new images and converts them if needed.<br/>Converted images are displayed automatically on the website, ensuring better performance with minimal effort.</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('webp_converter_settings');
        do_settings_sections('webp-converter');
        submit_button();
        echo '</form>';
        echo '<h3>Conversion Method Availability</h3>';
        echo '<p><strong>GD:</strong> ' . esc_html($gd_available) . '</p>';
        echo '<p><strong>Imagick:</strong> ' . esc_html($imagick_available) . '</p>';
        echo '<p><strong>Gmagick:</strong> ' . esc_html($gmagick_available) . '</p>';
        echo '<hr>';
        echo '<h2>Run Cron Job Manually</h2>';
        echo '<p>This cron job processes <strong>' . esc_html($limit) . '</strong> images per run and runs <strong>' . esc_html($freq_print) . '</strong>.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php?action=run_webp_converter_cron')) . '">';
        wp_nonce_field('run_webp_converter_cron_nonce');
        submit_button('Run Cron Now', 'secondary');
        echo '</form>';
        echo '<hr>';
        echo '<h2>Regenerate All Images</h2>';
        echo '<p>Press the button below to regenerate all images. The terminal will appear and show each step in a new line.</p>';
        echo '<button id="webp-start-regen" class="button button-primary">Regenerate All</button>';
        echo '<div style="width:100%;height:400px;background:#000;color:#0f0;font-family:monospace;overflow:auto;margin-top:10px;padding:5px;white-space:pre-wrap;display:none" id="webp-terminal"></div>';
        echo '<hr>';
        echo '<p style="margin-top:2em;">Thank you for using WebP Converter. If this plugin has helped you, <a href="https://buy.stripe.com/4gwcOm4l86dyeuk8ww" target="_blank" rel="noopener">please consider donating</a>.</p>';
        echo '</div>';
        ?>
        <script>
            (function ($) {
                let running = false;
                const terminalBox = $('#webp-terminal');
                $('#webp-start-regen').on('click', function (e) {
                    e.preventDefault();
                    if (running) return;
                    running = true;
                    terminalBox.hide().text('').show();
                    terminalBox.append("Initializing...\n");
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: { action: 'webp_regen_init', security: scyWebp.security },
                        success: function (resp) {
                            if (resp === 'ok') {
                                terminalBox.append("Starting...\n");
                                nextImage();
                            } else {
                                terminalBox.append("Error: " + resp + "\n");
                                running = false;
                            }
                        },
                        error: function () {
                            terminalBox.append("Error occurred.\n");
                            running = false;
                        }
                    });
                });
                function nextImage() {
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: { action: 'webp_regen_next', security: scyWebp.security },
                        dataType: 'json',
                        success: function (resp) {
                            terminalBox.append(resp.msg + "\n");
                            terminalBox.scrollTop(terminalBox[0].scrollHeight);
                            if (resp.done) {
                                terminalBox.append("All done.\n");
                                running = false;
                            } else {
                                setTimeout(nextImage, 100);
                            }
                        },
                        error: function () {
                            terminalBox.append("Error on chunk.\n");
                            running = false;
                        }
                    });
                }
            })(jQuery);
        </script>
        <?php
    }

    public function register_settings()
    {

        register_setting('webp_converter_settings', 'webp_converter_method');
        register_setting('webp_converter_settings', 'webp_converter_cron_limit');
        register_setting('webp_converter_settings', 'webp_converter_cron_frequency');
        register_setting('webp_converter_settings', 'webp_converter_quality');
        add_settings_section('webp_converter_main', 'General Settings', null, 'webp-converter');
        add_settings_field(
            'webp_converter_method',
            'Conversion Method',

            function () {
                $m = get_option('webp_converter_method', 'gd');
                echo '<select name="webp_converter_method">';
                echo '<option value="gd"' . selected($m, 'gd', false) . '>GD</option>';
                if (class_exists('Imagick')) {
                    echo '<option value="imagick"' . selected($m, 'imagick', false) . '>Imagick</option>';
                }
                if (class_exists('Gmagick')) {
                    echo '<option value="gmagick"' . selected($m, 'gmagick', false) . '>Gmagick</option>';
                }
                echo '</select>';
            },
            'webp-converter',
            'webp_converter_main'
        );
        add_settings_field(
            'webp_converter_quality',
            'WebP Quality',

            function () {
                $q = (int) get_option('webp_converter_quality', 82);
                echo '<input type="number" name="webp_converter_quality" min="0" max="100" value="' . esc_attr($q) . '" style="width:60px;">';
            },
            'webp-converter',
            'webp_converter_main'
        );
        add_settings_field(
            'webp_converter_cron_limit',
            'Files Per Cron Run',

            function () {
                $l = (int) get_option('webp_converter_cron_limit', 25);
                echo '<input type="number" name="webp_converter_cron_limit" min="1" value="' . esc_attr($l) . '" style="width:60px;">';
            },
            'webp-converter',
            'webp_converter_main'
        );
        add_settings_field(
            'webp_converter_cron_frequency',
            'Cron Frequency',

            function () {
                $c    = get_option('webp_converter_cron_frequency', 'hourly');
                $opts = [
                    'every_five_minutes'    => 'Every 5 Minutes',
                    'every_fifteen_minutes' => 'Every 15 Minutes',
                    'every_thirty_minutes'  => 'Every 30 Minutes',
                    'hourly'                => 'Hourly',
                    'twicedaily'            => 'Twice Daily',
                    'daily'                 => 'Daily',
                ];
                echo '<select name="webp_converter_cron_frequency">';
                foreach ($opts as $val => $lbl) {
                    echo '<option value="' . esc_attr($val) . '"' . selected($c, $val, false) . '>' . esc_html($lbl) . '</option>';
                }
                echo '</select>';
            },
            'webp-converter',
            'webp_converter_main'
        );
    }

    public function add_custom_cron_schedules($schedules)
    {

        $schedules['every_five_minutes']    = [
            'interval' => 5 * 60,
            'display'  => 'Every 5 Minutes',
        ];
        $schedules['every_fifteen_minutes'] = [
            'interval' => 15 * 60,
            'display'  => 'Every 15 Minutes',
        ];
        $schedules['every_thirty_minutes']  = [
            'interval' => 30 * 60,
            'display'  => 'Every 30 Minutes',
        ];
        return $schedules;
    }

    public function schedule_conversion_check()
    {

        $freq = get_option('webp_converter_cron_frequency', 'hourly');
        $time = wp_next_scheduled('webp_conversion_cron');
        if ($time) {
            $current = wp_get_schedule($time, 'webp_conversion_cron');
            if ($current !== $freq) {
                wp_unschedule_event($time, 'webp_conversion_cron');
            }
        }
        if (!wp_next_scheduled('webp_conversion_cron')) {
            wp_schedule_event(time(), $freq, 'webp_conversion_cron');
        }
    }

    public function convert_all_existing_images()
    {

        $base   = wp_upload_dir()['basedir'];
        $images = $this->scan_directory_for_images($base);
        $limit  = (int) get_option('webp_converter_cron_limit', 25);
        $batch  = array_slice($images, 0, $limit);
        foreach ($batch as $img) {
            $this->generate_webp_file($img);
        }
    }

    private function scan_directory_for_images($dir)
    {

        $list  = [];
        $files = @scandir($dir);
        if (!$files) {
            return $list;
        }
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $full = $dir . '/' . $f;
            if (is_dir($full)) {
                $list = array_merge($list, $this->scan_directory_for_images($full));
            } elseif (preg_match('/\.(jpe?g|png)$/i', $f)) {
                $list[] = $full;
            }
        }
        return $list;
    }

    public function run_webp_converter_cron()
    {

        check_admin_referer('run_webp_converter_cron_nonce');
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission.');
        }
        do_action('webp_conversion_cron');
        wp_redirect(add_query_arg('webp_message', 'cron_success', admin_url('admin.php?page=webp-converter')));
        exit;
    }

    public function webp_regen_init()
    {

        check_ajax_referer('scy_webp_nonce', 'security');
        if (!current_user_can('manage_options')) {
            echo 'No permission';
            exit;
        }
        $all = $this->collect_all_images();
        set_transient('webp_regen_list', $all, 3600);
        echo 'ok';
        exit;
    }

    public function webp_regen_next()
    {

        check_ajax_referer('scy_webp_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No permission');
        }
        $list = get_transient('webp_regen_list');
        if (!$list || !is_array($list) || count($list) === 0) {
            wp_send_json(['done' => true, 'msg' => "No images left to process."]);
        }
        $img       = array_shift($list);
        $file_name = sanitize_file_name(basename($img));
        $this->generate_webp_file($img);
        set_transient('webp_regen_list', $list, 3600);
        if (count($list) === 0) {
            wp_send_json(['done' => true, 'msg' => "Regenerated: $file_name"]);
        }
        wp_send_json(['done' => false, 'msg' => "Regenerated: $file_name"]);
    }

    private function collect_all_images()
    {

        $base     = wp_upload_dir()['basedir'];
        $arr      = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $n = $file->getFilename();
                if (preg_match('/\.(jpe?g|png)$/i', $n)) {
                    $arr[] = $file->getPathname();
                }
            }
        }
        return $arr;
    }

    public function admin_notices()
    {

        if (isset($_GET['_wpnonce'])) {
            check_admin_referer('generate_webp_now');
        }
        if (isset($_GET['webp_message'])) {
            $wm = sanitize_text_field(wp_unslash($_GET['webp_message']));
            if ($wm === 'cron_success') {
                echo '<div class="notice notice-success is-dismissible"><p>Cron job started. Limited batch will regenerate.</p></div>';
            }
        }
        if (isset($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }
    }

    public function enqueue_nonce()
    {

        if (is_admin()) {
            wp_localize_script('jquery', 'scyWebp', [
                'security' => wp_create_nonce('scy_webp_nonce'),
            ]);
        }
    }

}

new WPWebPConverter();

register_deactivation_hook(
    __FILE__,

    function () {
        wp_clear_scheduled_hook('webp_conversion_cron');
    }
);
