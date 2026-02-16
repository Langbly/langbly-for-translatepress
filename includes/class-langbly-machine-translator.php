<?php
/**
 * Langbly Machine Translator for TranslatePress.
 *
 * Extends TranslatePress's machine translation system to use the Langbly API,
 * which is Google Translate v2 compatible.
 *
 * @package Langbly_For_TranslatePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TRP_Langbly_Machine_Translator
 *
 * Handles translation requests via the Langbly API.
 * Modeled after TRP_Google_Translate_V2_Machine_Translator since
 * Langbly uses the same API response format.
 */
class TRP_Langbly_Machine_Translator extends TRP_Machine_Translator {

	/**
	 * Langbly engine identifier.
	 */
	const ENGINE_KEY = 'langbly';

	/**
	 * Settings field name for the API key.
	 */
	const FIELD_API_KEY = 'langbly-api-key';

	/**
	 * Langbly API endpoint.
	 */
	const API_URL = 'https://api.langbly.com/language/translate/v2';

	/**
	 * Maximum number of strings per API request.
	 */
	const MAX_ITEMS = 50;

	/**
	 * Maximum total characters per API request.
	 */
	const MAX_CHARS = 10000;

	/**
	 * Retrieve the API key from settings.
	 *
	 * @return string|false API key or false if not set.
	 */
	public function get_api_key() {
		// Try nested structure (some TRP versions).
		if ( isset( $this->settings['trp_machine_translation_settings'][ self::FIELD_API_KEY ] ) ) {
			$key = $this->settings['trp_machine_translation_settings'][ self::FIELD_API_KEY ];
			if ( ! empty( $key ) ) {
				return $key;
			}
		}
		// Try flat structure (other TRP versions).
		if ( isset( $this->settings[ self::FIELD_API_KEY ] ) ) {
			$key = $this->settings[ self::FIELD_API_KEY ];
			if ( ! empty( $key ) ) {
				return $key;
			}
		}
		// Fallback: read directly from options table.
		$mt_settings = get_option( 'trp_machine_translation_settings', array() );
		if ( isset( $mt_settings[ self::FIELD_API_KEY ] ) && ! empty( $mt_settings[ self::FIELD_API_KEY ] ) ) {
			return $mt_settings[ self::FIELD_API_KEY ];
		}
		return false;
	}

	/**
	 * Send a translation request to the Langbly API.
	 *
	 * @param string   $source_language Source language ISO code.
	 * @param string   $language_code   Target language ISO code.
	 * @param string[] $strings_array   Array of strings to translate.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	public function send_request( $source_language, $language_code, $strings_array ) {
		$body = array(
			'q'      => array_values( $strings_array ),
			'source' => $source_language,
			'target' => $language_code,
			'format' => 'text',
		);

		return wp_remote_post(
			self::API_URL,
			array(
				'method'  => 'POST',
				'timeout' => 45,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-Key'    => $this->get_api_key(),
					'User-Agent'   => 'langbly-translatepress/1.0.0',
					'Referer'      => $this->get_referer(),
				),
				'body'    => wp_json_encode( $body ),
			)
		);
	}

	/**
	 * Translate an array of strings.
	 *
	 * Core method called by TranslatePress to translate page content.
	 * Handles chunking, API calls, response parsing, and logging.
	 *
	 * @param array  $new_strings          Associative array of original => original strings.
	 * @param string $target_language_code  Target language (WordPress locale format).
	 * @param string $source_language_code  Source language (WordPress locale format).
	 * @return array Associative array of original => translated strings.
	 */
	public function translate_array( $new_strings, $target_language_code, $source_language_code = null ) {
		if ( null === $source_language_code ) {
			$source_language_code = $this->settings['default-language'];
		}

		if ( ! $this->verify_request_parameters( $target_language_code, $source_language_code ) ) {
			return $new_strings;
		}

		// Map WordPress locale codes to ISO language codes.
		$source_lang = $this->map_language_code( $source_language_code );
		$target_lang = $this->map_language_code( $target_language_code );

		// Apply TranslatePress language filters.
		$source_lang = apply_filters( 'trp_langbly_source_language', $source_lang, $source_language_code, $target_language_code );
		$target_lang = apply_filters( 'trp_langbly_target_language', $target_lang, $target_language_code, $source_language_code );

		$translated_strings = array();

		// Split into chunks respecting API limits.
		$chunks = $this->chunk_strings( $new_strings );

		foreach ( $chunks as $chunk ) {
			// Check quota between chunks.
			if ( isset( $this->machine_translator_logger ) && $this->machine_translator_logger->quota_exceeded() ) {
				break;
			}

			$response = $this->send_request( $source_lang, $target_lang, $chunk );

			// Log the request.
			if ( isset( $this->machine_translator_logger ) ) {
				$this->machine_translator_logger->log(
					array(
						'strings'   => $chunk,
						'response'  => $response,
						'lang_source' => $source_lang,
						'lang_target' => $target_lang,
					)
				);
			}

			// Handle network errors.
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$status_code   = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( 200 !== $status_code ) {
				continue;
			}

			$data = json_decode( $response_body );

			if ( ! isset( $data->data->translations ) || ! is_array( $data->data->translations ) ) {
				continue;
			}

			// Map translations back to original keys.
			$chunk_keys    = array_keys( $chunk );
			$translations  = $data->data->translations;

			foreach ( $chunk_keys as $index => $original_string ) {
				if ( isset( $translations[ $index ]->translatedText ) ) {
					$translated_strings[ $original_string ] = $translations[ $index ]->translatedText;
				}
			}
		}

		// Merge: use translations where available, keep originals otherwise.
		return array_merge( $new_strings, $translated_strings );
	}

	/**
	 * Test the API connection with a simple translation request.
	 *
	 * @return array|WP_Error Response array or WP_Error.
	 */
	public function test_request() {
		return $this->send_request( 'en', 'es', array( 'Hello' ) );
	}

	/**
	 * Check if the API key is valid and the service is accessible.
	 *
	 * Called by TranslatePress to show status in settings.
	 *
	 * @return array Array with 'message' (string) and 'error' (bool) keys.
	 */
	public function check_api_key_validity() {
		$current_engine = isset( $this->settings['trp_machine_translation_settings']['translation-engine'] )
			? $this->settings['trp_machine_translation_settings']['translation-engine']
			: '';

		$is_active = isset( $this->settings['trp_machine_translation_settings']['machine-translation'] )
			&& 'yes' === $this->settings['trp_machine_translation_settings']['machine-translation'];

		if ( self::ENGINE_KEY !== $current_engine || ! $is_active ) {
			return array(
				'message' => '',
				'error'   => false,
			);
		}

		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return array(
				'message' => esc_html__( 'Please enter your Langbly API key.', 'langbly-for-translatepress' ),
				'error'   => true,
			);
		}

		$response = $this->test_request();

		if ( is_wp_error( $response ) ) {
			return array(
				'message' => sprintf(
					/* translators: %s: error message */
					esc_html__( 'Connection failed: %s', 'langbly-for-translatepress' ),
					$response->get_error_message()
				),
				'error'   => true,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status_code ) {
			return array(
				'message' => esc_html__( 'Langbly API key is valid.', 'langbly-for-translatepress' ),
				'error'   => false,
			);
		}

		if ( 401 === $status_code ) {
			return array(
				'message' => esc_html__( 'Invalid API key. Please check your key at langbly.com/dashboard.', 'langbly-for-translatepress' ),
				'error'   => true,
			);
		}

		return array(
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				esc_html__( 'Unexpected response from Langbly API (HTTP %d).', 'langbly-for-translatepress' ),
				$status_code
			),
			'error'   => true,
		);
	}

	/**
	 * Get engine-specific language codes.
	 *
	 * @param array $languages Array of TranslatePress language data.
	 * @return array Language code mappings.
	 */
	public function get_engine_specific_language_codes( $languages ) {
		return $this->trp_languages->get_iso_codes( $languages );
	}

	/**
	 * Map a WordPress locale code to an ISO 639-1 language code.
	 *
	 * Handles TranslatePress locale formats (en_US, zh_CN, pt_BR, etc.)
	 * and converts them to codes the Langbly API expects.
	 *
	 * @param string $locale WordPress locale code.
	 * @return string ISO 639-1 language code.
	 */
	private function map_language_code( $locale ) {
		// Check if we have machine_translation_codes from TranslatePress.
		if ( isset( $this->machine_translation_codes[ $locale ] ) ) {
			$code = $this->machine_translation_codes[ $locale ];
			if ( ! empty( $code ) ) {
				return $code;
			}
		}

		// Special cases.
		$special_map = array(
			'zh_CN' => 'zh',
			'zh_TW' => 'zh-TW',
			'zh_HK' => 'zh-TW',
			'pt_BR' => 'pt',
			'pt_PT' => 'pt',
			'nb_NO' => 'no',
			'nn_NO' => 'no',
		);

		if ( isset( $special_map[ $locale ] ) ) {
			return $special_map[ $locale ];
		}

		// Default: extract primary language subtag (first 2 chars before _ or -).
		$parts = preg_split( '/[_-]/', $locale );
		return strtolower( $parts[0] );
	}

	/**
	 * Split strings into chunks respecting Langbly API limits.
	 *
	 * Each chunk contains at most MAX_ITEMS strings and MAX_CHARS
	 * total characters.
	 *
	 * @param array $strings Associative array of original => text.
	 * @return array[] Array of chunks.
	 */
	private function chunk_strings( $strings ) {
		$chunks        = array();
		$current_chunk = array();
		$current_chars = 0;
		$current_count = 0;

		foreach ( $strings as $key => $text ) {
			$text_len = mb_strlen( (string) $text, 'UTF-8' );

			if (
				$current_count > 0 &&
				( $current_count >= self::MAX_ITEMS || $current_chars + $text_len > self::MAX_CHARS )
			) {
				$chunks[]      = $current_chunk;
				$current_chunk = array();
				$current_chars = 0;
				$current_count = 0;
			}

			$current_chunk[ $key ] = $text;
			$current_chars        += $text_len;
			++$current_count;
		}

		if ( ! empty( $current_chunk ) ) {
			$chunks[] = $current_chunk;
		}

		return $chunks;
	}
}
