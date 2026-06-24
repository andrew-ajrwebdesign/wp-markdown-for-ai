<?php
/**
 * Generates and serves /llms.txt and /llms-full.txt endpoints.
 *
 * llms.txt      — index of all public content with titles, URLs, and excerpts.
 * llms-full.txt — same index with full Markdown content inlined (cached; large
 *                 sites should rely on the cached version rather than live generation).
 */

namespace AJR\MarkdownForAI;

defined( 'ABSPATH' ) || exit;

class Llms_Txt {

	/**
	 * Maximum number of posts processed per post type for llms-full.txt.
	 * Prevents memory exhaustion on very large sites.
	 */
	private const FULL_POST_LIMIT = 200;

	public function register(): void {
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ] );
		add_filter( 'robots_txt', [ $this, 'add_robots_pointer' ], 10, 2 );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'wpmai_llms';
		return $vars;
	}

	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?wpmai_llms=full', 'top' );
		add_rewrite_rule( '^llms\.txt$', 'index.php?wpmai_llms=index', 'top' );
	}

	public function maybe_serve(): void {
		$llms = get_query_var( 'wpmai_llms' );

		if ( ! $llms ) {
			return;
		}

		$include_content = ( 'full' === $llms );

		// Respect endpoint toggles.
		if ( $include_content && ! Settings::get_option( 'enable_llms_full', true ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'This endpoint is disabled.', 'wp-markdown-for-ai' ), 404 );
		}

		if ( ! $include_content && ! Settings::get_option( 'enable_llms_index', true ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'This endpoint is disabled.', 'wp-markdown-for-ai' ), 404 );
		}

		$cache_key = $include_content ? 'llms_full' : 'llms_index';
		$cached    = Cache::get( $cache_key );

		$ttl = $this->get_ttl();

		if ( false === $cached ) {
			$cached = $this->build( $include_content );
			Cache::set( $cache_key, $cached, $ttl );
		}

		$this->send_headers( $ttl );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cached;
		exit;
	}

	/**
	 * Sends appropriate HTTP headers for the plain-text response.
	 *
	 * @param int $ttl Cache TTL in seconds, used for Cache-Control max-age.
	 */
	private function send_headers( int $ttl ): void {
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: public, max-age=' . $ttl );
	}

	/**
	 * Returns the configured cache TTL in seconds.
	 *
	 * @return int
	 */
	private function get_ttl(): int {
		$hours = (int) Settings::get_option( 'cache_ttl_hours', 12 );
		return max( 1, $hours ) * HOUR_IN_SECONDS;
	}

	/**
	 * Builds the llms.txt content string.
	 *
	 * @param bool $include_content Whether to inline full Markdown content.
	 * @return string
	 */
	private function build( bool $include_content = false ): string {
		$site_name       = get_bloginfo( 'name' );
		$site_desc       = get_bloginfo( 'description' );
		$site_url        = home_url();
		$post_types      = Settings::get_option( 'post_types', [ 'post', 'page' ] );
		$excluded        = Settings::get_option( 'excluded_ids', [] );
		$ai_instructions = trim( (string) Settings::get_option( 'ai_instructions', '' ) );
		$converter       = $include_content ? new Markdown_Converter() : null;
		$limit           = $include_content
			? (int) Settings::get_option( 'full_post_limit', self::FULL_POST_LIMIT )
			: 500;

		$output  = "# {$site_name}\n\n";
		$output .= "> {$site_desc}\n\n";
		$output .= "Site: {$site_url}\n";
		$output .= 'Generated: ' . gmdate( 'Y-m-d\TH:i:s\Z' ) . "\n\n";

		if ( $include_content ) {
			$output .= "This file contains the full content of all public pages and posts on this site in Markdown format.\n\n";
		} else {
			$output .= "This file lists all public content on this site. Append ?format=markdown to any URL to retrieve its content as Markdown.\n\n";
		}

		if ( $ai_instructions ) {
			$output .= "## Instructions\n\n";
			$output .= $ai_instructions . "\n\n";
		}

		$output .= "---\n\n";

		$allowed_langs = Settings::get_option( 'polylang_languages', [] );

		foreach ( $post_types as $post_type ) {
			$query_args = [
				'post_type'              => sanitize_key( $post_type ),
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'no_found_rows'          => true,
				'update_post_meta_cache' => true, // Required for Indexability SEO meta checks.
				'update_post_term_cache' => ! $include_content,
				'orderby'                => 'date',
				'order'                  => 'DESC',
			];

			if ( ! empty( $excluded ) ) {
				$query_args['post__not_in'] = array_map( 'absint', $excluded );
			}

			// Polylang: filter by selected languages if any are specified.
			if ( ! empty( $allowed_langs ) && function_exists( 'pll_get_post_language' ) ) {
				$query_args['lang'] = implode( ',', array_map( 'sanitize_key', $allowed_langs ) );
			}

			$posts = new \WP_Query( $query_args );

			if ( ! $posts->have_posts() ) {
				continue;
			}

			$type_obj = get_post_type_object( $post_type );
			$label    = $type_obj ? $type_obj->labels->name : ucfirst( $post_type );

			// Build section content first — skip the heading if nothing passes indexability.
			$section = '';

			foreach ( $posts->posts as $post ) {
				if ( ! Indexability::is_indexable( $post ) ) {
					continue;
				}

				$title        = html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$url          = get_permalink( $post );
				$markdown_url = add_query_arg( 'format', 'markdown', $url );
				$excerpt      = $this->get_excerpt( $post );

				if ( $include_content && $converter ) {
					$cached_md = Cache::get( Cache::post_key( $post->ID ) );

					if ( false === $cached_md ) {
						$cached_md = $converter->convert( $post );
						Cache::set( Cache::post_key( $post->ID ), $cached_md, $this->get_ttl() );
					}

					$section .= "### {$title}\n\n";
					$section .= "URL: {$url}\n\n";
					$section .= $cached_md . "\n\n";
					$section .= "---\n\n";
				} else {
					$section .= "- [{$title}]({$markdown_url})";
					if ( $excerpt ) {
						$section .= ': ' . $excerpt;
					}
					$section .= "\n";
				}
			}

			if ( $section ) {
				$output .= "## {$label}\n\n" . $section . "\n";
			}

			wp_reset_postdata();
		}

		return $output;
	}

	/**
	 * Returns a short plain-text excerpt for a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function get_excerpt( \WP_Post $post ): string {
		$length = (int) Settings::get_option( 'excerpt_length', 20 );

		if ( $post->post_excerpt ) {
			return wp_trim_words( wp_strip_all_tags( $post->post_excerpt ), $length, '...' );
		}

		$content = wp_strip_all_tags( $post->post_content );
		$content = preg_replace( '/\s+/', ' ', $content );

		return wp_trim_words( $content, $length, '...' );
	}

	/**
	 * Appends an llms.txt pointer to the WordPress-generated robots.txt.
	 *
	 * @param string $output  Current robots.txt content.
	 * @param bool   $public  Whether the site is public.
	 * @return string
	 */
	public function add_robots_pointer( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}

		$output .= "\n# AI Agents\n";
		$output .= 'X-Llms-Txt: ' . esc_url( home_url( '/llms.txt' ) ) . "\n";

		return $output;
	}
}
