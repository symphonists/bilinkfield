<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	class FieldBiLink extends Field {
		protected $_driver = null;
		
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function __construct(&$parent) {
			parent::__construct($parent);
			
			$this->_name = 'Bi Link';
			$this->_required = true;
			$this->_driver = $this->_engine->ExtensionManager->create('subsectionfield');
			
			// Set defaults:
			$this->set('show_column', 'yes');
		}
		
		public function createTable() {
			$field_id = $this->get('id');
			
			return $this->_engine->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`linked_entry_id` int(11) unsigned default NULL,
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `linked_entry_id` (`linked_entry_id`)
				)
			");
		}
		
		public function canFilter() {
			return true;
		}
		
		public function allowDatasourceParamOutput() {
			return true;
		}
		
	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		
		protected function getFields() {
			$sectionManager = new SectionManager($this->_engine);
			$section = $sectionManager->fetch($this->get("linked_section_id"));
			
			if (empty($section)) return null;
			
			return $section->fetchFields();
		}
		
	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/
		
		public function findDefaults(&$fields) {
			if (!isset($fields['allow_multiple'])) $fields['allow_multiple'] = 'yes';
			if (!isset($fields['column_size'])) $fields['column_size'] = 'medium';
		}
		
		public function findOptions() {
			$sectionManager = new SectionManager($this->_engine);
		  	$sections = $sectionManager->fetch(null, 'ASC', 'name');
			$groups = $options = array();
			
			if (is_array($sections) and !empty($sections)) {
				foreach ($sections as $section) {
					$groups[$section->get('id')] = array(
						'fields'	=> $section->fetchFields(),
						'section'	=> $section
					);
				}
			}
			
			$options[] = array('', '', __('None'));
			
			foreach ($groups as $group) {
				if (!is_array($group['fields'])) continue;
				
				$fields = array();
				
				foreach ($group['fields'] as $field) {
					if (
						$field->get('type') == 'bilink'
						and $field->get('id') != $this->get('id')
					) {
						$selected = $this->get('linked_field_id') == $field->get('id');
						$fields[] = array(
							$field->get('id'), $selected, $field->get('label')
						);
					}
				}
				
				if (empty($fields)) continue;
				
				$options[] = array(
					'label'		=> $group['section']->get('name'),
					'options'	=> $fields
				);
			}
			
			return $options;
		}
		
		public function findModes() {
			$modes = array(
				array('count', false, 'Entry Count'),
				array('first-item', false, 'First Item'),
				array('last-item', false, 'Last Item')
			);
			
			foreach ($modes as &$mode) {
				$mode[1] = ($mode[0] == $this->get('column_mode'));
			}
			
			return $modes;
		}
		
		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);
			
			$field_id = $this->get('id');
			$order = $this->get('sortorder');
			
		// Linked -------------------------------------------------------------
		
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Options'));
			
			$label->appendChild(Widget::Select(
				"fields[{$order}][linked_field_id]", $this->findOptions()
			));
			
			if (isset($errors['linked_field_id'])) {
				$label = Widget::wrapFormElementWithError($label, $errors['linked_field_id']);
			}
			
			$group->appendChild($label);
			
		// Column Mode --------------------------------------------------------
			
			$label = Widget::Label(__('Column Mode'));
			
			$label->appendChild(Widget::Select(
				"fields[{$order}][column_mode]", $this->findModes()
			));
			
			if (isset($errors['column_mode'])) {
				$label = Widget::wrapFormElementWithError($label, $errors['column_mode']);
			}
			
			$group->appendChild($label);
			$wrapper->appendChild($group);
			
		// Allow Multiple -----------------------------------------------------
			
			$label = Widget::Label();
			$input = Widget::Input(
				"fields[{$order}][allow_multiple]", 'yes', 'checkbox'
			);
			
			if ($this->get('allow_multiple') == 'yes') $input->setAttribute('checked', 'checked');
			
			$label->setValue($input->generate() . ' ' . __('Allow selection of multiple options'));
			
			$wrapper->appendChild($label);
			$this->appendShowColumnCheckbox($wrapper);
			$this->appendRequiredCheckbox($wrapper);
		}
		
		public function commit() {
			if (!parent::commit() or $field_id === false) return false;
			
			$field_id = $this->get('id');
			$handle = $this->handle();
			
			$linked_field_id = $this->get('linked_field_id');
			$linked_section_id = $this->_engine->Database->fetchVar('parent_section', 0, "
				SELECT
					f.parent_section
				FROM
					`tbl_fields` AS f
				WHERE
					f.id = {$linked_field_id}
				LIMIT 1
			");
			
			$fields = array(
				'field_id'			=> $this->get('id'),
				'linked_section_id'	=> $linked_section_id,
				'linked_field_id'	=> $linked_field_id,
				'allow_multiple'	=> ($this->get('allow_multiple') ? $this->get('allow_multiple') : 'no'),
				'column_mode'		=> $this->get('column_mode')
			);
			
		// Cleanup ------------------------------------------------------------
			
			$this->_engine->Database->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '$field_id'
				LIMIT 1
			");
			
		// Create -------------------------------------------------------------
			
			if (!$this->_engine->Database->insert($fields, "tbl_fields_{$handle}")) return false;
			
			// Update child field:
			if ($linked_field_id) {
				$fieldManager = new FieldManager($this->_engine);
				$field = $fieldManager->fetch($linked_field_id);
				
				if (is_object($field) and $field->get('linked_field_id') != $field_id) {
					$field->set('linked_section_id', $this->get('parent_section'));
					$field->set('linked_field_id', $field_id);
					$field->commit();
				}
			}
			
			return true;
		}
		
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
		
		public function findEntries($entry_ids) {
			$entryManager = new EntryManager($this->_engine);
			$entries = $entryManager->fetch(null, $this->get('linked_section_id'));
			$options = array();
			
			if ($this->get('required') != 'yes') $options[] = array(null, false, null);
			
			if (empty($entries)) return $options;
			
			header('content-type: text/plain');
			
			foreach ($entries as $order => $entry) {
				if (!is_object($entry)) continue;
				
				$section = $entry->getSection();
				
				if (!is_object($section)) continue;
				
				$field = current($section->fetchVisibleColumns());
				
				if (!is_object($field)) continue;
				
				$selected = in_array($entry->get('id'), $entry_ids);
				
				$value = $field->prepareTableValue(
					$entry->getData($field->get('id'))
				);
				
				$options[] = array(
					$entry->get('id'), $selected, $value
				);
			}
			
			return $options;
		}
		
		public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null) {
			$handle = $this->get('element_name');
			
			if (!is_array($data['linked_entry_id'])) {
				$entry_ids = array($data['linked_entry_id']);
				
			} else {
				$entry_ids = $data['linked_entry_id'];
			}
			
			$options = $this->findEntries($entry_ids);
			
			$fieldname = "fields{$prefix}[{$handle}]{$postfix}";
			
			if ($this->get('allow_multiple') == 'yes') $fieldname .= '[]';
			
			$label = Widget::Label($this->get('label'));
			$select = Widget::Select($fieldname, $options);
			
			if ($this->get('allow_multiple') == 'yes') {
				$select->setAttribute('multiple', 'multiple');
			}
			
			$label->appendChild($select);
			
			if ($error != null) {
				$label = Widget::wrapFormElementWithError($label, $error);
			}
			
			$wrapper->appendChild($label);
		}
		
	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/
		
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$field_id = $this->get('id');
			$status = self::__OK__;
			
			if (!is_array($data)) $data = array($data);
			
			if (empty($data)) return null;
			
			$result = array();
			
			foreach ($data as $a => $value) { 
				$result['linked_entry_id'][] = $data[$a];
			}
			
			// Update linked field:
			// TODO: Delete relations when entries are deselected.
			header('content-type: text/plain');
			//var_dump($entry_id);
			
			$remove = $this->_engine->Database->fetchCol('linked_entry_id',
				sprintf("
					SELECT
						f.linked_entry_id
					FROM
						`tbl_entries_data_{$field_id}` AS f
					WHERE
						f.entry_id = '{$entry_id}'
				")
			);
			
			$remove = array_diff($remove, $data);
			
			if (!$simulate) {
				$entryManager = new EntryManager($this->_engine);
				
				// Remove old entries:
				foreach ($remove as $linked_entry_id) {
					if (is_null($linked_entry_id)) continue;
					
					$entry = @current($entryManager->fetch($linked_entry_id, $this->get('linked_section_id')));
					
					if (!is_object($entry)) continue;
					
					$values = $entry->getData($this->get('linked_field_id'));
					
					if (isset($values['linked_entry_id'])) $values = $values['linked_entry_id'];
					
					if (!is_array($values)) $values = array($values);
					
					$values = array_diff($values, array($entry_id));
					
					//var_dump($values);
					
					//$entry->setData($this->get('linked_field_id'), array(
					//	'linked_entry_id'	=> $values
					//));
					//$entry->commit();
				}
				
				// Link new entries:
				foreach ($data as $linked_entry_id) {
					if (is_null($linked_entry_id)) continue;
					
					$entry = @current($entryManager->fetch($linked_entry_id, $this->get('linked_section_id')));
					
					if (!is_object($entry)) continue;
					
					$values = $entry->getData($this->get('linked_field_id'));
					
					//if (isset($values['linked_entry_id'])) $values = $values['linked_entry_id'];
					
					//if (!is_array($values)) $values = array($values);
					
					var_dump($values);
					
					continue;
					
					if (!is_array($values)) {
						$values = array(
							'linked_entry_id'	=> $entry_id
						);
						
					} else if (!in_array($entry_id, $values)) {
						$values['linked_entry_id'][] = $entry_id;
					}
					
					if (is_array($remove)) $values = array_diff($values, $remove);
					
					//$entry->setData($this->get('linked_field_id'), array(
					//	'linked_entry_id'	=> $values
					//));
					//$entry->commit();
				}
				
				exit;
			}
			
			return $result;
		}
	}
	
?>