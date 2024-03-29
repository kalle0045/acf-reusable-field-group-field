<?php

	class acf_field_reusable_field_group_field extends acf_field {
		
		var $groups = array();  // groups that need to be rebuilt
		var $field_groups = array(); // to hold all field groups for processing
		var $new_field_groups = array(); // to hold fieilds after rebuilding for testing
		var $replaced_keys = array(); // field keys that need to be replaced for conditional logic
		
		function __construct() {
			$this->name = 'reusable_field_group_field';
			$this->label = __('Reusable Field Group Field', 'acf-reusable_field_group_field');
			$this->category = 'layout';
			$this->defaults = array(
				'group_key'	=> '',
				'name_prefix' => 1,
				'key_prefix' => 1,
				'label_prefix' => 0,
			);
			
			// this action is run with a very high priority to make sure that all
			// field groups are added before it runs
			add_action('acf/include_fields', array($this, 'build_field_groups'), 99);
			
			// add custom location rule (nowhere) for reusable group
			add_filter('acf/location/rule_types', array($this, 'acf_location_rules_types'));
			add_filter('acf/location/rule_values/special', array($this, 'acf_location_rules_values_special'));
			add_filter('acf/location/rule_match/nowhere', 'acf_location_rules_match_nowhere', 10, 3);
			
			// do not delete!
			parent::__construct();
				
		}
		
		function field_group_admin_head() {
			?>
				<style type="text/css">
					/*.acf-field-list .acf-field-object-reusable-field-group-field tr[data-name="name"], */
					/* acf-field-object-reusable-field_group_field */
					.acf-field-list .acf-field-object-reusable-field-group-field tr[data-name="instructions"], 
					.acf-field-list .acf-field-object-reusable-field-group-field tr[data-name="required"], 
					.acf-field-list .acf-field-object-reusable-field-group-field tr[data-name="warning"],
					.acf-field-list .acf-field-object-reusable-field-group-field tr[data-name="wrapper"],
					/*.acf-field-list .acf-field-object-reusable-field-group-field a.add-conditional-group,
					.acf-field-list .acf-field-object-reusable-field-group-field .rule-groups>h4,*/
					.acf-field-list .acf-field-object-reusable-field_group_field tr[data-name="instructions"], 
					.acf-field-list .acf-field-object-reusable-field_group_field tr[data-name="required"], 
					.acf-field-list .acf-field-object-reusable-field_group_field tr[data-name="warning"],
					.acf-field-list .acf-field-object-reusable-field_group_field tr[data-name="wrapper"]/*
					.acf-field-list .acf-field-object-reusable-field_group_field a.add-conditional-group,
					.acf-field-list .acf-field-object-reusable-field_group_field .rule-groups>h4*/ {
						display: none !important;
					}
				</style>
			<?php
		}
		
		function render_field_settings( $field ) {
			$message = '';
			$message .= '<span class="acf-error-message"><p>';
			$message .= __('Warning: It is possible to create an infinite loop by reusing field groups if resused groups create a circular reference.', 'acf-reusable_field_group_field');
			$message .= '</p></span><p style="margin-bottom: 0;">';
			$message .= __('The field name of this field will prefix all top level field names of the reused fields.', 'acf-reusable_field_group_field');
			$message .= '<br />';
			$message .= __('Example', 'acf-reusable_field_group_field');
			$message .= ':</p><pre style="margin-top: 0; background-color: #EEE; padding: .5em;">';
			$message .= __('  This Field Name = "reusable_field"', 'acf-reusable_field_group_field');
			$message .= '<br />';
			$message .= __('Reused Field Name = "text_field"', 'acf-reusable_field_group_field');
			$message .= '<br />';
			$message .= __('   New Field Name = "reusable_field_text_field"', 'acf-reusable_field_group_field');
			$message .= '</pre><p style="margin-bottom: 0;">';
			$message .= __('The field key value following "field_" will prefix the field key value of the reused fields. This will be applied to all fields and sub fields to ensure unique field keys.', 'reusable_field_text_field');
			$message .= '<br />';
			$message .= __('Example', 'acf-reusable_field_group_field');
			$message .= ':</p><pre style="margin-top: 0; background-color: #EEE; padding: .5em;">';
			$message .= __('  This Field Key = "field_56351aa7271b7"', 'acf-reusable_field_group_field');
			$message .= '<br />';
			$message .= __('Reused Field key = "field_56351ab9271b8"', 'acf-reusable_field_group_field');
			$message .= '<br />';
			$message .= __('   New Field key = "field_56351aa7271b7_56351ab9271b8"', 'acf-reusable_field_group_field');
			$message .= '</pre><p>';
			$message .= __('Please note that if the fields in the group reused here also contains reusable field groups that the above will create compound prefixes. Use nested reusable groups with care.', 'acf-reusable_field_group_field');
			$message .= __(' You can turn off field name and key prefixing, but there are only limited cases where this will not cause errors in saving and retrieving data. ', 'acf-reusable_field_group_field');
			$message .= '<br /><em><strong style="color: #A00;">';
			$message .= __('Disable field name and/or key prefixing at your own risk.', 'acf-reusable_field_group_field');
			$message .= '</strong></em></p><p>';
			$message .= __('Conditional Logic if added will be applied to all top level fields in the reused field group.', 'acf-reusable_field_group_field');
			$message .= '</p>';
			
			acf_render_field_setting( $field, array(
				'label'			=> __('Instructions', 'acf-reusable_field_group_field'),
				'instructions'	=> '',
				'type'			=> 'message',
				'message'		=> $message,
				'new_lines'		=> ''
			));
			
			acf_render_field_setting( $field, array(
				'label'			=> __('Field Group', 'acf-reusable_field_group_field'),
				'instructions'	=> 'Select the Field Group to Reuse',
				'type'			=> 'select',
				'name'			=> 'group_key',
				'choices'		=> $this->get_field_group_choices($field),
				'multiple'		=> 0,
				'ui'			=> 1,
				'allow_null'	=> 0,
				'placeholder'	=> __("No Field Group", 'acf-reusable_field_group_field'),
			));
			
			// layout
			acf_render_field_setting( $field, array(
				'label'			=> __('Field Name Prefixing', 'acf-reusable_field_group_field'),
				'instructions'	=> '',
				'type'			=> 'radio',
				'name'			=> 'name_prefix',
				'layout'		=> 'horizontal', 
				'choices'		=> array(
					1		=> __("Yes",'acf-reusable_field_group_field'), 
					0	=> __("No",'acf-reusable_field_group_field')
				)
			));
			
			acf_render_field_setting( $field, array(
				'label'			=> __('Field Key Prefixing','acf-reusable_field_group_field'),
				'instructions'	=> '',
				'type'			=> 'radio',
				'name'			=> 'key_prefix',
				'layout'		=> 'horizontal', 
				'choices'		=> array(
					1		=> __("Yes",'acf-reusable_field_group_field'), 
					0	=> __("No",'acf-reusable_field_group_field')
				)
			));
			
			acf_render_field_setting( $field, array(
				'label'			=> __('Field Label Prefixing','acf-reusable_field_group_field'),
				'instructions'	=> 'Add the label of this field as a prefix to the labels of reused fields.',
				'type'			=> 'radio',
				'name'			=> 'label_prefix',
				'layout'		=> 'horizontal', 
				'choices'		=> array(
					1		=> __("Yes",'acf-reusable_field_group_field'), 
					0	=> __("No",'acf-reusable_field_group_field')
				)
			));
			
		}
		
		function get_field_group_choices() {
			// function taken from https://github.com/tybruffy/ACF-Reusable-Field-Group, thank you
			global $post;
			$groups = acf_get_field_groups();
			$r = array();
			$current_id = is_object( $post ) ? $post->ID : $_POST['parent'];
			$current_group = _acf_get_field_group_by_id($current_id);
			foreach( $groups as $group ) {
				$key = $group["key"];
				// don't show the current field group.
				if ($key == $current_group["key"]) {
					continue;
				}
				$r[$key] = $group["title"];
			}
			return $r;
		}
		
		function build_field_groups() {
			/*
					How this works
					All acf field groups are loaded
					The fields of the field groups are inspected to find resusable groups
					if reusable groups are found then the field groups are rebuilt as local field groups
							and these replace the existing field groups
			*/
			$this->get_acf_field_groups();
			if (!count($this->field_groups)) {
				return;
			}
			foreach ($this->field_groups as $group_key => $group) {
				$this->scan_group_fields($group_key, $group['fields']);
			}
			$current_count = count($this->groups);
			$iterations = 0;
			while (count($this->groups)) {
				// make sure count of groups continues to go down
				if ($iterations > 0 && $current_count == count($this->groups)) {
					// infinite loop in reuseable field groups detected
					add_action('admin_notices', array($this, 'infinite_loop_message'));
					return;
				}
				$current_count = count($this->groups);
				foreach ($this->groups as $group_key => $sub_groups) {
					$rebuild = true;
					if (count($sub_groups)) {
						foreach ($sub_groups as $sub_group) {
							if (isset($this->groups[$sub_group])) {
								// subgroup is also a parent group
								// need to rebuild that one first
								$rebuild = false;
								break;
							}
						}
					} // end if count
					if ($rebuild) {
						$this->rebuild_group($group_key);
						unset($this->groups[$group_key]);
						// break to restart while loop
						break;
					}
				} // end foreach
				$iterations++;
			} // end while
		}
		
		function rebuild_group($group_key) {
			$group = $this->field_groups[$group_key];
			$group['fields'] = $this->rebuild_fields($group_key, $group['fields']);
			$group['fields'] = $this->replace_keys($group['fields']);
			unset($group['ID']);
			$this->new_field_groups[] = $group;
			if (acf_is_local_field_group($group_key)) {
				// this is already a local field group
				// remove the existing version before replacing
				acf_remove_local_fields($group_key);
				// there currently isn't a remove group function in acf_local
				unset(acf_local()->groups[$group_key]);
			}
			// add or replace the field group to local groups
			acf_add_local_field_group($group);
		}
		
		function rebuild_fields($parent, $fields, $parent_key='', $parent_name='', $logic='', $parent_label='') {
			// this is a rucusive function
			$new_fields = array();
			if (!count($fields)) {
				return $new_fields;
			}
			foreach ($fields as $field) {
				if ($logic) {
					if (!$field['conditional_logic']) {
						$field['conditional_logic'] = $logic;
					} else {
						$new_logic = array();
						foreach ($field['conditional_logic'] as $field_logic) {
							foreach ($logic as $add_logic) {
								$new_logic[] = array_merge($add_logic, $field_logic);
							}
						}
						$field['conditional_logic'] = $new_logic;
					}
				}
				if ($field['type'] != 'reusable_field_group_field') {
					if ($parent_label) {
						$field['label'] = $parent_label.' - '.$field['label'];
					}
					$old_key = $field['key'];
					$old_name = $field['name'];
					$new_key = preg_replace('/^field_/', '', $field['key']);
					if ($parent_key) {
						$new_key = $parent_key.'_'.$new_key;
					}
					$field['parent'] = $parent;
					$field['key'] = 'field_'.$new_key;
					$new_parent = $field['key'];
					if ($parent_name) {
						$field['name'] = $parent_name.'_'.$field['name'];
					}
					$this->replaced_keys[$old_key] = $field['key'];
					// unset anything that may cause a problem when adding local group
					$unsets = array('ID', '_name', '_valid', 'parent');
					foreach ($unsets as $unset) {
						if (isset($field[$unset])) {
							unset($field[$unset]);
						}
					}
					$new_name = '';
					$new_parent = $field['key'];
					$sub_fields = false;
					if (isset($field['sub_fields']) && 
							is_array($field['sub_fields']) && 
							count($field['sub_fields'])) {
						// recursive call to add subfields of a reeater or flex field
						$field['sub_fields'] = $this->rebuild_fields(
							$new_parent,
							$field['sub_fields'],
							$parent_key,
							$new_name
						);
					} elseif (isset($field['layouts']) &&
										is_array($field['layouts']) &&
										count($field['layouts'])) {
						foreach ($field['layouts'] as $index => $layout) {
							if (isset($layout['sub_fields']) &&
									is_array($layout['sub_fields']) &&
									count($layout['sub_fields'])) {
								$layout['sub_fields'] = $this->rebuild_fields(
									$new_parent,
									$layout['sub_fields'],
									$parent_key,
									$new_name
								);
								$field['layouts'][$index] = $layout;
							} // end if layouts
						} // end each layout
					} // end if subfields elsif layouts
					$new_fields[] = $field;
				} else {
					// reusable field
					if ($field['key_prefix']) {
						$new_key = preg_replace('/^field_/', '', $field['key']);
						if ($parent_key) {
							$new_key = $parent_key.'_'.$new_key;
						}
					} else {
						$new_key = $parent_key;
					}
					$sub_fields = false;
					if ($field['group_key']) {
						// get the field list from the reused field group
						$sub_fields = $this->field_groups[$field['group_key']]['fields'];
					}
					if ($field['name_prefix']) {
						$new_name = $field['name'];
					} else {
						$new_name = '';
					}
					$new_parent = $parent;
					$new_logic = '';
					if (is_array($field['conditional_logic'])) {
						$new_logic = $field['conditional_logic'];
					}
					$label = '';
					if ($field['label_prefix']) {
						$label = $field['label'];
					}
					if ($sub_fields) {
						// recursive call to merge reused fields into group
						$new_fields = array_merge(
							$new_fields, 
							$this->rebuild_fields(
								$new_parent,
								$sub_fields,
								$new_key,
								$new_name,
								$new_logic,
								$label
							)
						);
					} // end if sub fields
				} // if ! reusable else
			} // end foreach $fields;
			return $new_fields;
		} // end function rebuild_fields
		
		function get_acf_field_groups() {
			$field_groups = acf_get_field_groups();
			$count = count($field_groups);
			for ($i=0; $i<$count; $i++) {
				$fields = acf_get_fields($field_groups[$i]['key']);
				$field_groups[$i]['fields'] = $fields;
				$this->field_groups[$field_groups[$i]['key']] = $field_groups[$i];
			}
		}
		
		function scan_group_fields($group, $fields) {
			// recursive function
			if (!count($fields)) {
				return;
			}
			foreach ($fields as $field) {
				if ($field['type'] == 'reusable_field_group_field') {
					if (!isset($this->groups[$group])) {
						$this->groups[$group] = array();
					}
					if ($field['group_key'] != '') {
						$this->groups[$group][] = $field['group_key'];
					}
					if (isset($field['sub_fields']) &&
							is_array($field['sub_fields'])) {
						// recursive calle
						$this->scan_group_fields($group, $field['sub_fields']);
					} elseif (isset($field['layouts']) &&
										is_array($field['layouts'])) {
						$this->scan_group_fields($group, $field['layouts']);
					}
				} // end if is reusable
			} // end foreach $field
		} // end function scan_group_fields
			
		function replace_keys($array) {
			// this is called after the copy process to replace 
			// altered field keys in conditional logic and such
			// this is a recuresive function
			$copied_array = array();
			if (count($array)) {
				foreach ($array as $key => $value) {
					if (is_array($value)) {
						// recursive call here
						$copied_array[$key] = $this->replace_keys($value);
					} else {
						if (isset($this->replaced_keys[$value])) {
							// replace field key value
							$value = $this->replaced_keys[$value];
						}
						$copied_array[$key] = $value;
					} // end if else
				} // end foreach field
			} // end if count fields
			// reset keys that need to be replaced
			$this->replaced_keys = array();
			return $copied_array;
		}
		
		function acf_location_rules_types($choices) {
			// add a new group called special
			if (!isset($choices['Special'])) {
				$choices['Special'] = array('special' => 'Special');
			}
			return $choices;
		}
		
		function acf_location_rules_values_special($choices) {
			// add a nowhere choice
			if (!isset($choices['nowhere'])) {
				$choices['nowhere'] = 'Nowhere (operator ignored)';
			}
			return $choices;
		}
		
		function acf_location_rules_match_nowhere($match, $rule, $options) {
			// always returns false so that field group is never shown
			return false;
		}
		
		function infinite_loop_message() {
			?>
				<div class="error">
					<p>
						<strong>
							<?php _e('ERROR! Infinite Loop Detected in Reusable Field Groups!<br />Operation Aborted!', 'acf-reusable_field_group_field'); ?>
						</strong>
					</p>
				</div>
			<?php 
		}
	}
	
	// create field
	new acf_field_reusable_field_group_field();

?>
