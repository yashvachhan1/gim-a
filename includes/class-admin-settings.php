<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gima_Chatbot_Admin_Settings {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu() {
		add_options_page(
			'GIMA Chatbot',
			'GIMA Chatbot',
			'manage_options',
			'gima-chatbot',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'gima_chatbot', 'gima_chatbot_enabled' );
		register_setting( 'gima_chatbot', 'gima_chatbot_groq_api_key' );
		register_setting( 'gima_chatbot', 'gima_chatbot_model' );
		register_setting( 'gima_chatbot', 'gima_chatbot_title' );
		register_setting( 'gima_chatbot', 'gima_chatbot_greeting' );
		register_setting( 'gima_chatbot', 'gima_chatbot_system_prompt' );
		register_setting( 'gima_chatbot', 'gima_chatbot_teaser_text' );
		register_setting( 'gima_chatbot', 'gima_chatbot_suggested_questions' );
	}

	public static function default_suggested_questions() {
		return "What courses do you offer?\nAre there any free courses?\nHow do I enroll?\nWhat is the course fee?";
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>GIMA AI Chatbot Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'gima_chatbot' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="gima_chatbot_enabled">Enable Chatbot</label></th>
						<td><input type="checkbox" id="gima_chatbot_enabled" name="gima_chatbot_enabled" value="1" <?php checked( get_option( 'gima_chatbot_enabled', '1' ), '1' ); ?> /></td>
					</tr>
					<tr>
						<th><label for="gima_chatbot_groq_api_key">Groq API Key</label></th>
						<td>
							<input type="password" id="gima_chatbot_groq_api_key" name="gima_chatbot_groq_api_key" value="<?php echo esc_attr( get_option( 'gima_chatbot_groq_api_key', '' ) ); ?>" class="regular-text" autocomplete="off" />
							<p class="description">Get your key from <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com/keys</a>.</p>
						</td>
					</tr>
					<tr>
						<th><label for="gima_chatbot_model">Groq Model</label></th>
						<td><input type="text" id="gima_chatbot_model" name="gima_chatbot_model" value="<?php echo esc_attr( get_option( 'gima_chatbot_model', 'openai/gpt-oss-120b' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="gima_chatbot_title">Widget Title</label></th>
						<td><input type="text" id="gima_chatbot_title" name="gima_chatbot_title" value="<?php echo esc_attr( get_option( 'gima_chatbot_title', 'GIMA Assistant' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="gima_chatbot_greeting">Greeting Message</label></th>
						<td><input type="text" id="gima_chatbot_greeting" name="gima_chatbot_greeting" value="<?php echo esc_attr( get_option( 'gima_chatbot_greeting', "Hi! I'm GIMA's assistant. Ask me about our courses or programs." ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="gima_chatbot_system_prompt">System Prompt</label></th>
						<td><textarea id="gima_chatbot_system_prompt" name="gima_chatbot_system_prompt" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'gima_chatbot_system_prompt', "You are a helpful assistant for Global Integrative Medicine Academy (GIMA), a nutrition certification platform for healthcare professionals. Answer questions using the site information provided below. If you don't know the answer from the given information, say so honestly and suggest the visitor contact the GIMA team. Keep answers concise and friendly." ) ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="gima_chatbot_teaser_text">Teaser Popup Text</label></th>
						<td>
							<input type="text" id="gima_chatbot_teaser_text" name="gima_chatbot_teaser_text" value="<?php echo esc_attr( get_option( 'gima_chatbot_teaser_text', "👋 Need help choosing a course?" ) ); ?>" class="regular-text" />
							<p class="description">Shown in a small bubble above the chat launcher before it's opened, along with the quick questions below.</p>
						</td>
					</tr>
					<tr>
						<th><label for="gima_chatbot_suggested_questions">Suggested Questions</label></th>
						<td>
							<textarea id="gima_chatbot_suggested_questions" name="gima_chatbot_suggested_questions" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'gima_chatbot_suggested_questions', self::default_suggested_questions() ) ); ?></textarea>
							<p class="description">One question per line. Shown as clickable quick-reply chips in the teaser popup and at the start of the chat.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
