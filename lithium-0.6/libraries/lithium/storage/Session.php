<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\storage;

use \lithium\core\Libraries;
use \lithium\util\Collection;

/**
 * The `Session` static class provides a consistent interface to configure and utilize the
 * different persistent storage adatpers included with Lithium, as well as your own adapters.
 *
 * The Session layer of Lithium inherits from the common `Adaptable` class, which provides
 * the generic configuration setting & retrieval logic, as well as the logic required to
 * locate & instantiate the proper adapter class.
 *
 * In most cases, you will configure various named session configurations in your bootstrap
 * process, which will then be available to you in all other parts of your application.
 *
 * Each adapter provides a consistent interface for the basic cache operations of `write`, `read`
 * and `delete`, which can be used interchangably between all adapters. ty.
 *
 * For more information on `Session` methods and specific adapters, please see their relevant
 * documentation.
 *
 * @see lithium\core\Adaptable
 * @see lithium\storage\session\adapter
 */
class Session extends \lithium\core\Adaptable {

	/**
	 * Stores configurations for cache adapters
	 *
	 * @var object Collection of cache configurations
	 */
	protected static $_configurations = null;

	protected static $_adapters = 'adapter.storage.session';

	/**
	 * Returns key to be used in session read, write and delete operations
	 *
	 * @param mixed $name Named session configuration
	 * @return string Value of key
	 */
	public static function key($name = null) {
		return is_object($adapter = static::adapter($name)) ? $adapter->key() : null;
	}

	/**
	 * Indicates whether the the current request includes information on a previously started
	 * session.
	 *
	 * @param string $name
	 * @return boolean Returns true if a the request includes a key from a previously created
	 *         session.
	 */
	public static function isStarted($name = null) {
		return is_object($adapter = static::adapter($name)) ? $adapter->isStarted() : false;
	}

	/**
	 * Checks the validity of a previously-started session by running several checks, including
	 * comparing the session start time to the expiration time set in the configuration, and any
	 * security settings.
	 *
	 * @param string $name
	 * @return boolean Returns true if the current session is active and valid.
	 */
	public static function isValid($name = null) {

	}

	/**
	 * Reads a value from a persistent session store.
	 *
	 * @param string $key Key to be read
	 * @param array $options Optional parameters that this method accepts
	 * @return mixed Read result on successful session read, null otherwise
	 */
	public static function read($key, $options = array()) {
		$defaults = array('name' => null);
		$options += $defaults;
		$method = ($name = $options['name']) ? static::adapter($name)->read($key, $options) : null;
		$settings = static::_config($name);

		if (!$method) {
			foreach (static::$_configurations->keys() as $name) {
				if ($method = static::adapter($name)->read($key, $options)) {
					break;
				}
			}
			if (!$method || !$name) {
				return null;
			}
		}
		$filters = $settings['filters'];
		return static::_filter(__METHOD__, compact('key', 'options'), $method, $filters);
	}

	/**
	 * Writes a persistent value to one or more session stores.
	 *
	 * @param string $key Key to be read
	 * @param mixed $value Data to be stored
	 * @param array $options Optional parameters that this method accepts
	 * @return boolean True on successful write, false otherwise
	 */
	public static function write($key, $value = null, $options = array()) {
		$defaults = array('name' => null);
		$options += $defaults;

		if (is_resource($value) || !static::$_configurations->count()) {
			return false;
		}
		$methods = array();

		if ($name = $options['name']) {
			$methods = array($name => static::adapter($name)->write($key, $value, $options));
		} else {
			foreach (static::$_configurations->keys() as $name) {
				if ($method = static::adapter($name)->write($key, $value, $options)) {
					$methods[$name] = $method;
				}
			}
		}
		$result = false;
		$settings = static::_config($name);

		foreach ($methods as $name => $method) {
			$params = compact('key', 'value', 'options');
			$filters = $settings['filters'];
			$result = $result || static::_filter(__METHOD__, $params, $method, $filters);
		}
		return $result;
	}

	/**
	 * Deletes a named key from a single adapter (if a `'name'` option is specified) or all
	 * session adapters.
	 *
	 * @param string $key The name of the session key to delete
	 * @param array $options
	 * @return void
	 */
	public static function delete($key, $options = array()) {
		$defaults = array('name' => null);
		$options += $defaults;

		$methods = array();

		if ($name = $options['name']) {
			$methods = array($name => static::adapter($name)->delete($key, $options));
		} else {
			foreach (static::$_configurations->keys() as $name) {
				if ($method = static::adapter($name)->delete($key, $options)) {
					$methods[$name] = $method;
				}
			}
		}
		$result = false;
		$settings = static::_config($name);

		foreach ($methods as $name => $method) {
			$params = compact('key', 'options');
			$filters = $settings['filters'];
			$result = $result || static::_filter(__METHOD__, $params, $method, $filters);
		}
		return $result;
	}

	/**
	 * Checks if a session key is set in any adapter, or if a particular adapter configuration is
	 * specified (via `'name'` in `$options`), only that configuration is checked.
	 *
	 * @param string $key The session key to check.
	 * @param array $options
	 * @return boolean
	 */
	public static function check($key, $options = array()) {
		$defaults = array('name' => null);
		$options += $defaults;

		if ($options['name']) {
			return static::adapter($options['name'])->check($key, $options);
		}
		foreach (static::$_configurations->keys() as $name) {
			if (static::adapter($name)->check($key, $options)) {
				return true;
			}
		}
		return false;
	}

	public static function adapter($name = null) {
		if (empty($name)) {
			if (!$names = static::$_configurations->keys()) {
				return;
			}
			$name = end($names);
		}
		return parent::adapter($name);
	}
}

?>