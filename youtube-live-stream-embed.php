<?php
/*
Plugin Name: YouTube Live Stream Embed
Plugin URI: https://github.com/stronganchor/youtube-livestream-embed/
Description: Embeds a YouTube live stream using a shortcode.
Version: 1.0.0
Author: Strong Anchor Tech
Author URI: https://stronganchortech.com/
*/

// Add settings page
function youtube_live_stream_settings_page() {
    add_options_page(
        __('YouTube Live Stream Settings', 'youtube-live-stream'),
        __('YouTube Live Stream', 'youtube-live-stream'),
        'manage_options',
        'youtube-live-stream-settings',
        'youtube_live_stream_settings_page_content'
    );
}
add_action('admin_menu', 'youtube_live_stream_settings_page');

// Add settings link to plugin page
function youtube_live_stream_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=youtube-live-stream-settings') . '">' . __('Settings', 'youtube-live-stream') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'youtube_live_stream_settings_link');

// Settings page content
function youtube_live_stream_settings_page_content() {
    ?>
    <div class="wrap">
        <h1><?php _e('YouTube Live Stream Settings', 'youtube-live-stream'); ?></h1>
        <form method="post" action="options.php">
            <?php
            // Add nonce for security
            wp_nonce_field('youtube_live_stream_settings_action', 'youtube_live_stream_nonce');
            settings_fields('youtube_live_stream_settings');
            do_settings_sections('youtube-live-stream-settings');
            submit_button();
            ?>
        </form>
        <h2><?php _e('Shortcode Usage', 'youtube-live-stream'); ?></h2>
        <p><?php _e('To embed a YouTube live stream or the most recent live stream video, use the following shortcode:', 'youtube-live-stream'); ?></p>
        <code>[youtube_live_stream channel_id="CHANNEL_ID"]</code>

        <p><?php _e('If you have set the channel ID sitewide setting, you can use the shortcode without entering the ID:', 'youtube-live-stream'); ?></p>
        <code>[youtube_live_stream]</code>
        <p><?php _e('Replace CHANNEL_ID with the actual YouTube channel ID (e.g., UCabcdefghijklmnopqrstuvwx).', 'youtube-live-stream'); ?></p>
        <h3><?php _e('How to Find the Channel ID', 'youtube-live-stream'); ?></h3>
        <ol>
            <li><?php _e('Go to the YouTube channel\'s page.', 'youtube-live-stream'); ?></li>
            <li><?php _e('Look at the URL in your browser\'s address bar. The channel ID is the string of characters after the "/channel/" part of the URL.', 'youtube-live-stream'); ?></li>
            <li><?php _e('For example, if the URL is https://www.youtube.com/channel/UCxyzXXXXXXXXXXXXXXXXXXX, the channel ID is UCxyzXXXXXXXXXXXXXXXXXXX.', 'youtube-live-stream'); ?></li>
            <li><?php _e('If the channel uses a custom URL (e.g., https://www.youtube.com/c/somechannelname), you can still find the channel ID by viewing the page source and searching for "channelId".', 'youtube-live-stream'); ?></li>
        </ol>
    </div>
    <?php
}

// Register settings
function youtube_live_stream_register_settings() {
    register_setting('youtube_live_stream_settings', 'youtube_live_stream_api_key', 'sanitize_text_field');
    register_setting('youtube_live_stream_settings', 'youtube_live_stream_default_channel', 'sanitize_text_field');

    add_settings_section(
        'youtube_live_stream_section',
        __('API Key', 'youtube-live-stream'),
        'youtube_live_stream_section_callback',
        'youtube-live-stream-settings'
    );

    add_settings_field(
        'youtube_live_stream_api_key',
        __('YouTube Data API Key', 'youtube-live-stream'),
        'youtube_live_stream_api_key_callback',
        'youtube-live-stream-settings',
        'youtube_live_stream_section'
    );

    add_settings_field(
        'youtube_live_stream_default_channel',
        __('Default Channel ID', 'youtube-live-stream'),
        'youtube_live_stream_default_channel_callback',
        'youtube-live-stream-settings',
        'youtube_live_stream_section'
    );
}
add_action('admin_init', 'youtube_live_stream_register_settings');

// Section callback
function youtube_live_stream_section_callback() {
    echo '<p>' . __('Enter your YouTube Data API key and default channel ID below:', 'youtube-live-stream') . '</p>';
}

// API key field callback
function youtube_live_stream_api_key_callback() {
    $api_key = sanitize_text_field(get_option('youtube_live_stream_api_key'));
    echo '<input type="text" name="youtube_live_stream_api_key" value="' . esc_attr($api_key) . '" size="50" />';
}

// Default channel field callback
function youtube_live_stream_default_channel_callback() {
    $default_channel = sanitize_text_field(get_option('youtube_live_stream_default_channel'));
    echo '<input type="text" name="youtube_live_stream_default_channel" value="' . esc_attr($default_channel) . '" size="50" />';
}

// Shortcode callback
function youtube_live_stream_shortcode($atts) {
    $channel_id = isset($atts['channel_id']) ? sanitize_text_field($atts['channel_id']) : sanitize_text_field(get_option('youtube_live_stream_default_channel'));
    $api_key = sanitize_text_field(get_option('youtube_live_stream_api_key'));

    if (empty($channel_id)) {
        return '<p>' . __('Please provide a channel ID or set a default channel in the plugin settings.', 'youtube-live-stream') . '</p>';
    }

    if (empty($api_key)) {
        return '<p>' . __('Please provide a valid YouTube Data API key in the plugin settings.', 'youtube-live-stream') . '</p>';
    }

    $embed_code = '<div id="livestream-container" style="height:360px; width:640px"></div>';
    $embed_code .= '<script src="https://www.youtube.com/iframe_api"></script>';
    $embed_code .= '<script>
        var player;

        function onYouTubeIframeAPIReady() {
            player = new YT.Player("livestream-container", {
                height: "360",
                width: "640",
                videoId: "",
                events: {
                    "onReady": onPlayerReady
                }
            });
        }

        function onPlayerReady(event) {
            var channelId = "' . esc_js($channel_id) . '";
            fetch("https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" + channelId + "&eventType=live&type=video&key=' . esc_js($api_key) . '")
                .then(response => response.json())
                .then(data => {
                    if (data.items && data.items.length > 0) {
                        var videoId = data.items[0].id.videoId;
                        player.loadVideoById(videoId);
                    } else {
                        fetch("https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" + channelId + "&order=date&type=video&key=' . esc_js($api_key) . '")
                            .then(response => response.json())
                            .then(data => {
                                if (data.items && data.items.length > 0) {
                                    var videoId = data.items[0].id.videoId;
                                    player.loadVideoById(videoId);
                                }
                            })
                            .catch(error => {
                                document.getElementById("livestream-container").innerHTML = "<p>' . esc_js(__('Unable to load live stream. Please check your API key and Channel ID.', 'youtube-live-stream')) . '</p>";
                            });
                    }
                })
                .catch(error => {
                    document.getElementById("livestream-container").innerHTML = "<p>' . esc_js(__('Unable to load live stream. Please check your API key and Channel ID.', 'youtube-live-stream')) . '</p>";
                });
        }
    </script>';

    return $embed_code;
}
add_shortcode('youtube_live_stream', 'youtube_live_stream_shortcode');
