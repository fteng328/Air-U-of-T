<?php

/**
 * Static class to extend CI's HTML generation.
 */
class HTML_Utils extends CI_Model {
	/**
	 * Return an associative array for everything that's needed for a text input with the given name.
	 * @param input_name The name of the input field.
	 */
	function get_input_array($input_name) {
		return array("name" => $input_name, "id" => $input_name);
	}
	
	/**
	 * Given an associative array, return an options string to add as 4th parameter to input_dropdown.
	 * The options string simply glues together the key-value pairs of the associative array, and glues the array together with spaces.
	 * Add an HTML5 'required' attribute as well.
	 * @param $arr The associative array
	 */
	function get_dropdown_options($arr) {
		$arr['required'] = 'required';
		$a2 = array();
		
		foreach ($arr as $key => $value) {
			$a2[] = $key . "='" . $value . "'";
		}
		
		return join(" ", $a2);
	}
}

?>