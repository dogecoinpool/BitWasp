<?php 	if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Location Model
 *
 * This class handles handles database queries regarding locations. They
 * can be against different sets of data - the basic bitwasp location list
 * or a user defined list of locations.
 * 
 * @package		BitWasp
 * @subpackage	Models
 * @category	Location
 * @author		BitWasp
 * 
 */
class Location_model extends CI_Model {

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Get Location Heirarchy
	 * 
	 * Loads a sequence of locations, going from $child_location_id to it's
	 * parent location, and so on, until it hits the root category.
	 * 
	 * @return boolean
	 */
	 
	public function get_location_heirarchy($child_location_id){
		$location_heirarchy[] = $this->get_location_info($child_location_id);
		do {
			$previous_position = count($location_heirarchy)-1;
			$location_heirarchy[] = $this->get_location_info($location_heirarchy[$previous_position]['parent_id']);	
		} while($location_heirarchy[($previous_position+1)]['parent_id'] !== '0');
	
		return $location_heirarchy;
	}
	
	/**
	 * Get Child Locations
	 * 
	 * Loads an array of child locations in an array (the main array of
	 * locations) 
	 * 
	 * @param	array	$array
	 * @param	int	$int
	 * @return  array/FALSE
	 * 
	 */
	public function get_child_locations($array, $id) {
		$results = array();

		foreach ($array as $entry) {
			if ($entry['parent_id'] == $id) 
				$results[] = $el;
			
			if (isset($entry['children']) && count($entry['children']) > 0 && ($children = $this->get_child_locations($entry['children'], $id)) !== FALSE) 
				$results = array_merge($results, $children);
		}
		return count($results) > 0 ? $results : FALSE;
	}
	
	
	/**
	 * Validate User Child Location
	 * 
	 * This function will create a list of the heirarchy of parent locations
	 * from the users location to the root location, and returns true 
	 * or false depending on whether the required_parent_id is contained
	 * in this list of points.
	 * 
	 * @param	id	$required_parent_id
	 * @param	id	$user_location_id
	 * @return	boolean
	 */
	public function validate_user_child_location($required_parent_id, $user_location_id) {
		
		$location_heirarchy = $this->get_location_heirarchy($user_location_id);
		$required_location_found = FALSE;
		foreach($location_heirarchy as $location) {
		
			if($location['id'] == $required_parent_id)
				$required_location_found = TRUE;
			if($location['parent_id'] == $required_parent_id)
				$required_location_found = TRUE;
			
		} 
	
		return $required_location_found;
	}
	
	/**
	 * Get List
	 * 
	 * Loads a multidimensional array of locations, depending on the 
	 * supplied list specifier: Default, or Custom. 
	 * 
	 * @param	string	$list
	 * @return	array/FALSE
	 */
	public function get_list($list){
		if($list == 'Default'){
			$query = $this->db->get('country_codes');
			if($query->num_rows() == 0)
				return array();
				
			$results = $query->result_array();
			foreach($results as &$result) {
				$result['location'] = $result['country'];
				unset($result['country']);
				$result['parent_id'] = '0';
			}
			return $results;
		} else if($list == 'Custom') {
			
			
			$this->db->select('id, location, hash, parent_id');
			//Load all categories and sort by parent category
			$this->db->order_by("parent_id asc, location asc");
			$query = $this->db->get('locations_custom_list');
			
			if($query->num_rows() == 0) 
				return array();
				
			// Add all categories to $menu[] array.
			foreach($query->result() as $result) {				
				$menu[$result->id] = array(	'id' => $result->id,
											'location' => $result->location,
											'hash' => $result->hash,
											'parent_id' => $result->parent_id
										);
			}
			
			// Store all child categories as an array $menu[parentID]['children']
			foreach($menu as $ID => &$menuItem) {
				if($menuItem['parent_id'] !== '0')	
					$menu[$menuItem['parent_id']]['children'][$ID] = &$menuItem;
			}

			// Remove child categories from the first level of the $menu[] array.
			foreach(array_keys($menu) as $ID) {
				if($menu[$ID]['parent_id'] != "0")
					unset($menu[$ID]);
			}
			// Return constructed menu.
			return $menu;
		} else {
			return FALSE;
		}
	}

	/**
	 * Generate Select List
	 * 
	 * This function creates a <select> menu to select categories, which
	 * displays parent categories in bold. When chosing a category, if
	 * block_access_to_parent_category is used in form validation, the bold 
	 * categories will be disallowed. The name of the post variable is 'category'.
	 * 
	 * It uses a recursive function, generate_select_list_recurse() to
	 * recurse into the multidimensional array to show child/parent
	 * categories.
	 * 
	 * @return	string
	 */
	public function generate_select_list($list_type, $param_name, $class, $selected = FALSE, $extras = array()) {
		$locations = $this->get_list($list_type);
		$select = "<select name=\"{$param_name}\" class='{$class}' autocomplete=\"off\">\n";
		$select.= "<option value=\"\"></option>\n";
		if(isset($extras['root']) && $extras['root'] == TRUE)
			$select.= "<option value=\"0\">Root Category</option>";
		if(isset($extras['worldwide']) && $extras['worldwide'] == TRUE)
			$select.= "<option value=\"worldwide\">Worldwide</option>";
		
		foreach($locations as $cat){
			$select.= $this->generate_select_list_recurse($cat, $selected);
		}
		$select.= '</select>';
		return $select;
	}
	
	/**
	 * Generate Select List Recurse
	 * 
	 * Called by generate_select_list, this function takes a multidimensional 
	 * array as input, and recurses deeper into the array 
	 * if $array['children'] > 0. If that is the case, the select option
	 * will be in bold, indicating a parent category. Otherwise the option 
	 * is not altered.
	 * 
	 * @param	array	$array
	 * @return	string
	 */
	public function generate_select_list_recurse($array, $selected){
		
		if(isset($array['children']) && is_array($array['children'])){
			$select_txt = '';
			if($selected !== FALSE && $array['id'] == $selected) $select_txt = ' selected="selected" ';
			$output = "<option style=\"font-weight:bold;\" value=\"{$array['id']}\"{$select_txt}>{$array['location']}</option>\n";
			foreach($array['children'] as $child){
				$output.= $this->generate_select_list_recurse($child, $selected);
			}
		} else {
			$select_txt = ' ';
			if($selected !== FALSE && $array['id'] == $selected ) $select_txt = ' selected="selected" ';
			if($array['parent_id'] == '0') $select_txt .= "style=\"font-weight:bold;\" ";
			$output = "<option value=\"{$array['id']}\"{$select_txt}>{$array['location']}</option>\n";
		}
		return $output;
	}

	/**
	 * Add
	 * 
	 * Add a category to the table, as outlined below.
	 * $category = array(	'location' => '...',
	 *						'hash' => '...'),
	 *						'parent_id' => '...');
	 * The category must contain these parameters, otherwise the insert will fail. 
	 * Returns a boolean TRUE on successful insert, else returns FALSE.
	 *
	 * @access	public
	 * @param	array	$category
	 * @return	bool
	 */			
	public function add_custom_location($location) {
		return ($this->db->insert('locations_custom_list', $location) == TRUE) ? TRUE : FALSE;
	}

	/**
	 * Delete Custom Location
	 * 
	 * Deletes a selected location $id. Returns boolean indicating success.
	 * 
	 * @param	int	$id
	 * @return	boolean
	 */
	public function delete_custom_location($id) {
		$this->db->where('id',$id);
		return ($this->db->delete('locations_custom_list') == TRUE) ? TRUE : FALSE;
	}

	/**
	 * Location by ID
	 * 
	 * Load the name of the location specified by $id. Returns a string
	 * if successful or FALSE on failure.
	 *
	 * @param	int	$id
	 * @return	string/FALSE
	 */
	public function location_by_id($id) {
		if($id == 'worldwide')
			return 'Worldwide';
			
		$location = $this->get_location_info($id);
		return ($location == FALSE) ? FALSE : $location['location'];
	}

	/**
	 * Get Location Info
	 * 
	 * Function to get the array of information describing a location, 
	 * specified by it's ID. Returns FALSE on failure, otherwise the 
	 * database record for the location.
	 * 
	 * @param	string/int	id
	 * @return	string/FALSE
	 */
	public function get_location_info($id){
		if($this->bw_config->location_list_source == 'Default') {
			$this->db->where('id', $id);
			$query = $this->db->get('country_codes');
			if($query->num_rows() > 0) {
				$row = $query->row_array();
				return array('id' => $row['id'],
							 'location' => $row['country'],
							 'parent_id' => '0');
			}
		} else if($this->bw_config->location_list_source == 'Custom') {
			$this->db->where('id', $id);
			$query = $this->db->get('locations_custom_list');
			if($query->num_rows() > 0) {
				return $query->row_array();
			} 			
		}
		return FALSE;
	}

	/**
	 * Menu Human Readable
	 * 
	 * This is a recursive function which displays a heirarchy of locations
	 * in the custom location list.
	 */
	public function menu_human_readable($locations, $level, $params) {
		$content = ''; 
		$level++; 
		
		if($level !== 1) 
			$content .= "<ul>\n";

		// Pregenerate the URL. Checks for trailing slashes, fixes up
		// issues when mod_rewrite is disabled.
		// Loop through each parent category
		foreach($locations as $location) {
			//Check if were are currently viewing this category, if so, set it as active
			$content .= "<li "; 
			if(isset($params['id'])) {
				if($params['id']==$location['id'])  
					$content .= "class='active'"; 
			} $content .= ">\n";

			// Display link if category contains items. 
			$content .= '<span>'.$location['location'].'</span>';
			
			// Check if we need to recurse into children.
			if(isset($location['children']))  
				$content .= $this->menu_human_readable($location['children'], $level, $params); 
			
			$content .= "</li>\n";
		}

		if($level!==1) 
			$content .= "</ul>\n"; 

		return $content;
	}


};

