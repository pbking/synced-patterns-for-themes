<?php
/**
 * Class to handle the synced patterns
 * 
 * Usage: put pattern files in a /synced-patterns directory.
 * Use the SAME format as patterns in the /patterns directory.
 * 
 * Patterns in this folder will have a SYNCED PATTERN post created and be available to users as SYNCED PATTERNS.
 * 
 * If a user edits this pattern in the editor, the post will be updated and used throughout the site.
 * 
 * If the theme changes the pattern file it will be updated in the editor.
 * 
 * These themes will ALSO be registered as UNSYNCED patterns which are just a reference to the SYNCED pattern.
 * This allows the patterns to be used in templates by referencing their slug.  
 * These unsynced patterns are "hidden" and not shown to the user.
 * 
 * IMPORTANT: Making changes in the THEME version of these patterns will CLOBBER the database version of the pattern.
 * Making changes in the editor will clobber the theme version of the pattern, changing the file.
 * 
 * Latest change wins.
 * 
 */
class Synced_Patterns_For_Themes {

	/**
	 * Constructor
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'register_patterns' ) );
	}

	/**
	 * Render a synced pattern file.  Since this is a PHP file, we can just include it which causes the logic bits to run.
	 */
	private function render_synced_pattern($pattern_file) {
		ob_start();
		include $pattern_file;
		return ob_get_clean();
	}

	/**
	 * Register the synced patterns
	 */
	public function register_patterns() {

		$pattern_files = glob(get_stylesheet_directory() . '/synced-patterns/*.php');

		foreach ($pattern_files as $pattern_file) {

			// get the contents of the file
			$pattern_content = file_get_contents($pattern_file);
			$file_modified_timestamp = filemtime($pattern_file);
			$pattern_slug = '';
			$pattern_title = '';

			// we can do this better... wordpress has a parser for this.  I just can't think of it right now.
			if (preg_match('/\btitle\s*:\s*(.*)/i', $pattern_content, $matches)) {
				$pattern_title = trim($matches[1]);
			}
			if (preg_match('/\bslug\s*:\s*(.*)/i', $pattern_content, $matches)) {
				$pattern_slug = trim($matches[1]);
			}
			if (empty($pattern_title) || empty($pattern_slug)) {
				continue;
			}

			$post_id = post_exists($pattern_title, '', '', 'wp_block');

			if ($post_id) {

				$post_modified_timestamp = strtotime(get_post_field('post_modified', $post_id));

				// if the pattern file was updated after the post was modified, update the post
				if ($file_modified_timestamp > $post_modified_timestamp) {
					wp_update_post(array(
						'ID' => $post_id,
						'post_content' => $this->render_synced_pattern($pattern_file),
					));
				} 

			}

			// create a new post
			else {
				$post_id = wp_insert_post(array(
					'post_title' => $pattern_title,
					'post_content' => $this->render_synced_pattern($pattern_file),
					'post_name' => $pattern_slug,
					'post_status' => 'publish',
					'comment_status' => 'closed',
					'ping_status' => 'closed',
					'post_type' => 'wp_block',
				));
			}

			// add the pattern as an UNsynced pattern TOO so that it can be used in templates.
			// this pattern just injects a synced pattern block as the content.
			register_block_pattern(
				$pattern_slug,
				array(
					'title'   => $pattern_title,
					'inserter' => false,
					'content' => '<!-- wp:block {"ref":' . $post_id . '} /-->',
				)
			);
		}
	}
}