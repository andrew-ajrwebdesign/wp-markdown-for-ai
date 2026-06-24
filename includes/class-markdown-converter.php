<?php
/**
 * Converts WordPress post content (HTML) to clean Markdown.
 *
 * Uses league/html-to-markdown for robust, block-aware conversion.
 */

namespace AJR\MarkdownForAI;

use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Converter\TableConverter;

defined( 'ABSPATH' ) || exit;

class Markdown_Converter {

	private HtmlConverter $converter;

	public function __construct() {
		$this->converter = new HtmlConverter(
			[
				'bold_style'          => '**',
				'italic_style'        => '_',
				'strip_tags'          => true,
				'remove_nodes'        => 'script style noscript iframe nav header footer form',
				'hard_break'          => false,
				'header_style'        => 'atx',
				'suppress_errors'     => true,
			]
		);

		$this->converter->getEnvironment()->addConverter( new TableConverter() );
	}

	/**
	 * Converts a WP_Post to a Markdown string with YAML-style frontmatter.
	 *
	 * @param \WP_Post $post Post to convert.
	 * @return string
	 */
	public function convert( \WP_Post $post ): string {
		$frontmatter = $this->build_frontmatter( $post );
		$body        = $this->to_markdown( $this->get_content( $post ) );

		return $frontmatter . "\n\n" . $body;
	}

	/**
	 * Converts an HTML string to Markdown.
	 *
	 * @param string $html HTML to convert.
	 * @return string
	 */
	public function to_markdown( string $html ): string {
		if ( empty( trim( $html ) ) ) {
			return '';
		}

		$markdown = $this->converter->convert( $html );

		// Gutenberg can nest <strong> tags, producing ****text**** or worse.
		// Collapse any run of 4+ asterisks on each side down to a single **.
		$markdown = preg_replace( '/\*{4,}(.+?)\*{4,}/s', '**$1**', $markdown );

		// Remove image references with no alt text — decorative/icon images add noise.
		$markdown = preg_replace( '/!\[\]\([^)]+\)\n?/', '', $markdown );

		// Decode any HTML entities that survived conversion (e.g. &amp; in headings).
		$markdown = html_entity_decode( $markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Remove orphaned emphasis markers on their own line.
		$markdown = preg_replace( '/^[_*]{1,2}\s*[_*]{0,2}$/m', '', $markdown );

		// Collapse excessive blank lines.
		$markdown = preg_replace( '/\n{3,}/', "\n\n", $markdown );

		return trim( $markdown );
	}

	/**
	 * Returns YAML frontmatter for the post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function build_frontmatter( \WP_Post $post ): string {
		$categories = wp_get_post_terms( $post->ID, 'category', [ 'fields' => 'names' ] );
		$tags       = wp_get_post_terms( $post->ID, 'post_tag', [ 'fields' => 'names' ] );

		$lines = [
			'---',
			'title: '    . $this->yaml_escape( html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
			'url: '      . esc_url( get_permalink( $post ) ),
			'date: '     . get_the_date( 'Y-m-d', $post ),
			'modified: ' . get_the_modified_date( 'Y-m-d', $post ),
			'author: '   . esc_html( get_the_author_meta( 'display_name', $post->post_author ) ),
			'type: '     . esc_html( $post->post_type ),
		];

		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$lines[] = 'categories: [' . implode( ', ', array_map( 'esc_html', $categories ) ) . ']';
		}

		if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
			$lines[] = 'tags: [' . implode( ', ', array_map( 'esc_html', $tags ) ) . ']';
		}

		$lines[] = '---';

		return implode( "\n", $lines );
	}

	/**
	 * Returns the processed post content ready for Markdown conversion.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function get_content( \WP_Post $post ): string {
		$content = $post->post_content;

		// Strip Gutenberg block comment delimiters.
		$content = preg_replace( '/<!--\s*\/?wp:[^\-].*?-->/s', '', $content );

		// Apply standard WP content filters (shortcodes, embeds, etc.).
		$content = apply_filters( 'the_content', $content );

		return $content;
	}

	/**
	 * Wraps a string in quotes if it contains special YAML characters.
	 *
	 * @param string $value String to escape.
	 * @return string
	 */
	private function yaml_escape( string $value ): string {
		if ( preg_match( '/[:#\[\]{},&*?|<>=!%@`\'"]/', $value ) ) {
			return '"' . str_replace( '"', '\\"', $value ) . '"';
		}
		return $value;
	}
}
