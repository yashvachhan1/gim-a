<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gima_Chatbot_DB {

	public static function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$conversations   = $wpdb->prefix . 'gima_chatbot_conversations';
		$messages        = $wpdb->prefix . 'gima_chatbot_messages';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql1 = "CREATE TABLE $conversations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id)
		) $charset_collate;";
		dbDelta( $sql1 );

		$sql2 = "CREATE TABLE $messages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT UNSIGNED NOT NULL,
			role VARCHAR(16) NOT NULL,
			message LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY conversation_id (conversation_id)
		) $charset_collate;";
		dbDelta( $sql2 );
	}

	public static function get_or_create_conversation( $session_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'gima_chatbot_conversations';

		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE session_id = %s", $session_id ) );
		if ( $id ) {
			return (int) $id;
		}

		$wpdb->insert( $table, [
			'session_id' => $session_id,
			'user_id'    => get_current_user_id() ?: null,
			'created_at' => current_time( 'mysql' ),
		] );

		return (int) $wpdb->insert_id;
	}

	public static function add_message( $conversation_id, $role, $message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'gima_chatbot_messages';

		$wpdb->insert( $table, [
			'conversation_id' => $conversation_id,
			'role'            => $role,
			'message'         => $message,
			'created_at'      => current_time( 'mysql' ),
		] );
	}

	public static function get_recent_messages( $conversation_id, $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'gima_chatbot_messages';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, message FROM $table WHERE conversation_id = %d ORDER BY id DESC LIMIT %d",
				$conversation_id,
				$limit
			),
			ARRAY_A
		);

		return array_reverse( $rows ?: [] );
	}
}
