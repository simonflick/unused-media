<?php

/**
 * @package Unused_Media
 * @version 0.1.0
 */
/*
Plugin Name: Unused Media
Description: Find out which media files are unused in post content, global options or post meta (currently supports attachments and all ACF extra fields).
Author: sun concept
Version: 0.1.0
Author URI: https://www.sun-concept.de
*/

function unused_media_options_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

	include_once('html/options-page.php');
}

function unused_media_options_page()
{
	add_submenu_page(
		'tools.php',
		'Unused Media',
		'Unused Media',
		'manage_options',
		'unused-media',
		'unused_media_options_page_html'
	);
}
add_action('admin_menu', 'unused_media_options_page');

function unused_media_enqueue($hook) {
    if($hook !== 'tools_page_unused-media') {
        return;
    }

    wp_register_style('unused_media_options_page_jquery_ui_style', plugins_url('assets/jquery-ui.min.css', __FILE__));
    wp_enqueue_style('unused_media_options_page_jquery_ui_style');
    wp_register_style('unused_media_options_page_style', plugins_url('assets/styles.css', __FILE__));
    wp_enqueue_style('unused_media_options_page_style');
    wp_enqueue_script('unused_media_options_page_sorttable_script', plugin_dir_url(__FILE__) . 'assets/sorttable.js', [], '1.0');
    wp_enqueue_script('unused_media_options_page_jquery_ui_script', plugin_dir_url(__FILE__) . 'assets/jquery-ui.min.js', [], '1.0');
    wp_enqueue_script('unused_media_options_page_script', plugin_dir_url(__FILE__) . 'assets/script.js', [], '1.0');
}
add_action('admin_enqueue_scripts', 'unused_media_enqueue');