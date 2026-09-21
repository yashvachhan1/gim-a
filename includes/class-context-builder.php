<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gima_Chatbot_Context_Builder {

	private $stopwords = [
		'the', 'is', 'are', 'and', 'for', 'with', 'that', 'this', 'have', 'has', 'you', 'your',
		'what', 'which', 'how', 'can', 'who', 'when', 'where', 'why', 'does', 'about', 'from',
		'muje', 'mujhe', 'koi', 'krna', 'karna', 'hai', 'he', 'ka', 'ki', 'ke', 'ko', 'se', 'me',
		'mein', 'ho', 'hu', 'hoga', 'kya', 'kaise', 'aap', 'apka', 'apki', 'bhi', 'nahi', 'nhi',
	];

	public function build( $user_message ) {
		$sections   = [];
		$sections[] = $this->get_courses_summary();
		$sections[] = $this->get_relevant_content( $user_message );

		return implode( "\n\n", array_filter( $sections ) );
	}

	/**
	 * LearnPress course post type (lp_course) may not be active on every environment.
	 * Fall back to regular pages/posts whose title mentions "course" so the AI still
	 * has a baseline list to work from.
	 */
	private function get_courses_summary() {
		if ( post_type_exists( 'lp_course' ) ) {
			$courses = get_posts( [
				'post_type'      => 'lp_course',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );
		} else {
			$courses = get_posts( [
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				's'              => 'course',
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );
		}

		if ( empty( $courses ) ) {
			return '';
		}

		$lines = [ 'Course-related pages on the site:' ];
		foreach ( $courses as $course ) {
			$excerpt = wp_strip_all_tags( $course->post_excerpt ?: wp_trim_words( $course->post_content, 25 ) );
			$lines[] = '- [' . $course->post_title . '](' . get_permalink( $course ) . ')' . ( $excerpt ? ': ' . $excerpt : '' );
		}

		return implode( "\n", $lines );
	}

	/**
	 * WordPress core search requires ALL words in the query to match (AND), which
	 * breaks for casual/mixed-language chat messages (e.g. Hinglish with typos).
	 * Instead, pull out meaningful keywords and match on ANY of them (OR).
	 */
	private function get_relevant_content( $user_message ) {
		global $wpdb;

		$keywords = $this->extract_keywords( $user_message );
		if ( empty( $keywords ) ) {
			return '';
		}

		$like_clauses = [];
		$params       = [];
		foreach ( $keywords as $word ) {
			$like           = '%' . $wpdb->esc_like( $word ) . '%';
			$like_clauses[] = '(post_title LIKE %s OR post_content LIKE %s)';
			$params[]       = $like;
			$params[]       = $like;
		}

		$sql = "SELECT ID, post_title, post_content FROM {$wpdb->posts}
			WHERE post_status = 'publish' AND post_type IN ('post','page')
			AND (" . implode( ' OR ', $like_clauses ) . ")
			LIMIT 5";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		if ( empty( $rows ) ) {
			return '';
		}

		$lines = [ 'Relevant page content:' ];
		foreach ( $rows as $row ) {
			$content = wp_strip_all_tags( $row->post_content );
			$content = wp_trim_words( $content, 120 );
			$lines[] = '### [' . $row->post_title . '](' . get_permalink( $row->ID ) . ')' . "\n" . $content;
		}

		return implode( "\n\n", $lines );
	}

	private function extract_keywords( $message ) {
		$message = strtolower( $message );
		$words   = preg_split( '/[^a-z0-9]+/', $message, -1, PREG_SPLIT_NO_EMPTY );

		$words = array_filter( $words, function ( $w ) {
			return strlen( $w ) >= 3 && ! in_array( $w, $this->stopwords, true );
		} );

		return array_slice( array_values( array_unique( $words ) ), 0, 6 );
	}
}
