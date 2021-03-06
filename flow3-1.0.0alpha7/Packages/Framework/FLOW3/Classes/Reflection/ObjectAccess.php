<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\Reflection;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * @version $Id: ObjectAccess.php 3746 2010-01-21 20:29:02Z k-fish $
 */
/**
 * Provides methods to call appropriate getter/setter on an object given the
 * property name. It does this following these rules:
 * - if the target object is an instance of ArrayAccess, it gets/sets the property
 * - if public getter/setter method exists, call it.
 * - if public property exists, return/set the value of it.
 * - else, throw exception
 *
 * @version $Id: ObjectAccess.php 3746 2010-01-21 20:29:02Z k-fish $
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class ObjectAccess {

	const ACCESS_GET = 0;
	const ACCESS_SET = 1;
	const ACCESS_PUBLIC = 2;

	/**
	 * Get a property of a given object.
	 * Tries to get the property the following ways:
	 * - if the target is an array, and has this property, we call it.
	 * - if public getter method exists, call it.
	 * - if the target object is an instance of ArrayAccess, it gets the property
	 *   on it if it exists.
	 * - if public property exists, return the value of it.
	 * - else, throw exception
	 *
	 * @param mixed $subject Object or array to get the property from
	 * @param string $propertyName name of the property to retrieve
	 * @return object Value of the property.
	 * @throws \InvalidArgumentException in case $subject was not an object or $propertyName was not a string
	 * @throws \RuntimeException if the property was not accessible
	 * @author Robert Lemke <robert@typo3.org>
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	static public function getProperty($subject, $propertyName) {
		if (!is_object($subject) && !is_array($subject)) throw new \InvalidArgumentException('$subject must be an object or array, ' . gettype($subject). ' given.', 1237301367);
		if (!is_string($propertyName)) throw new \InvalidArgumentException('Given property name is not of type string.', 1231178303);

		if (is_array($subject)) {
			if (array_key_exists($propertyName, $subject)) {
				return $subject[$propertyName];
			}
		} else {
			if (is_callable(array($subject, $getterMethodName = self::buildGetterMethodName($propertyName)))) {
				return call_user_func(array($subject, $getterMethodName));
			} elseif ($subject instanceof \ArrayAccess && isset($subject[$propertyName])) {
				return $subject[$propertyName];
			} elseif (array_key_exists($propertyName, get_object_vars($subject))) {
				return $subject->$propertyName;
			}
		}

		throw new \RuntimeException('The property "' . $propertyName . '" on the subject was not accessible.', 1263391473);
	}

	/**
	 * Gets a property path from a given object.
	 * If propertyPath is "bla.blubb", then we first call getProperty($object, 'bla'),
	 * and on the resulting object we call getProperty(..., 'blubb')
	 *
	 * @param object $object
	 * @param string $propertyPath
	 * @return mixed Value of the property
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	static public function getPropertyPath($object, $propertyPath) {
		$propertyPathSegments = explode('.', $propertyPath);
		foreach ($propertyPathSegments as $pathSegment) {
			if (is_object($object) && self::isPropertyGettable($object, $pathSegment)) {
				$object = self::getProperty($object, $pathSegment);
			} else {
				return NULL;
			}
		}
		return $object;
	}

	/**
	 * Set a property for a given object.
	 * Tries to set the property the following ways:
	 * - if public setter method exists, call it.
	 * - if public property exists, set it directly.
	 * - if the target object is an instance of ArrayAccess, it sets the property
	 *   on it without checking if it existed.
	 * - else, return FALSE
	 *
	 * @param object $object The target object
	 * @param string $propertyName Name of the property to set
	 * @param object $propertyValue Value of the property
	 * @return boolean TRUE if the property could be set, FALSE otherwise
	 * @throws \InvalidArgumentException in case $object was not an object or $propertyName was not a string
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 * @author Robert Lemke <robert@typo3.org>
	 */
	static public function setProperty($object, $propertyName, $propertyValue) {
		if (!is_object($object)) throw new \InvalidArgumentException('$object must be an object, ' . gettype($object). ' given.', 1237301368);
		if (!is_string($propertyName)) throw new \InvalidArgumentException('Given property name is not of type string.', 1231178878);

		if (is_callable(array($object, $setterMethodName = self::buildSetterMethodName($propertyName)))) {
			call_user_func(array($object, $setterMethodName), $propertyValue);
		} elseif ($object instanceof \ArrayAccess) {
			$object[$propertyName] = $propertyValue;
		} elseif (array_key_exists($propertyName, get_object_vars($object))) {
			$object->$propertyName = $propertyValue;
		} else {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Returns an array of properties which can be get with the getProperty()
	 * method.
	 * Includes the following properties:
	 * - which can be get through a public getter method.
	 * - public properties which can be directly get.
	 *
	 * @param object $object Object to receive property names for
	 * @return array Array of all gettable property names
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	static public function getGettablePropertyNames($object) {
		if (!is_object($object)) throw new \InvalidArgumentException('$object must be an object, ' . gettype($object). ' given.', 1237301369);
		$declaredPropertyNames = array_keys(get_class_vars(get_class($object)));

		foreach (get_class_methods($object) as $methodName) {
			if (substr($methodName, 0, 3) === 'get' && is_callable(array($object, $methodName))) {
				$declaredPropertyNames[] = lcfirst(substr($methodName, 3));
			}
		}

		$propertyNames = array_unique($declaredPropertyNames);
		sort($propertyNames);
		return $propertyNames;
	}

	/**
	 * Returns an array of properties which can be set with the setProperty()
	 * method.
	 * Includes the following properties:
	 * - which can be set through a public setter method.
	 * - public properties which can be directly set.
	 *
	 * @param object $object Object to receive property names for
	 * @return array Array of all settable property names
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	static public function getSettablePropertyNames($object) {
		if (!is_object($object)) throw new \InvalidArgumentException('$object must be an object, ' . gettype($object). ' given.', 1264022994);
		$declaredPropertyNames = array_keys(get_class_vars(get_class($object)));

		foreach (get_class_methods($object) as $methodName) {
			if (substr($methodName, 0, 3) === 'set' && is_callable(array($object, $methodName))) {
				$declaredPropertyNames[] = lcfirst(substr($methodName, 3));
			}
		}

		$propertyNames = array_unique($declaredPropertyNames);
		sort($propertyNames);
		return $propertyNames;
	}

	/**
	 * Tells if the value of the specified property can be set by this Object Accessor.
	 *
	 * @param object $object Object containting the property
	 * @param string $propertyName Name of the property to check
	 * @return boolean
	 * @author Robert Lemke <robert@typo3.org>
	 */
	static public function isPropertySettable($object, $propertyName) {
		if (!is_object($object)) throw new \InvalidArgumentException('$object must be an object, ' . gettype($object). ' given.', 1259828920);
		if (array_search($propertyName, array_keys(get_class_vars(get_class($object)))) !== FALSE) return TRUE;
		return is_callable(array($object, self::buildSetterMethodName($propertyName)));
	}

	/**
	 * Tells if the value of the specified property can be retrieved by this Object Accessor.
	 *
	 * @param object $object Object containting the property
	 * @param string $propertyName Name of the property to check
	 * @return boolean
	 * @author Robert Lemke <robert@typo3.org>
	 */
	static public function isPropertyGettable($object, $propertyName) {
		if (!is_object($object)) throw new \InvalidArgumentException('$object must be an object, ' . gettype($object). ' given.', 1259828921);
		if (array_search($propertyName, array_keys(get_class_vars(get_class($object)))) !== FALSE) return TRUE;
		return is_callable(array($object, self::buildGetterMethodName($propertyName)));
	}

	/**
	 * Get all properties (names and their current values) of the current
	 * $object that are accessible through this class.
	 *
	 * @param object $object Object to get all properties from.
	 * @return array Associative array of all properties.
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 * @todo What to do with ArrayAccess
	 */
	static public function getGettableProperties($object) {
		if (!is_object($object)) throw new \InvalidArgumentException('$object must be an object, ' . gettype($object). ' given.', 1237301370);
		$properties = array();
		foreach (self::getGettablePropertyNames($object) as $propertyName) {
			$properties[$propertyName] = self::getProperty($object, $propertyName);
		}
		return $properties;
	}

	/**
	 * Build the getter method name for a given property by capitalizing the
	 * first letter of the property, and prepending it with "get".
	 *
	 * @param string $propertyName Name of the property
	 * @return string Name of the getter method name
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 */
	static public function buildGetterMethodName($propertyName) {
		return 'get' . ucfirst($propertyName);
	}

	/**
	 * Build the setter method name for a given property by capitalizing the
	 * first letter of the property, and prepending it with "set".
	 *
	 * @param string $propertyName Name of the property
	 * @return string Name of the setter method name
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 */
	static public function buildSetterMethodName($propertyName) {
		return 'set' . ucfirst($propertyName);
	}
}


?>