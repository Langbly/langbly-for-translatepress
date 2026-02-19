=== Langbly for TranslatePress ===
Contributors: langbly
Tags: translation, translatepress, machine-translation, ai, multilingual
Requires at least: 5.6
Tested up to: 6.7
Stable tag: 1.0.3
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered automatic translations for TranslatePress. A drop-in Google Translate replacement — 5x cheaper with better quality.

== Description ==

**Langbly for TranslatePress** adds Langbly as a machine translation engine in TranslatePress. Automatically translate your entire WordPress site with AI-powered translations that understand context, tone, and locale conventions.

= Why Langbly? =

* **5-10x cheaper** than Google Translate ($1.99-$4/1M characters vs $20/1M)
* **Better quality** — LLM-powered translations understand context and idioms
* **Locale formatting** — automatic decimal, date, and currency formatting per language
* **Free tier** — 500K characters to get started, no credit card required
* **Same API format** — Google Translate v2 compatible, proven and reliable

= How It Works =

1. Install and activate this plugin alongside TranslatePress
2. Go to Settings > TranslatePress > Automatic Translation
3. Select "Langbly" as your translation engine
4. Enter your API key
5. Enable automatic translation — done!

TranslatePress will automatically translate new pages and content using Langbly when visitors browse your site in a different language.

= Supported Languages =

Langbly supports all major languages including English, Dutch, German, French, Spanish, Portuguese, Italian, Chinese, Japanese, Korean, Arabic, Russian, and many more. Especially strong for Dutch, German, and French translations.

== Installation ==

1. Install [TranslatePress](https://wordpress.org/plugins/translatepress-multilingual/) if not already installed.
2. Upload the `langbly-for-translatepress` folder to `/wp-content/plugins/`.
3. Activate the plugin from the WordPress admin.
4. Go to **Settings > TranslatePress > Automatic Translation**.
5. Set "Enable Automatic Translation" to **Yes**.
6. Select **Langbly** as the translation engine.
7. Enter your Langbly API key.
8. Save settings.

= Getting an API Key =

1. Sign up for free at [langbly.com/signup](https://langbly.com/signup)
2. Go to your dashboard and create an API key
3. Paste the key into the TranslatePress settings

== Frequently Asked Questions ==

= How much does it cost? =

Langbly offers a free tier with 500K characters. Paid plans start at $19/month for 5M characters. See [langbly.com/pricing](https://langbly.com/pricing) for details.

= Which languages are supported? =

All standard ISO 639-1 languages are supported. Langbly is especially strong for European languages like Dutch, German, and French.

= How does it compare to Google Translate? =

Langbly uses the same API format as Google Translate v2 but provides better translations through LLM technology at 5-10x lower cost. The integration works identically — just select Langbly instead of Google Translate in the settings.

= Does it work with the free version of TranslatePress? =

Yes! This plugin works with both the free and premium versions of TranslatePress.

= Where do I find my API key? =

Sign up at [langbly.com/signup](https://langbly.com/signup) and go to your dashboard to create an API key. The free tier includes 500K characters, no credit card required.

= Can I switch from Google Translate? =

Absolutely. Just change the translation engine in TranslatePress settings from Google Translate to Langbly and enter your API key. Existing translations are preserved.

== Changelog ==

= 1.0.3 =
* Fix engine loading race condition: register filters at file load time instead of plugins_loaded
* Use TranslatePress built-in machine_translation_codes instead of custom mapping
* Return empty array on verification failure (TranslatePress convention)
* Add fallback selector for engine dropdown toggle

= 1.0.2 =
* Minor stability improvements

= 1.0.1 =
* Version bump, internal improvements

= 1.0.0 =
* Initial release
* Full TranslatePress engine integration
* API key validation with test request
* Automatic chunking for large page translations
* Locale-aware language code mapping
* Settings UI with show/hide based on engine selection

== Upgrade Notice ==

= 1.0.3 =
Fixes a race condition where Langbly could fail to appear as a translation engine. Recommended update.
