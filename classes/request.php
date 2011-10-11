<?php defined('SYSPATH') or die('No direct script access.');

class Request extends Kohana_Request {

	/**
	 * @var  string  the language of the main request
	 */
	public static $lang;

	/**
	 * Extension of the main request factory. If none given, the URI will
	 * be automatically detected. If the URI contains no language segment, the user
	 * will be redirected to the same URI with the default language prepended.
	 * If the URI does contain a language segment, I18n and locale will be set.
	 * Also, a cookie with the current language will be set. Finally, the language
	 * segment is chopped off the URI and normal request processing continues.
	 *
	 * @param   string  $uri URI of the request
	 * @param   Cache   $cache
	 * @param   array   $injected_routes an array of routes to use, for testing
	 * @return  Request
	 * @uses    Kohana::$config
	 * @uses    Request::detect_uri
	 * @uses    Lang::find_default
	 * @uses    URL::base
	 * @uses    I18n
	 * @uses    Cookie::get
	 * @uses    Cookie::set
	 */
	public static function factory($uri = TRUE, HTTP_Cache $cache = NULL, $injected_routes = array())
	{
		// All supported languages
		$langs = (array) Kohana::$config->load('lang');

		if ($uri === TRUE)
		{
			// We need the current URI
			$uri = Request::detect_uri();
		}

		// Normalize URI
		$uri = ltrim($uri, '/');

		// Look for a supported language in the first URI segment
		if ( ! preg_match('~^(?:'.implode('|', array_keys($langs)).')(?=/|$)~i', $uri, $matches))
		{
			// Find the best default language
			$lang = Lang::find_default();

			// Prepend default language if needed
			if (Lang::$default_prepended)
			{
				// Use the default server protocol
				$protocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';

				// Redirect to the same URI, but with language prepended
				header($protocol.' 302 Found');
				header('Location: '.URL::base(TRUE, TRUE).$lang.'/'.$uri);

				// Stop execution
				exit;
			}
			else
			{
				// Set the default language as the match
				$matches[0] = Lang::$default;
			}
		}
		else
		{
			// If default is not prepended and found language is the default, then remove it from URI
			if ( ! Lang::$default_prepended AND strtolower($matches[0]) === Lang::$default)
			{
				// Use the default server protocol
				$protocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';

				// Redirect to the same URI, but with default language removed
				header($protocol.' 302 Found');
				header('Location: '.URL::base(TRUE, TRUE).ltrim($uri, Lang::$default.'/'));

				// Stop execution
				exit;
			}
		}

		// Language found in the URI
		Request::$lang = strtolower($matches[0]);

		// Store target language in I18n
		I18n::$lang = $langs[Request::$lang]['i18n_code'];

		// Set locale
		setlocale(LC_ALL, $langs[Request::$lang]['locale']);

		if (Cookie::get(Lang::$cookie) !== Request::$lang)
		{
			// Update language cookie if needed
			Cookie::set(Lang::$cookie, Request::$lang);
		}

		if (Lang::$default_prepended OR Request::$lang !== Lang::$default)
		{
			// Remove language from URI if default is prepended or the language is not the default
			$uri = (string) substr($uri, strlen(Request::$lang));
		}

		// Continue normal request processing with the URI without language
		return parent::factory($uri, $cache, $injected_routes);
	}

} // End Request