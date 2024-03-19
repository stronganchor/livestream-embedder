<?php
/*
Plugin Name: * YouTube Live Stream Embed
Plugin URI: https://github.com/stronganchor/youtube-livestream-embed/
Description: Embeds a YouTube live stream using a shortcode.
Version: 1.0.3
Author: Strong Anchor Tech
Author URI: https://stronganchortech.com/
*/

// Add settings page
function youtube_live_stream_settings_page() {
    add_options_page(
        'YouTube Live Stream Settings',
        'YouTube Live Stream',
        'manage_options',
        'youtube-live-stream-settings',
        'youtube_live_stream_settings_page_content'
    );
}
add_action('admin_menu', 'youtube_live_stream_settings_page');

// Add settings link to plugin page
function youtube_live_stream_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=youtube-live-stream-settings') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'youtube_live_stream_settings_link');

// Settings page content
function youtube_live_stream_settings_page_content() {
    ?>
    <div class="wrap">
        <h1>YouTube Live Stream Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('youtube_live_stream_settings');
            do_settings_sections('youtube-live-stream-settings');
            submit_button();
            ?>
        </form>
        <h2>Shortcode Usage</h2>
        <p>To embed a YouTube live stream or the most recent live stream video, use the following shortcode:</p>
        <code>[youtube_live_stream channel_id="CHANNEL_ID"]</code>
		
        <p>If you have set the channel ID sitewide setting, you can use the shortcode without entering the ID:</p>
        <code>[youtube_live_stream]</code>
        <p>Replace <code>CHANNEL_ID</code> with the actual YouTube channel ID (e.g. UCabcdefghijklmnopqrstuvwx).</p>
        <h3>How to Find the Channel ID</h3>
        <ol>
            <li>Go to the YouTube channel's page.</li>
            <li>Look at the URL in your browser's address bar. The channel ID is the string of characters after the "/channel/" part of the URL.</li>
            <li>For example, if the URL is <code>https://www.youtube.com/channel/UCxyzXXXXXXXXXXXXXXXXXXX</code>, the channel ID is <code>UCxyzXXXXXXXXXXXXXXXXXXX</code>.</li>
            <li>If the channel uses a custom URL (e.g., <code>https://www.youtube.com/c/somechannelname</code>), you can still find the channel ID by viewing the page source and searching for <code>"channelId"</code> (including the quotes).</li>
        </ol>
    </div>
    <?php
}

// Register settings
function youtube_live_stream_register_settings() {
    register_setting('youtube_live_stream_settings', 'youtube_live_stream_api_key');
    register_setting('youtube_live_stream_settings', 'youtube_live_stream_default_channel');
    add_settings_section(
        'youtube_live_stream_section',
        'API Key',
        'youtube_live_stream_section_callback',
        'youtube-live-stream-settings'
    );
    add_settings_field(
        'youtube_live_stream_api_key',
        'YouTube Data API Key',
        'youtube_live_stream_api_key_callback',
        'youtube-live-stream-settings',
        'youtube_live_stream_section'
    );
    add_settings_field(
        'youtube_live_stream_default_channel',
        'Default Channel ID',
        'youtube_live_stream_default_channel_callback',
        'youtube-live-stream-settings',
        'youtube_live_stream_section'
    );
}
add_action('admin_init', 'youtube_live_stream_register_settings');

// Section callback
function youtube_live_stream_section_callback() {
    echo '<p>Enter your YouTube Data API key and default channel ID below:</p>';
}

// API key field callback
function youtube_live_stream_api_key_callback() {
    $api_key = get_option('youtube_live_stream_api_key');
    echo '<input type="text" name="youtube_live_stream_api_key" value="' . esc_attr($api_key) . '" size="50" />';
}

// Default channel field callback
function youtube_live_stream_default_channel_callback() {
    $default_channel = get_option('youtube_live_stream_default_channel');
    echo '<input type="text" name="youtube_live_stream_default_channel" value="' . esc_attr($default_channel) . '" size="50" />';
}

// Shortcode callback
function youtube_live_stream_shortcode($atts) {
    $channel_id = isset($atts['channel_id']) ? $atts['channel_id'] : get_option('youtube_live_stream_default_channel');
    $api_key = get_option('youtube_live_stream_api_key');

    if (empty($channel_id)) {
        return '<p>Please provide a channel ID or set a default channel in the plugin settings.</p>';
    }

    if (empty($api_key)) {
        return '<p>Please provide a valid YouTube Data API key in the plugin settings.</p>';
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
            var channelId = "' . $channel_id . '";
            fetch("https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" + channelId + "&eventType=live&type=video&key=' . $api_key . '")
                .then(response => response.json())
                .then(data => {
                    if (data.items && data.items.length > 0) {
                        var videoId = data.items[0].id.videoId;
                        player.loadVideoById(videoId);
                    } else {
                        fetch("https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" + channelId + "&order=date&type=video&key=' . $api_key . '")
                            .then(response => response.json())
                            .then(data => {
                                if (data.items && data.items.length > 0) {
                                    var videoId = data.items[0].id.videoId;
                                    player.loadVideoById(videoId);
                                }
                            });
                    }
                });
        }
    </script>';

    return $embed_code;
}
add_shortcode('youtube_live_stream', 'youtube_live_stream_shortcode');
