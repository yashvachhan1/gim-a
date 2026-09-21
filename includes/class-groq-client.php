<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gima_Chatbot_Groq_Client {

	private $api_key;
	private $model;

	public function __construct() {
		$this->api_key = get_option( 'gima_chatbot_groq_api_key', '' );
		$this->model   = get_option( 'gima_chatbot_model', 'openai/gpt-oss-120b' );
	}

	public function is_configured() {
		return ! empty( $this->api_key );
	}

	public function chat( array $messages ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'gima_chatbot_no_key', 'Groq API key is not configured.' );
		}

		$response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( [
				'model'       => $this->model,
				'messages'    => $messages,
				'temperature' => 0.4,
				'max_tokens'  => 600,
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$error_message = $body['error']['message'] ?? 'Unknown error from Groq API (HTTP ' . $code . ').';
			return new WP_Error( 'gima_chatbot_api_error', $error_message );
		}

		return $body['choices'][0]['message']['content'] ?? '';
	}
}
