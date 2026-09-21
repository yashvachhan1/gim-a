<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gima_Chatbot_Handler {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( 'gima-chatbot/v1', '/message', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_message' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'message'    => [ 'required' => true, 'type' => 'string' ],
				'session_id' => [ 'required' => true, 'type' => 'string' ],
			],
		] );
	}

	public function handle_message( WP_REST_Request $request ) {
		$message    = sanitize_textarea_field( $request->get_param( 'message' ) );
		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) );

		if ( empty( $message ) || empty( $session_id ) ) {
			return new WP_REST_Response( [ 'error' => 'Missing message or session_id.' ], 400 );
		}

		if ( strlen( $message ) > 2000 ) {
			return new WP_REST_Response( [ 'error' => 'Message too long.' ], 400 );
		}

		$client = new Gima_Chatbot_Groq_Client();
		if ( ! $client->is_configured() ) {
			return new WP_REST_Response( [
				'reply' => 'The chatbot is not fully set up yet. Please add a Groq API key in the WordPress admin under Settings → GIMA Chatbot.',
			], 200 );
		}

		$conversation_id = Gima_Chatbot_DB::get_or_create_conversation( $session_id );
		Gima_Chatbot_DB::add_message( $conversation_id, 'user', $message );

		$history = Gima_Chatbot_DB::get_recent_messages( $conversation_id, 10 );

		$context_builder = new Gima_Chatbot_Context_Builder();
		$context          = $context_builder->build( $message );

		$system_prompt = get_option( 'gima_chatbot_system_prompt',
			"You are a helpful assistant for Global Integrative Medicine Academy (GIMA), a nutrition certification platform for healthcare professionals. Answer questions using the site information provided below. If you don't know the answer from the given information, say so honestly and suggest the visitor contact the GIMA team. Keep answers concise and friendly.\n\n" .
			"Formatting rules for this chat widget (narrow ~300px panel):\n" .
			"- When you mention a specific page, course, or action the visitor can take (like enrolling or reading more), always include it as a Markdown link using the exact URL given in the site information: [Enroll Now](https://example.com/page/). Never write a bare URL or an angle-bracket link like <https://example.com>.\n" .
			"- Avoid wide tables. If you use a table, keep it to at most 2 short columns, or prefer a bullet list instead — the panel is narrow and long table cells are hard to read."
		);

		if ( $context ) {
			$system_prompt .= "\n\nSite information you can use to answer:\n" . $context;
		}

		$messages = [ [ 'role' => 'system', 'content' => $system_prompt ] ];
		foreach ( $history as $row ) {
			$messages[] = [
				'role'    => $row['role'] === 'assistant' ? 'assistant' : 'user',
				'content' => $row['message'],
			];
		}

		$reply = $client->chat( $messages );

		if ( is_wp_error( $reply ) ) {
			return new WP_REST_Response( [
				'reply' => "Sorry, I couldn't process that right now. Please try again in a moment.",
				'error' => $reply->get_error_message(),
			], 200 );
		}

		Gima_Chatbot_DB::add_message( $conversation_id, 'assistant', $reply );

		return new WP_REST_Response( [ 'reply' => $reply ], 200 );
	}
}
