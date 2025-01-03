<?php

/**
 * Plugin Name: Stark Message
 * Plugin URI: https://julianstark.de/plugins/
 * Description: A simple and lightweight solution (>2KB, no jQuery) to display a sticky message popup on the screen with a dismissable close button. Includes admin settings to configure custom HTML content and CSS and define pages to display popup.
 * Version: 0.9
 * Author: Julian Stark
 * Author URI: https://julianstark.de
 * Text Domain: stark-message
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enqueue stylesheet and script only if popup is enabled.
 */
function stark_message_enqueue_assets() {
    if (!is_admin() && stark_message_is_popup_enabled()) {
        // Enqueue style and script
        wp_enqueue_style('stark-message-style', plugin_dir_url(__FILE__) . 'style.css');
        wp_enqueue_script('stark-message-script', plugin_dir_url(__FILE__) . 'script.js', array(), null, true);

        // Fetch custom CSS from the database
        $custom_css = get_option('stark_message_css', '');
        if (!empty($custom_css)) {
            // Add custom CSS inline to the loaded stylesheet
            wp_add_inline_style('stark-message-style', $custom_css);
        }

        // Pass PHP data to JavaScript
        $cookie_version = get_option('stark_message_cookie_version', time());
        wp_localize_script('stark-message-script', 'starkMessage', array(
            'cookie_suffix' => $cookie_version,
            'cookie_lifetime' => 3 * 24 * 60 * 60, // 3 days in seconds
        ));
    }
}
add_action('wp_enqueue_scripts', 'stark_message_enqueue_assets');

/**
 * Check if the popup should be displayed.
 */
function stark_message_is_popup_enabled() {
    $enabled = get_option('stark_message_enabled', false);
    if (!$enabled) {
        return false;
    }

    // Get current page ID
    $current_page_id = get_queried_object_id();
    $page_ids = get_option('stark_message_page_ids', '');
    if (!empty($page_ids)) {
        $allowed_pages = array_map('intval', explode(',', $page_ids));
        if (!in_array($current_page_id, $allowed_pages)) {
            return false; // Popup not enabled for this page
        }
    }

    return true;
}

/**
 * Hook footer to add the popup HTML if it should be displayed.
 */
function stark_message_display_popup() {
    if (!stark_message_is_popup_enabled()) {
        return;
    }

    $cookie_version = get_option('stark_message_cookie_version', time());
    $cookie_name = 'stark_message_closed_' . $cookie_version;

    // Do not display the popup if the cookie already exists
    if (isset($_COOKIE[$cookie_name])) {
        return;
    }

    // Get the custom HTML content from the database
    $html_content = get_option('stark_message_html_content', '<p>This is the default content. Configure me in the Stark Message settings.</p>');
    $html_content = wpautop($html_content); // Automatically add paragraphs

    ?>
<div class="stark-message-popup" role="dialog" aria-hidden="false">
    <button class="stark-message-close" id="stark-message-close" aria-label="Close popup">
        <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>
    <div class="stark-message-content">
        <?php echo wp_kses_post($html_content); ?>
    </div>
</div>
<?php
}
add_action('wp_footer', 'stark_message_display_popup');

/**
 * AJAX handler to set the versioned cookie when the popup is dismissed.
 */
function stark_message_set_cookie() {
    $cookie_version = get_option('stark_message_cookie_version', time());
    $cookie_name = 'stark_message_closed_' . $cookie_version;

    if (!headers_sent()) {
        setcookie($cookie_name, '1', time() + (3 * DAY_IN_SECONDS), '/');
    }
    wp_send_json_success('Cookie set for 3 days.');
}
add_action('wp_ajax_stark_message_close', 'stark_message_set_cookie');
add_action('wp_ajax_nopriv_stark_message_close', 'stark_message_set_cookie');

/**
 * Add admin menu for settings.
 */
function stark_message_add_admin_menu() {
    add_options_page(
        'Stark Message Settings',
        'Stark Message',
        'manage_options',
        'stark-message-settings',
        'stark_message_settings_page'
    );
}
add_action('admin_menu', 'stark_message_add_admin_menu');

/**
 * Render the settings page.
 */
function stark_message_settings_page() {
    if (isset($_POST['stark_message_settings_submit']) && check_admin_referer('stark_message_settings_save')) {
        $enabled = isset($_POST['stark_message_enabled']) ? 1 : 0;
        $html_content = wp_kses_post($_POST['stark_message_html_content']);
        $custom_css = sanitize_textarea_field($_POST['stark_message_css']);
        $page_ids = sanitize_text_field($_POST['stark_message_page_ids']);

        // Save settings
        update_option('stark_message_enabled', $enabled);
        update_option('stark_message_html_content', $html_content);
        update_option('stark_message_css', $custom_css);
        update_option('stark_message_page_ids', $page_ids);

        // Update the cookie version to reset displaying rules
        update_option('stark_message_cookie_version', time());

        echo '<div class="updated"><p>Settings saved. Popup will now be displayed again on all pages.</p></div>';
    }

    $enabled = get_option('stark_message_enabled', false);
    $html_content = get_option('stark_message_html_content', '<p>This is the default content. Configure me in the Stark Message settings.</p>');
    $custom_css = get_option('stark_message_css', '.stark-message-popup {' . PHP_EOL . '  box-shadow: 0px 0px 10px 5px rgba(0,0,0,0.1);' . PHP_EOL . '}');
    $page_ids = get_option('stark_message_page_ids', '');

    ?>
<div class="wrap">
    <h1>Stark Message Settings</h1>
    <form method="post">
        <?php wp_nonce_field('stark_message_settings_save');?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="stark_message_enabled">Enable Popup</label></th>
                <td>
                    <input type="checkbox" id="stark_message_enabled" name="stark_message_enabled" value="1"
                        <?php checked($enabled, 1);?>>
                    <p class="description">Check this box to enable the popup.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="stark_message_html_content">Popup HTML Content</label></th>
                <td><?php wp_editor($html_content, 'stark_message_html_content', array(
        'textarea_name' => 'stark_message_html_content',
        'media_buttons' => false,
        'textarea_rows' => 8,
        'teeny' => true,
    ));?>
                    <p class="description">Use the editor to design the popup content.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="stark_message_css">Custom CSS</label></th>
                <td>
                    <textarea id="stark_message_css" name="stark_message_css" rows="10" class="large-text"
                        style="font-family: monospace; white-space: pre;"><?php echo esc_textarea($custom_css); ?></textarea>
                    <p class="description">Add custom CSS for the popup:<br>
                        <code>.stark-message-popup {}</code><br>
                        <code>.stark-message-content {}</code><br>
                        <code>.stark-message-close {}</code>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="stark_message_page_ids">Page IDs</label></th>
                <td>
                    <input type="text" id="stark_message_page_ids" name="stark_message_page_ids"
                        value="<?php echo esc_attr($page_ids); ?>" class="regular-text">
                    <p class="description">Comma-separated list of page IDs where the popup will appear. Leave empty to
                        show everywhere.</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Save Settings', 'primary', 'stark_message_settings_submit');?>
    </form>
</div>
<?php
}

/**
 * Cleanup plugin data on uninstall.
 */
function stark_message_uninstall() {
    delete_option('stark_message_enabled');
    delete_option('stark_message_html_content');
    delete_option('stark_message_css');
    delete_option('stark_message_page_ids');
    delete_option('stark_message_cookie_version');
}
register_uninstall_hook(__FILE__, 'stark_message_uninstall');