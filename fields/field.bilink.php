<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	class FieldBiLink extends Field {
		protected $_driver = null;
		public $_ignore = array();
		private $_linked_field = NULL;
		static public $errors = array();
		static public $entries = array();
		protected $is_fail = true;
		
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function __construct(&$parent) {
			parent::__construct($parent);
			
			$this->_name = 'Bi-Link';
			$this->_required = true;
			$this->_driver = $this->_engine->ExtensionManager->create('bilinkfield');
			
			// Set defaults:
			$this->set('show_column', 'yes');
		}
		
		public function createTable() {
			$field_id = $this->get('id');
			
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`linked_entry_id` INT(11) UNSIGNED DEFAULT NULL,
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
		
		public function entryDataCleanup($entry_id, $data = null) {
			$entryManager = new EntryManager($this->_engine);
			$field_id = $this->get('linked_field_id');
			$entry_ids = Symphony::Database()->fetchCol('linked_entry_id', sprintf(
				"
					SELECT
						f.linked_entry_id
					FROM
						`tbl_entries_data_%s` AS f
					WHERE
						f.entry_id = '%s'
				",
				$this->get('id'),
				$entry_id
			));
			$entries = $entryManager->fetch($entry_ids, $this->get('linked_section_id'));
			
			if ($entries) foreach ($entries as $entry) {
				if (!is_object($entry)) continue;
				
				$values = $entry->getData($field_id);
				
				if (is_array($values) and array_key_exists('linked_entry_id', $values)) {
					$values = $values['linked_entry_id'];
				}
				
				if (is_null($values)) {
					$values = array();
				}
				
				else if (!is_array($values)) {
					$values = array($values);
				}
				
				$values = array_diff($values, array($entry_id));
				
				if (empty($values)) {
					$values = null;
				}
				
				$entry->setData($field_id, array(
					'linked_entry_id'	=> $values
				));
				$entry->commit();
			}
			
			return parent::entryDataCleanup($entry_id, $data);
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
		
		public function getLinkedField() {
			if (!($this->_linked_field instanceof StdClass)) {
				$this->_linked_field = (object)Symphony::Database()->fetchRow(0, 
					"SELECT `allow_multiple` FROM `tbl_fields_bilink` WHERE `field_id` = ".$this->get('linked_field_id')." LIMIT 1"
				);
			}
			
			return $this->_linked_field;
		}
		
		protected function isOneToManyRelationship() {
			return (strcasecmp($this->getLinkedField()->allow_multiple, 'no') === 0);
		}
		
	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/
		
		public function findDefaults(&$fields) {
			if (!isset($fields['allow_editing'])) $fields['allow_editing'] = 'no';
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
			
		// Allow Editing -----------------------------------------------------
			
			$label = Widget::Label();
			$input = Widget::Input(
				"fields[{$order}][allow_editing]", 'yes', 'checkbox'
			);
			
			if ($this->get('allow_editing') == 'yes') $input->setAttribute('checked', 'checked');
			
			$label->setValue($input->generate() . ' ' . __('Allow editing of linked entries'));
			$wrapper->appendChild($label);
			
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
			
			$linked_field_id = (integer)$this->get('linked_field_id');
			$linked_section_id = Symphony::Database()->fetchVar('parent_section', 0, sprintf(
				"
					SELECT
						f.parent_section
					FROM
						`tbl_fields` AS f
					WHERE
						f.id = %s
					LIMIT 1
				",
				$linked_field_id
			));
			
			$fields = array(
				'field_id'			=> $this->get('id'),
				'linked_section_id'	=> $linked_section_id,
				'linked_field_id'	=> $linked_field_id,
				'allow_editing'		=> ($this->get('allow_editing') ? $this->get('allow_editing') : 'no'),
				'allow_multiple'	=> ($this->get('allow_multiple') ? $this->get('allow_multiple') : 'no'),
				'column_mode'		=> $this->get('column_mode')
			);
			
		// Cleanup ------------------------------------------------------------
			
			Symphony::Database()->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '$field_id'
				LIMIT 1
			");
			
		// Create -------------------------------------------------------------
			
			if (!Symphony::Database()->insert($fields, "tbl_fields_{$handle}")) return false;
			
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
		
		public function findEntries($entry_ids, $current_entry_id = null, $limit = 50) {
			if (!is_array($entry_ids)) {
				if (is_null($entry_ids)) $entry_ids = array();
				else $entry_ids = array($entry_ids);
			}
			
			$sectionManager = new SectionManager($this->_engine);
			$section = $sectionManager->fetch($this->get('linked_section_id'));
			$entryManager = new EntryManager($this->_engine);
			$count = $entryManager->fetchCount($this->get('linked_section_id'));
			$entries = $entryManager->fetch(null, $this->get('linked_section_id'), $limit);
			$options = array(); $entry_ids = array_unique($entry_ids);
			
			if ($count > $limit) {
				$remove_ids = $extra_ids = array();
				
				foreach ($entries as $entry) if (in_array($entry->get('id'), $entry_ids)) {
					$remove_ids[] = $entry->get('id');
				}
				
				$extra_ids = array_diff($entry_ids, $remove_ids);
				
				if (!empty($extra_ids)) {
					$extra_entries = $entryManager->fetch($extra_ids, $this->get('linked_section_id'));
					
					if (is_array($extra_entries)) $entries = array_merge($entries, $extra_entries);
				}
			}
			
			if (!is_object($section) or empty($entries)) return $options;
			
			foreach ($entries as $order => $entry) {
				if (!is_object($entry)) continue;
				
				$field = current($section->fetchVisibleColumns());
				
				if (!is_object($field) or $current_entry_id == $entry->get('id')) continue;
				
				if (is_array($entry_ids)) {
					$selected = in_array($entry->get('id'), $entry_ids);
				}
				
				else {
					$selected = false;
				}
				
				$value = $field->prepareTableValue(
					$entry->getData($field->get('id'))
				);
				
				if ($value instanceof XMLElement) {
					$value = $value->generate();
				}
				
				$options[] = array(
					$entry->get('id'), $selected, $value
				);
			}
			
			return $options;
		}
		
		public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$this->_driver->addHeaders($this->_engine->Page);
			$handle = $this->get('element_name'); $entry_ids = array();
			$field_id = $this->get('id');
			
			if (!is_array($data['linked_entry_id']) and !is_null($data['linked_entry_id'])) {
				$entry_ids = array($data['linked_entry_id']);
			}
			
			else {
				$entry_ids = $data['linked_entry_id'];
			}
			
			if ($this->get('allow_editing') != 'yes') {
				$options = $this->findEntries($entry_ids, $entry_id);
				
				$fieldname = "fields{$prefix}[{$handle}]{$postfix}";
				
				if ($this->get('allow_multiple') == 'yes') {
					$fieldname .= '[]';
				}
				
				else if ($this->get('required') != 'yes') {
					array_unshift($options, array(null, false, null));
				}
				
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
			
			else {
				$label = new XMLElement('h3', $this->get('label'));
				$label->setAttribute('class', 'label');
				$wrapper->appendChild($label);
				
				$ol = new XMLElement('ol');
				
				if ($this->get('allow_multiple') == 'yes') {
					$ol->setAttribute('class', 'multiple');
				}
				
				else {
					$ol->setAttribute('class', 'single');
				}
				
				$sectionManager = new SectionManager($this->_engine);
				$section = $sectionManager->fetch($this->get('linked_section_id'));
				$entryManager = new EntryManager($this->_engine);
				$possible_entries = $entryManager->fetch(null, $this->get('linked_section_id'), 25);
				$fields = array(); $first = null;
				
				if ($section) {
					$fields = $section->fetchFields();
					$first = array_shift($section->fetchVisibleColumns());
				}
				
				$this->displayItem($ol, __('New'), -1, $entryManager->create(), $first, $fields, $prefix, $postfix);
				
				if (self::$entries[$field_id]) {
					foreach (self::$entries[$field_id] as $order => $entry) {
						$this->displayItem($ol, __('None'), $order, $entry, $first, $fields, $prefix, $postfix);
					}
				}
				
				else if ($entry_ids) {
					$linked_entries = $entryManager->fetch($entry_ids, $this->get('linked_section_id'));
					
					if ($linked_entries) {
						foreach ($linked_entries as $index => $entry) {
							unset($linked_entries[$index]);
							$linked_entries[$entry->get('id')] = $entry;
						}
						
						foreach ($entry_ids as $order => $linked_entry) {
							if (!isset($linked_entries[$linked_entry])) continue;
							
							$entry = $linked_entries[$linked_entry];
							$this->displayItem($ol, __('None'), $order, $entry, $first, $fields, $prefix, $postfix);
						}
					}
				}
				
				if ($possible_entries) foreach ($possible_entries as $order => $entry) {
					if (is_array($entry_ids) and in_array($entry->get('id'), $entry_ids)) continue;
					
					$this->displayItem($ol, __('None'), -1, $entry, $first, $fields, $prefix, $postfix);
				}
				
				$wrapper->appendChild($ol);
				
				if ($error != null) {
					$wrapper = Widget::wrapFormElementWithError($wrapper, $error);
				}
			}
		}
		
		protected function displayItem($wrapper, $title, $order, $entry, $first, $fields, $prefix, $postfix) {
			$handle = $this->get('element_name');
			
			if ($first and $entry->getData($first->get('id'))) {
				$new_title = $first->prepareTableValue(
					$entry->getData($first->get('id'))
				);
				
				if ($new_title instanceof XMLElement) {
					$new_title = $new_title->generate();
				}
				
				if ($new_title != '') $title = $new_title;
			}
			
			$item = new XMLElement('li');
			$item->appendChild(new XMLElement('h4', strip_tags($title)));
			
			$input = Widget::Input(
				"fields{$prefix}[{$handle}][entry_id][{$order}]",
				$entry->get('id')
			);
			$input->setAttribute('type', 'hidden');
			$item->appendChild($input);
			
			$group = new XMLElement('div');
			
			$left = new XMLElement('div');
			$right = new XMLElement('div');
			
			if ($order < 0) {
				$item->setAttribute('class', 'template');
			}
			
			foreach ($fields as $field) {
				if ($field->get('linked_section_id') == $this->get('parent_section')) continue;
				
				$name = "{$prefix}[{$handle}][entry][{$order}]";
				$data = $entry->getData($field->get('id'));
				$error = self::$errors[$this->get('id')][$order][$field->get('id')];
				
				if ($this->get('location') != 'main') {
					$container = $group;
				}
				
				else if ($field->get('location') == 'main') {
					$container = $left;
				}
				
				else {
					$container = $right;
				}
				
				if ($field->get('type') == 'bilink') {
					$field->set('allow_editing', 'no');
				}
				
				$div = new XMLElement('div');
				$div->setAttribute('class', sprintf(
					'field field-%s%s',
					$field->handle(),
					($field->get('required') == 'yes' ? ' required' : '')
				));
				
				$field->displayPublishPanel($div, $data, $error, $name, $postfix, $entry->get('id'));
				
				$container->appendChild($div);
			}
			
			if ($this->get('location') == 'main') {
				$group->setAttribute('class', 'group');
				$group->appendChild($left);
				$group->appendChild($right);
			}
			
			$item->appendChild($group);
			$wrapper->appendChild($item);
		}
		
	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/
		
		public function checkPostFieldData($data, &$error = null, $entry_id = null) {
			if (isset($data['entry']) and is_array($data['entry'])) {
				$entryManager = new EntryManager($this->_engine);
				$fieldManager = new FieldManager($this->_engine);
				$field = $fieldManager->fetch($this->get('linked_field_id'));
				$field_id = $this->get('id');
				$status = self::__OK__;
				
				self::$errors[$field_id] = array();
				self::$entries[$field_id] = array();
				
				// Create:
				foreach ($data['entry'] as $index => $entry_data) {
					$existing_id = (integer)$data['entry_id'][$index];
					
					if ($existing_id <= 0) {
						if ($this->_engine->Author) {
							$author_id = $this->_engine->Author->get('id');
						}
						
						else {
							$author_id = '1';
						}
						
						$entry = $entryManager->create();
						$entry->set('section_id', $this->get('linked_section_id'));
						$entry->set('author_id', $author_id);
						$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
						$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
						$entry->assignEntryId();
					}
					
					else {
						$entry = @current($entryManager->fetch($existing_id, $this->get('linked_section_id')));
					}
					
					// Append correct linked data:
					$existing_data = $entry->getData($this->get('linked_field_id'));
					$existing_entries = array();
					
					if (isset($existing_data['linked_entry_id'])) {
						if (!is_array($existing_data['linked_entry_id'])) {
							$existing_entries[] = $existing_data['linked_entry_id'];
						}
						
						else foreach ($existing_data['linked_entry_id'] as $linked_entry_id) {
							$existing_entries[] = $linked_entry_id;
						}
					}
					
					if (!in_array($entry_id, $existing_entries)) {
						$existing_entries[] = $entry_id;
					}
					
					$entry_data[$field->get('element_name')] = $existing_entries;
					
					// Validate:
					if (__ENTRY_FIELD_ERROR__ == $entry->checkPostData($entry_data, $errors)) {
						self::$errors[$field_id][$index] = $errors;
						
						$status = self::__INVALID_FIELDS__;
					}
					
					if (__ENTRY_OK__ != $entry->setDataFromPost($entry_data, $error)) {
						$status = self::__INVALID_FIELDS__;
					}
					
					// Cleanup dud entry:
					if ($existing_id == 0 and $status != self::__OK__) {
						$existing_id = $entry->get('id');
						$entry->set('id', 0);
						
						Symphony::Database()->delete('tbl_entries', " `id` = '$existing_id' ");
					}
					
					self::$entries[$field_id][$index] = $entry;
				}
				
				return $status;
			}
			
			return parent::checkPostFieldData($data, $error, $entry_id);
		}
		
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$field_id = $this->get('id');
			$status = self::__OK__;
			$result = array();
			
			if (!empty(self::$entries[$field_id])) {
				$new_data = array();
				
				if (is_array($data)) foreach ($data as $item) {
					if (!is_array($item)) $new_data[] = $item;
				}
				
				foreach (self::$entries[$field_id] as $entry) {
					if ($entry->get('id') == 0) continue;
					
					$entry->commit();
					$new_data[] = $entry->get('id');
				}
				
				$data = $new_data;
			}
			
			if (empty($data)) {
				return null;
			}
			
			if (!is_array($data)) {
				$data = array($data);
			}
			
			foreach ($data as $a => $value) {
				$result['linked_entry_id'][] = $data[$a];
			}
			
			// Update linked field:
			$remove = Symphony::Database()->fetchCol('linked_entry_id',
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
			
			if ($simulate) return $result;
			
			$entryManager = new EntryManager($this->_engine);
			
			// We need to also remove any other entries linking to the selected 
			// if the linked field is single select. This is to maintain any
			// one-to-many or one-to-one relationships
			if ($this->getLinkedField()->allow_multiple == 'no') {
				Symphony::Database()->query(sprintf(
					"
						DELETE FROM
							`tbl_entries_data_%s`
						WHERE
							`linked_entry_id` IN ('%s')
					",
					$field_id,
					@implode("','", $data)
				));
				Symphony::Database()->query(sprintf(
					"
						DELETE FROM
							`tbl_entries_data_%s`
						WHERE
							`entry_id` IN ('%s')
					",
					$this->get('linked_field_id'),
					@implode("','", $data)
				));
			}
			
			// Remove old entries:
			foreach ($remove as $linked_entry_id) {
				if (is_null($linked_entry_id)) continue;
				
				$entry = @current($entryManager->fetch($linked_entry_id, $this->get('linked_section_id')));
				
				if (!is_object($entry)) continue;
				
				$values = $entry->getData($this->get('linked_field_id'));
				
				if (is_array($values) && array_key_exists('linked_entry_id', $values)) {
					$values = $values['linked_entry_id'];
				}
				
				if (is_null($values)) {
					$values = array();
				}
				
				else if (!is_array($values)) {
					$values = array($values);
				}
				
				$values = array_values(array_diff($values, array($entry_id)));
				
				// This ensures that the MySQL::insert() function does not
				// end up creating invalid SQL (bug with Symphony <= 2.0.6)
				if (count($values) == 1) {
					$values = $values[0];
				}
				
				if (empty($values)) {
					$values = null;
				}
				
				$entry->setData($this->get('linked_field_id'), array(
					'linked_entry_id'	=> $values
				));
				
				$entry->commit();
			}
			
			// Link new entries:
			foreach ($data as $linked_entry_id) {
				if (is_null($linked_entry_id)) continue;
				
				$entry = @current($entryManager->fetch($linked_entry_id, $this->get('linked_section_id')));
				
				if (!is_object($entry)) continue;
				
				$values = $entry->getData($this->get('linked_field_id'));
				
				if (is_array($values) && array_key_exists('linked_entry_id', $values)) {
					$values = $values['linked_entry_id'];
				}
				
				if (is_null($values)) {
					$values = array();
				}
				
				else if (!is_array($values)) {
					$values = array($values);
				}
				
				if (!in_array($entry_id, $values)) {
					$values[] = $entry_id;
				}
				
				// This ensures that the MySQL::insert() function does not
				// end up creating invalid SQL (bug with Symphony <= 2.0.6)
				if (count($values) == 1) {
					$values = array_values($values);
					$values = $values[0];
				}
				
				if (empty($values)) {
					$values = null;
				}
				
				$entry->setData($this->get('linked_field_id'), array(
					'linked_entry_id'	=> $values
				));
				
				$entry->commit();
			}
			
			if ($entry) {
				if (!is_array($values)) {
					$values = array($values);
				}
				
				if (!in_array($entry_id, $values)) {
					$values[] = $entry_id;
				}
				
				if (empty($values)) {
					$values = null;
				}
				
				$entry->setData($this->get('linked_field_id'), array(
					'linked_entry_id'	=> $values
				));
				$entry->commit();
			}
			
			return $result;
		}
		
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function getParameterPoolValue($data) {
			if (!is_array($data['linked_entry_id'])) {
				return array($data['linked_entry_id']);
			}
			
			return $data['linked_entry_id'];
		}
		
		public function fetchIncludableElements() {
			return array(
				$this->get('element_name') . ': count',
				$this->get('element_name') . ': items',
				$this->get('element_name') . ': entries'
			);
		}
		
		public function prepareData($data) {
			if (!isset($data['linked_entry_id'])) {
				return array(
					'linked_entry_id'	=> array()
				);
			}
			
			if (!is_array($data['linked_entry_id'])) {
				$data['linked_entry_id'] = array($data['linked_entry_id']);
			}
			
			if (is_null($data['linked_entry_id'])) {
				$data['linked_entry_id'] = array();
			}
			
			else if (!is_array($data['linked_entry_id'])) {
				$data['linked_entry_id'] = array($data['linked_entry_id']);
			}
			
			return $data;
		}
		
		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
			$sectionManager = new SectionManager($this->_engine);
			$entryManager = new EntryManager($this->_engine);
			$linked_section_id = $this->get('linked_section_id');
			$section = $sectionManager->fetch($linked_section_id);
			$data = $this->prepareData($data);
			$entry_ids = array();
			
			if (!is_array($data['linked_entry_id']) and !is_null($data['linked_entry_id'])) {
				$entry_ids = array($data['linked_entry_id']);
			}
			
			else {
				$entry_ids = $data['linked_entry_id'];
			}
			
			$list = new XMLElement($this->get('element_name'));
			$list->setAttribute('mode', $mode);
			$list->setAttribute('entries', count($data['linked_entry_id']));
			
			// No section or relations:
			if (!is_object($section)) {
				$list->setAttribute('entries', 0);
				$wrapper->appendChild($list);
				return;
			}
			
			if ($mode == null) $mode = 'items';
			
			$entries = $entryManager->fetch($entry_ids, $linked_section_id);
			$list->setAttribute('entries', count($entries));
			
			// List:
			if ($mode == 'items') {
				$list->appendChild(new XMLElement(
					'section', $section->get('name'),
					array(
						'id'		=> $section->get('id'),
						'handle'	=> $section->get('handle')
					)
				));
				$field = @current($section->fetchVisibleColumns());
				
				foreach ($entries as $index => $entry) {
					unset($entries[$index]);
					$entries[$entry->get('id')] = $entry;
				}
				
				foreach ($entry_ids as $order => $entry) {
					if (!isset($entries[$entry]) or empty($entries[$entry])) continue;
					
					$entry = $entries[$entry];
					$value = $field->prepareTableValue(
						$entry->getData($field->get('id')),
						new XMLElement('span'),
						$entry_id
					);
					
					if ($value instanceof XMLElement) {
						$value = $value->generate();
					}
					
					$value = strip_tags($value);
					$handle = Lang::createHandle($value);
					
					$item = new XMLElement('item', General::sanitize($value));
					$item->setAttribute('id', $entry->get('id'));
					$item->setAttribute('handle', $handle);
					
					$list->appendChild($item);
				}
			}
			
			// Full:
			else if ($mode == 'entries') {
				$list->appendChild(new XMLElement(
					'section', $section->get('name'),
					array(
						'id'		=> $section->get('id'),
						'handle'	=> $section->get('handle')
					)
				));
				
				foreach ($entries as $index => $entry) {
					unset($entries[$index]);
					$entries[$entry->get('id')] = $entry;
				}
				
				foreach ($entry_ids as $order => $entry) {
					if (!isset($entries[$entry]) or empty($entries[$entry])) continue;
					
					$entry = $entries[$entry];
					$associated = $entry->fetchAllAssociatedEntryCounts();
					$data = $entry->getData();
					
					$item = new XMLElement('entry');
					$item->setAttribute('id', $entry->get('id'));
					
					if (is_array($associated) and !empty($associated)) {
						foreach ($associated as $section => $count) {
							$handle = Symphony::Database()->fetchVar('handle', 0, "
								SELECT
									s.handle
								FROM
									`tbl_sections` AS s
								WHERE
									s.id = '{$section}'
								LIMIT 1
							");
							
							$item->setAttribute($handle, (string)$count);
						}
					}
					
					// Add fields:
					foreach ($data as $field_id => $values) {
						$field = $entryManager->fieldManager->fetch($field_id);
						
						if ($field->get('type') == $this->get('type')) continue;
						
						$field->appendFormattedElement($item, $values, false, null);
					}
					
					$list->appendChild($item);
				}
			}
			
			$wrapper->appendChild($list);
		}
		
		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			$sectionManager = new SectionManager($this->_engine);
			$section = $sectionManager->fetch($this->get('linked_section_id'));
			$entryManager = new EntryManager($this->_engine);
			$fieldManager = new FieldManager($this->_engine);
			$linked = $fieldManager->fetch($this->get('linked_field_id'));
			$custom_link = null; $more_link = null;
			
			// Not setup correctly:
			if (!$section instanceof Section or !$linked) {
				return parent::prepareTableValue(array(), $link, $entry_id);
			}
			
			if (!empty($data['linked_entry_id'])) {
				$field = current($section->fetchVisibleColumns());
				$data = $this->prepareData($data);
				
				if (!is_null($field) and $data['linked_entry_id']) {
					if ($this->get('column_mode') != 'count') {
						if ($this->get('column_mode') == 'last-item') {
							$order = 'ASC';
						}
						
						else {
							$order = 'DESC';
						}
						
						$entryManager->setFetchSorting('date', $order);
						$entries = $entryManager->fetch($data['linked_entry_id'], $this->get('linked_section_id'), 1);
						
						if (is_array($entries) and !empty($entries)) {
							$entry = current($entries);
							$value = $field->prepareTableValue(
								$entry->getData($field->get('id')),
								new XMLElement('span')
							);
							$custom_link = new XMLElement('a');
							$custom_link->setAttribute(
								'href', sprintf(
									'%s/symphony/publish/%s/edit/%s/',
									URL,
									$section->get('handle'),
									$entry->get('id')
								)
							);
							
							if ($value instanceof XMLElement) {
								$value = $value->generate();
							}
							
							$custom_link->setValue(strip_tags($value));
							
							$more_link = new XMLElement('a');
							$more_link->setValue(__('more →'));
							$more_link->setAttribute(
								'href', sprintf(
									'%s/symphony/publish/%s/?filter=%s:%s',
									URL,
									$section->get('handle'),
									$linked->get('element_name'),
									$entry_id
								)
							);
						}
					}
					
					else {
						$joins = null; $where = null;
						
						$linked->buildDSRetrivalSQL(array($entry_id), $joins, $where, false);
						
						$count = $entryManager->fetchCount($this->get('linked_section_id'), $where, $joins);
						
						if ($count > 0) {
							$custom_link = new XMLElement('a');
							$custom_link->setValue($count . __(' →'));
							$custom_link->setAttribute(
								'href', sprintf(
									'%s/symphony/publish/%s/?filter=%s:%s', URL,
									$section->get('handle'),
									$linked->get('element_name'),
									$entry_id
								)
							);	
						}
					}
				}
			}
			
			if (is_null($custom_link)) {
				$custom_link = new XMLElement('a');
				$custom_link->setValue(__('0 →'));
				$custom_link->setAttribute(
					'href', sprintf(
						'%s/symphony/publish/%s/?filter=%s:%s',
						URL,
						$section->get('handle'),
						$linked->get('element_name'),
						$entry_id
					)
				);
				
				if ($this->get('column_mode') != 'count') {
					$more_link = $custom_link;
					$more_link->setValue(__('more →'));	
					
					$custom_link = new XMLElement('span');
					$custom_link->setAttribute('class', 'inactive');
					$custom_link->setValue(__('None'));
				}
			}
			
			if ($link) {
				$link->setValue($custom_link->getValue());
				
				return $link->generate();
			}
			
			if ($this->get('column_mode') != 'count') {
				$wrapper = new XMLElement('span');
				$wrapper->setValue(
					sprintf(
						'%s, %s',
						$custom_link->generate(),
						$more_link->generate()
					)
				);
				
				return $wrapper;
			}
			
			return $custom_link;
		}
		
	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/
		
		public function displayDatasourceFilterPanel(&$wrapper, $data = null, $errors = null, $prefix = null, $postfix = null) {
			$field_id = $this->get('id');
			
			$wrapper->appendChild(new XMLElement(
				'h4', sprintf(
					'%s <i>%s</i>',
					$this->get('label'),
					$this->name()
				)
			));
			
			$prefix = ($prefix ? "[{$prefix}]" : '');
			$postfix = ($postfix ? "[{$postfix}]" : '');
			
			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input(
				"fields[filter]{$prefix}[{$field_id}]{$postfix}",
				($data ? General::sanitize($data) : null)
			));	
			$wrapper->appendChild($label);
			
			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('To do a negative filter, prefix the value with <code>not:</code>.'));
			
			$wrapper->appendChild($help);
		}
		
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');
			$method_not = false;
			
			// Find mode:
			if (preg_match('/^(not):/', $data[0], $match)) {
				$data[0] = trim(substr($data[0], strlen(next($match)) + 1));
				$name = 'method_' . current($match); $$name = true;
			}
			
			if ($andOperation) {
				$match = ($method_not ? '!=' : '=');
				
				foreach ($data as $value) {
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND t{$field_id}_{$this->_key}.linked_entry_id {$match} '{$value}'
					";
				}
			}
			
			else {
				$match = ($method_not ? 'NOT IN' : 'IN');
				
				if (!is_array($data)) $data = array($data);
				
				foreach ($data as &$value) {
					$value = $this->cleanValue($value);
				}
				
				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{$this->_key}.linked_entry_id {$match} ('{$data}')
				";
			}

			return true;
		}
	}
	
?>