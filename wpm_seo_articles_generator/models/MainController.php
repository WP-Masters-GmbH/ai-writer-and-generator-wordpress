<?php

/**
 * Main Functions Model
 */
class WPM_SEO_ArticlesGenerator_MainController
{
	/**
	 * Initialize functions
	 */
	public function __construct()
	{
		// Init Functions
		add_action('init', [$this, 'create_new_post']);

		// Ajax Function
		add_action('wp_ajax_activate_plugin', [$this, 'activate_plugin']);
		add_action('wp_ajax_send_articles_to_queue', [$this, 'send_articles_to_queue']);
		add_action('wp_ajax_import_all_posts', [$this, 'import_all_posts']);
	}

	/**
	 * Mass import posts
	 */
	public function import_all_posts()
	{
		// Include Classes
		$WPM_Database = new WPM_SEO_ArticlesGenerator_Database();
		
		if(isset($_POST['publish_status'])) {
			$articles = $WPM_Database->get_not_imported_articles();

			if(!empty($articles)) {
				foreach($articles as $article) {
					// Create Post
					$post_id = wp_insert_post(array(
						'post_title' => $article->article_name,
						'post_content' => $article->article_content,
						'post_status' => sanitize_text_field($_POST['publish_status']),
						'post_date' => date('Y-m-d H:i:s'),
						'post_type' => 'post',
						'post_category' => [$article->category]
					));

					$WPM_Database->update_article($article->article_name, $article->article_content, $post_id, date('Y-m-d H:i:s'));

					wp_send_json( [
						'status'    => 'true',
						'all_count' => count($articles)
					] );
				}
			} else {
				// Get refreshed table data
				ob_start();
				$queued_articles = $WPM_Database->get_articles_results();
				include(WPM_SEO_ARTICLES_GENERATOR_PLUGIN_DIR."/templates/ajax/queued_articles_table.php");
				$table = ob_get_clean();

				wp_send_json( [
					'status'   => 'finished',
					'message' => 'All posts is imported!',
					'html' => $table
				] );
			}
		}
	}

	/**
	 * Create new Post by generated Data
	 */
	public function create_new_post()
	{
		// Include Classes
		$WPM_Database = new WPM_SEO_ArticlesGenerator_Database();
		$WPM_Helpers = new WPM_SEO_ArticlesGenerator_Helpers();

		if(isset($_POST['article_title']) && isset($_POST['article_description']) && isset($_POST['article_category']) && isset($_POST['article_status'])) {

			// Prepare Data
			$post_title = sanitize_text_field($_POST['article_title']);
			$post_description = wp_kses( $_POST['article_description'], array(
				'p' => array(),
				'h2' => array(),
				'h3' => array(),
			));

			$WPM_Database->update_article($post_title, $post_description, 0, date('Y-m-d H:i:s'));
			die;
		} elseif(isset($_POST['article_title']) && isset($_POST['errors_api'])) {
			$post_title = sanitize_text_field($_POST['article_title']);
			$errors = $WPM_Helpers->sanitize_array($_POST['errors_api']);

			$WPM_Database->update_article($post_title, '', 0, date('Y-m-d H:i:s'), $errors);
			die;
		} elseif(isset($_GET['import_ai_post'])) {
			
			$article = $WPM_Database->get_article_by_id(sanitize_text_field($_GET['import_ai_post']));

			if(!empty($article)) {
				// Create Post
				$post_id = wp_insert_post(array(
					'post_title' => $article->article_name,
					'post_content' => $article->article_content,
					'post_status' => 'draft',
					'post_date' => date('Y-m-d H:i:s'),
					'post_type' => 'post',
					'post_category' => [$article->category]
				));

				$WPM_Database->update_article($article->article_name, $article->article_content, $post_id, date('Y-m-d H:i:s'));

				wp_redirect(get_edit_post_link($post_id));
				exit;
			}
		}
	}

	/**
	 * Send request to API
	 */
	public function send_request_to_api($data)
	{
		$response = wp_remote_post("https://ai.wp-masters.com/", [
			'method'  => 'POST',
			"timeout" => 100,
			'headers' => [
				"Content-type" => "application/json",
				"Accept" => "application/json"
			],
			'body' => json_encode($data),
		]);



		return json_decode($response['body'], true);
	}

	/**
	 * Activate plugin by Key
	 */
	public function activate_plugin()
	{
		// Include Classes
		$WPM_Helpers = new WPM_SEO_ArticlesGenerator_Helpers();

		if(!isset($_POST['activation_key']) || $_POST['activation_key'] == '') {
			wp_send_json([
				'status' => 'false',
				'message' => 'License Key is not Set'
			]);
		}

		// Clean and Secure data from POST
		$data = [
			'type' => 'register',
			'activation_key' => sanitize_text_field($_POST['activation_key']),
			'website' => get_home_url()
		];

		// Get Data from API
		$response = $WPM_Helpers->sanitize_array($this->send_request_to_api($data));

		// Save license information
		if(isset($response['user'])) {
			update_option(WPM_SEO_ARTICLES_GENERATOR_ID, serialize($response['user']));
		}

		wp_send_json($response);
	}

	/**
	 * Snd articles to Queue generation API
	 */
	public function send_articles_to_queue()
	{
		// Include Classes
		$WPM_Database = new WPM_SEO_ArticlesGenerator_Database();
		$WPM_Helpers = new WPM_SEO_ArticlesGenerator_Helpers();

		if(!isset($_POST['language']) || !isset($_POST['category']) || !isset($_POST['articles_list'])) {
			return false;
		}

		if(!isset($_POST['activation_key']) || $_POST['activation_key'] == '') {
			wp_send_json([
				'status' => 'false',
				'message' => 'License Key is not Set'
			]);
		}

		// Clean and Secure data from POST
		$articles_list = $WPM_Helpers->sanitize_array(explode(PHP_EOL, $_POST['articles_list']));
		$category = sanitize_text_field($_POST['category']);

		// Check count Words in Titles
		$filtered_list = [];
		foreach($articles_list as $article) {
			if(str_word_count($article) > 0 && str_word_count($article) < 3) {
				wp_send_json([
					'status' => 'false',
					'message' => 'Some titles smaller than 3 words',
				]);
			} elseif(str_word_count($article) >= 3) {
				$filtered_list[] = $article;
			}
		}

		// Prepare for send to API
		$data = [
			'type' => 'articles',
			'activation_key' => sanitize_text_field($_POST['activation_key']),
			'website' => get_home_url(),
			'language' => sanitize_text_field($_POST['language']),
			'publish_status' => sanitize_text_field($_POST['publish_status']),
			'category' => $category,
			'articles_list' => $articles_list,
		];

		// Get Data from API
		$response = $WPM_Helpers->sanitize_array($this->send_request_to_api($data));

		// If success response save articles name to see status
		if(isset($response['status']) && $response['status'] == 'true') {
			foreach($articles_list as $article) {
				$WPM_Database->insert_article($article, $category);
			}

			// Save license information
			if(isset($response['user'])) {
				update_option(WPM_SEO_ARTICLES_GENERATOR_ID, serialize($response['user']));
			}
		}

		// Get refreshed table data
		ob_start();
		$queued_articles = $WPM_Database->get_articles_results();
		include(WPM_SEO_ARTICLES_GENERATOR_PLUGIN_DIR."/templates/ajax/queued_articles_table.php");
		$table = ob_get_clean();

		wp_send_json([
			'status' => $response['status'],
			'message' => $response['message'],
			'user' => isset($response['user']) ? $response['user'] : [],
			'table' => $table
		]);
	}
}