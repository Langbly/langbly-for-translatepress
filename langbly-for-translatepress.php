<?php
/**
 * Plugin Name: Langbly for TranslatePress
 * Plugin URI: https://langbly.com
 * Description: AI-powered automatic translations for TranslatePress — a drop-in Google Translate replacement, 5x cheaper with better quality.
 * Author: Langbly
 * Author URI: https://langbly.com
 * Version: 1.0.3
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Requires Plugins: translatepress-multilingual
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: langbly-for-translatepress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Register Langbly filters immediately at file load time.
 *
 * TranslatePress uses a deferred loader: it registers trp_run_translatepress_hooks
 * at plugins_loaded priority 1, which in turn registers init_machine_translation at
 * plugins_loaded priority 2. The init_machine_translation callback calls
 * get_active_engine(), which queries the trp_automatic_translation_engines_classes
 * filter to find available engine classes.
 *
 * Because WordPress loads plugins in activation order (not alphabetical), our plugin
 * may load before or after TranslatePress. If it loads before, any plugins_loaded
 * callback we register at priority 1 would fire BEFORE TRP's priority 1 callback,
 * meaning TRP_Machine_Translator would not exist yet.
 *
 * The solution: register filters at file load time (no plugins_loaded wrapper).
 * The filters only fire when TRP calls them, by which point all classes are loaded.
 * The class file include is deferred to the filter callback that needs it.
 */
add_filter( 'trp_machine_translation_engines', 'langbly_trp_add_engine', 10 );
add_filter( 'trp_automatic_translation_engines_classes', 'langbly_trp_add_engine_class', 10 );
add_action( 'trp_machine_translation_extra_settings_middle', 'langbly_trp_settings_ui', 10, 2 );
add_filter( 'trp_machine_translation_sanitize_settings', 'langbly_trp_sanitize_settings', 10, 2 );

/**
 * Add Langbly to the translation engine dropdown.
 *
 * @param array $engines Existing engines.
 * @return array Modified engines.
 */
function langbly_trp_add_engine( $engines ) {
	$engines[] = array(
		'value' => 'langbly',
		'label' => 'Langbly',
	);
	return $engines;
}

/**
 * Map the 'langbly' engine key to its class name.
 *
 * Also loads the class file on first call. This filter is called by
 * TRP's get_active_engine() at plugins_loaded priority 2, by which
 * point TRP_Machine_Translator is guaranteed to exist.
 *
 * @param array $classes Existing class mappings.
 * @return array Modified class mappings.
 */
function langbly_trp_add_engine_class( $classes ) {
	if ( ! class_exists( 'TRP_Langbly_Machine_Translator', false ) ) {
		require_once __DIR__ . '/includes/class-langbly-machine-translator.php';
	}
	$classes['langbly'] = 'TRP_Langbly_Machine_Translator';
	return $classes;
}

/**
 * Render the API key settings field in TranslatePress settings.
 *
 * @param array $mt_settings Machine translation settings.
 * @param object $mt_instance Machine translator instance (optional).
 */
function langbly_trp_settings_ui( $mt_settings, $mt_instance = null ) {
	if ( ! class_exists( 'TRP_Langbly_Machine_Translator', false ) ) {
		require_once __DIR__ . '/includes/class-langbly-machine-translator.php';
	}

	$api_key = isset( $mt_settings['langbly-api-key'] ) ? $mt_settings['langbly-api-key'] : '';
	$is_langbly = isset( $mt_settings['translation-engine'] ) && 'langbly' === $mt_settings['translation-engine'];

	// Check API key validity if we have a translator instance.
	$check_result = null;
	if ( $is_langbly && $mt_instance instanceof TRP_Langbly_Machine_Translator ) {
		$check_result = $mt_instance->check_api_key_validity();
	}
	?>
	<tr id="trp-langbly-settings" <?php echo $is_langbly ? '' : 'style="display:none;"'; ?>>
		<th scope="row">
			<?php esc_html_e( 'Langbly API Key', 'langbly-for-translatepress' ); ?>
		</th>
		<td>
			<input type="text"
				   id="trp-langbly-api-key"
				   name="trp_machine_translation_settings[langbly-api-key]"
				   class="trp-text-input"
				   value="<?php echo esc_attr( $api_key ); ?>"
				   autocomplete="off"
			/>
			<?php if ( null !== $check_result && isset( $check_result['message'] ) ) : ?>
				<p class="description <?php echo ! empty( $check_result['error'] ) ? 'trp-error' : 'trp-success'; ?>">
					<?php echo esc_html( $check_result['message'] ); ?>
				</p>
			<?php endif; ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: signup URL */
					esc_html__( 'Get your free API key at %s (500K characters free, no credit card required).', 'langbly-for-translatepress' ),
					'<a href="https://langbly.com/signup" target="_blank" rel="noopener">langbly.com/signup</a>'
				);
				?>
			</p>
		</td>
	</tr>

	<script type="text/javascript">
		(function() {
			function init() {
				var engineSelect = document.querySelector('select[name="trp_machine_translation_settings[translation-engine]"]');
				if (!engineSelect) {
					engineSelect = document.getElementById('trp-translation-engine');
				}
				var langblyRow = document.getElementById('trp-langbly-settings');
				if (engineSelect && langblyRow) {
					function toggleLangbly() {
						langblyRow.style.display = (engineSelect.value === 'langbly') ? '' : 'none';
					}
					engineSelect.addEventListener('change', toggleLangbly);
					toggleLangbly();
				}
			}
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', init);
			} else {
				init();
			}
		})();
	</script>
	<?php
}

/**
 * Sanitize the Langbly API key setting.
 *
 * @param array $mt_settings Sanitized settings.
 * @param array $raw_settings Raw POST input.
 * @return array Modified settings.
 */
function langbly_trp_sanitize_settings( $mt_settings, $raw_settings ) {
	if ( isset( $raw_settings['langbly-api-key'] ) ) {
		$key = sanitize_text_field( $raw_settings['langbly-api-key'] );
		$mt_settings['langbly-api-key'] = $key;

		// Validate API key on save when Langbly is the selected engine.
		if ( ! empty( $key ) && isset( $raw_settings['translation-engine'] ) && 'langbly' === $raw_settings['translation-engine'] ) {
			$response = wp_remote_post(
				'https://api.langbly.com/language/translate/v2',
				array(
					'timeout' => 10,
					'headers' => array(
						'Content-Type' => 'application/json',
						'X-API-Key'    => $key,
					),
					'body'    => wp_json_encode( array(
						'q'      => array( 'test' ),
						'source' => 'en',
						'target' => 'es',
						'format' => 'text',
					) ),
				)
			);
			if ( ! is_wp_error( $response ) && 401 === wp_remote_retrieve_response_code( $response ) ) {
				add_settings_error(
					'trp_machine_translation_settings',
					'langbly_invalid_key',
					__( 'The Langbly API key is invalid. Please check your key at langbly.com/dashboard.', 'langbly-for-translatepress' ),
					'error'
				);
			}
		}
	}
	return $mt_settings;
}
