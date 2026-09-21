<?php
/**
 * Plugin Name: GIMA AI Chatbot
 * Description: AI chatbot for GIMA Academy, powered by Groq. Pulls relevant course/page content from the site as context before answering.
 * Version: 1.0.9
 * Author: GIMA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GIMA_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'GIMA_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

// Single source of truth for the version: the "Version:" header above. Keeps
// the plugin list, asset cache-busting query strings, and this constant from
// ever drifting out of sync with each other again.
define( 'GIMA_CHATBOT_VERSION', get_file_data( __FILE__, [ 'Version' => 'Version' ] )['Version'] );

require_once GIMA_CHATBOT_PATH . 'includes/class-db.php';
require_once GIMA_CHATBOT_PATH . 'includes/class-groq-client.php';
require_once GIMA_CHATBOT_PATH . 'includes/class-context-builder.php';
require_once GIMA_CHATBOT_PATH . 'includes/class-chat-handler.php';
require_once GIMA_CHATBOT_PATH . 'includes/class-admin-settings.php';

register_activation_hook( __FILE__, [ 'Gima_Chatbot_DB', 'install' ] );

add_action( 'plugins_loaded', function () {
	new Gima_Chatbot_Admin_Settings();
	new Gima_Chatbot_Handler();
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! get_option( 'gima_chatbot_enabled', '1' ) ) {
		return;
	}

	wp_enqueue_style( 'gima-chatbot', GIMA_CHATBOT_URL . 'assets/css/chatbot.css', [], GIMA_CHATBOT_VERSION );
	wp_enqueue_script( 'gima-chatbot', GIMA_CHATBOT_URL . 'assets/js/chatbot.js', [], GIMA_CHATBOT_VERSION, true );
	$suggested_raw = get_option( 'gima_chatbot_suggested_questions', Gima_Chatbot_Admin_Settings::default_suggested_questions() );
	$suggestions   = array_values( array_filter( array_map( 'trim', explode( "\n", $suggested_raw ) ) ) );

	wp_localize_script( 'gima-chatbot', 'GimaChatbot', [
		'restUrl'     => esc_url_raw( rest_url( 'gima-chatbot/v1/message' ) ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'greeting'    => get_option( 'gima_chatbot_greeting', "Hi! I'm GIMA's assistant. Ask me about our courses or programs." ),
		'title'       => get_option( 'gima_chatbot_title', 'GIMA Assistant' ),
		'teaserText'  => get_option( 'gima_chatbot_teaser_text', '👋 Need help choosing a course?' ),
		'suggestions' => $suggestions,
	] );
} );

add_action( 'wp_footer', function () {
	if ( ! get_option( 'gima_chatbot_enabled', '1' ) ) {
		return;
	}
	echo '<div id="gima-chatbot-root"></div>';
} );
