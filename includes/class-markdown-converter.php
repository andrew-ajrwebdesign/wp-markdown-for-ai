<?php
/**
 * Converts WordPress post content (HTML) to clean Markdown.
 */

namespace AJR\MarkdownForAI;

defined( 'ABSPATH' ) || exit;

class Markdown_Converter {

	/**
	 * Converts a WP_Post to a Markdown string with YAML-style frontmatter.
	 */
	public function convert( \WP_Post $post ): string {
		$frontmatter = $this->build_frontmatter( $post );
		$body        = $this->html_to_markdown( $this->get_content( $post ) );

		return $frontmatter . "\n\n" . $body;
	}

	/**
	 * Returns frontmatter block for the post.
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
	 * Returns filtered, processed post content.
	 */
	private function get_content( \WP_Post $post ): string {
		$content = $post->post_content;

		// Strip Gutenberg block comment delimiters.
		$content = preg_replace( '/<!--\s*wp:[^\-].*?-->/s', '', $content );

		// Apply standard WP content filters (shortcodes, embeds, etc.).
		$content = apply_filters( 'the_content', $content );

		return $content;
	}

	/**
	 * Converts an HTML string to Markdown.
	 *
	 * Handles the most common HTML elements produced by WordPress/Gutenberg.
	 */
	public function html_to_markdown( string $html ): string {
		if ( empty( trim( $html ) ) ) {
			return '';
		}

		// Normalise line endings.
		$md = str_replace( "\r\n", "\n", $html );

		// Headings.
		$md = preg_replace( '/<h1[^>]*>(.*?)<\/h1>/si', '# $1', $md );
		$md = preg_replace( '/<h2[^>]*>(.*?)<\/h2>/si', '## $1', $md );
		$md = preg_replace( '/<h3[^>]*>(.*?)<\/h3>/si', '### $1', $md );
		$md = preg_replace( '/<h4[^>]*>(.*?)<\/h4>/si', '#### $1', $md );
		$md = preg_replace( '/<h5[^>]*>(.*?)<\/h5>/si', '##### $1', $md );
		$md = preg_replace( '/<h6[^>]*>(.*?)<\/h6>/si', '###### $1', $md );

		// Bold / italic — skip empty or whitespace-only tags.
		$md = preg_replace_callback( '/<(strong|b)[^>]*>(.*?)<\/(strong|b)>/si', function ( $m ) {
			$inner = trim( strip_tags( $m[2] ) );
			return $inner !== '' ? '**' . $m[2] . '**' : '';
		}, $md );
		$md = preg_replace_callback( '/<(em|i)[^>]*>(.*?)<\/(em|i)>/si', function ( $m ) {
			$inner = trim( strip_tags( $m[2] ) );
			return $inner !== '' ? '_' . $m[2] . '_' : '';
		}, $md );

		// Inline code and code blocks.
		$md = preg_replace( '/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/si', "\n```\n$1\n```\n", $md );
		$md = preg_replace( '/<code[^>]*>(.*?)<\/code>/si', '`$1`', $md );

		// Blockquotes.
		$md = preg_replace_callback(
			'/<blockquote[^>]*>(.*?)<\/blockquote>/si',
			function ( $m ) {
				$inner = trim( strip_tags( $m[1] ) );
				$lines = explode( "\n", $inner );
				return implode( "\n", array_map( fn( $l ) => '> ' . trim( $l ), $lines ) ) . "\n";
			},
			$md
		);

		// Images — skip decorative icons; only include images with meaningful alt text.
		$md = preg_replace_callback(
			'/<img[^>]+>/si',
			function ( $m ) {
				preg_match( '/src=["\']([^"\']+)["\']/i', $m[0], $src );
				preg_match( '/alt=["\']([^"\']*)["\']?/i', $m[0], $alt );
				$src_val = isset( $src[1] ) ? esc_url( $src[1] ) : '';
				$alt_val = isset( $alt[1] ) ? trim( $alt[1] ) : '';

				// Drop images with no alt text or generic icon labels.
				if ( '' === $alt_val || preg_match( '/\bicon\b/i', $alt_val ) ) {
					return '';
				}

				return '![' . esc_html( $alt_val ) . '](' . $src_val . ')';
			},
			$md
		);

		// Links.
		$md = preg_replace( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', '[$2]($1)', $md );

		// Unordered lists.
		$md = preg_replace_callback(
			'/<ul[^>]*>(.*?)<\/ul>/si',
			function ( $m ) {
				$items = [];
				preg_match_all( '/<li[^>]*>(.*?)<\/li>/si', $m[1], $li );
				foreach ( $li[1] as $item ) {
					$items[] = '- ' . trim( strip_tags( $item ) );
				}
				return implode( "\n", $items ) . "\n";
			},
			$md
		);

		// Ordered lists.
		$md = preg_replace_callback(
			'/<ol[^>]*>(.*?)<\/ol>/si',
			function ( $m ) {
				$items = [];
				$i     = 1;
				preg_match_all( '/<li[^>]*>(.*?)<\/li>/si', $m[1], $li );
				foreach ( $li[1] as $item ) {
					$items[] = $i . '. ' . trim( strip_tags( $item ) );
					$i++;
				}
				return implode( "\n", $items ) . "\n";
			},
			$md
		);

		// Horizontal rules.
		$md = preg_replace( '/<hr[^>]*>/si', "\n---\n", $md );

		// Paragraphs — convert to double-newline separated blocks.
		$md = preg_replace( '/<p[^>]*>(.*?)<\/p>/si', "$1\n\n", $md );

		// Line breaks.
		$md = preg_replace( '/<br[^>]*>/si', "  \n", $md );

		// Strip any remaining HTML tags.
		$md = strip_tags( $md );

		// Decode HTML entities.
		$md = html_entity_decode( $md, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Remove orphaned emphasis markers on their own line.
		$md = preg_replace( '/^[_*]{1,2}\s*[_*]{0,2}$/m', '', $md );

		// Collapse excessive blank lines.
		$md = preg_replace( '/\n{3,}/', "\n\n", $md );

		return trim( $md );
	}

	/**
	 * Wraps a string in quotes if it contains special YAML characters.
	 */
	private function yaml_escape( string $value ): string {
		if ( preg_match( '/[:#\[\]{},&*?|<>=!%@`\'"]/', $value ) ) {
			return '"' . str_replace( '"', '\\"', $value ) . '"';
		}
		return $value;
	}
}
