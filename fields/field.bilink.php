<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	class FieldBiLink extends Field {
		protected $_driver = null;
		public $_ignore = array();

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

			return $this->_engine->Database->query("
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
			$field_id = $this->get('id');
			$entries = $this->_engine->Database->fetchCol('linked_entry_id',
				sprintf("
					SELECT
						f.linked_entry_id
					FROM
						`tbl_entries_data_{$field_id}` AS f
					WHERE
						f.entry_id = '{$entry_id}'
				")
			);

			foreach ($entries as $linked_entry_id) {
				if (is_null($linked_entry_id)) continue;

				$entry = @current($entryManager->fetch($linked_entry_id, $this->get('linked_section_id')));

				if (!is_object($entry)) continue;

				$values = $entry->getData($this->get('linked_field_id'));

				if (array_key_exists(linked_entry_id, $values)) {
					$values = $values['linked_entry_id'];
				}

				if (is_null($values)) {
					$values = array();

				} else if (!is_array($values)) {
					$values = array($values);
				}

				$values = array_diff($values, array($entry_id));

				$entry->setData($this->get('linked_field_id'), array(
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
			if( $this->get('linked_field_id') ){
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
			}else{
				$linked_field_id = NULL;
				$linked_section_id = NULL;
			}

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

		public function findEntries($entry_ids, $current_entry_id = null) {
			$sectionManager = new SectionManager($this->_engine);
			$section = $sectionManager->fetch($this->get('linked_section_id'));
			$entryManager = new EntryManager($this->_engine);
			$entries = $entryManager->fetch(null, $this->get('linked_section_id'));
			$options = array();

			if ($this->get('required') != 'yes') $options[] = array(null, false, null);

			if (!is_object($section) or empty($entries)) return $options;

			foreach ($entries as $order => $entry) {
				if (!is_object($entry)) continue;

				$field = current($section->fetchVisibleColumns());

				if (!is_object($field) or $current_entry_id == $entry->get('id')) continue;

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

		public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$handle = $this->get('element_name'); $entry_ids = array();

			if (!is_array($data['linked_entry_id']) and !is_null($data['linked_entry_id'])) {
				$entry_ids = array($data['linked_entry_id']);

			} else {
				$entry_ids = $data['linked_entry_id'];
			}

			$options = $this->findEntries($entry_ids, $entry_id);

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

					if (array_key_exists(linked_entry_id, $values)) {
						$values = $values['linked_entry_id'];
					}

					if (is_null($values)) {
						$values = array();

					} else if (!is_array($values)) {
						$values = array($values);
					}

					$values = array_diff($values, array($entry_id));

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

					if (array_key_exists(linked_entry_id, $values)) {
						$values = $values['linked_entry_id'];
					}

					if (is_null($values)) {
						$values = array();

					} else if (!is_array($values)) {
						$values = array($values);
					}

					if (!in_array($entry_id, $values)) $values[] = $entry_id;

					$entry->setData($this->get('linked_field_id'), array(
						'linked_entry_id'	=> $values
					));
					$entry->commit();
				}
			}

			return $result;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function getParameterPoolValue($data) {
			if (!is_array($data['linked_entry_id'])) {
				$data['linked_entry_id'] = array($data['linked_entry_id']);
			}

			return implode(', ', $data['linked_entry_id']);
		}

		public function fetchIncludableElements() {
			return array(
				$this->get('element_name') . ': count',
				$this->get('element_name') . ': items',
				$this->get('element_name') . ': entries'
			);
		}

		public function prepareData($data) {
			if (!is_array($data['linked_entry_id'])) {
				$data['linked_entry_id'] = array($data['linked_entry_id']);
			}

			if (is_null($data['linked_entry_id'])) {
				$data['linked_entry_id'] = array();

			} else if (!is_array($data['linked_entry_id'])) {
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

			$list = new XMLElement($this->get('element_name'));
			$list->setAttribute('mode', $mode);
			$list->setAttribute('entries', count($data['linked_entry_id']));

			// No section or relations:
			if (empty($section) or @empty($data['linked_entry_id'][0])) {
				$list->setAttribute('entries', 0);
				$wrapper->appendChild($list);

				return;
			}

			// List:
			if ($mode == null or $mode == 'items') {
				$entries = $entryManager->fetch($data['linked_entry_id'], $linked_section_id);
				$list->appendChild(new XMLElement(
					'section', $section->get('name'),
					array(
						'id'		=> $section->get('id'),
						'handle'	=> $section->get('handle')
					)
				));
				$field = @current($section->fetchVisibleColumns());

				foreach ($entries as $count => $entry) {
					if (empty($entry)) continue;

					$value = $field->prepareTableValue(
						$entry->getData($field->get('id'))
					);
					$handle = Lang::createHandle($value);

					$item = new XMLElement('item', General::sanitize($value));
					$item->setAttribute('id', $entry->get('id'));
					$item->setAttribute('handle', $handle);

					$list->appendChild($item);
				}

			// Full:
			} else if ($mode == 'entries') {
				$entries = $entryManager->fetch($data['linked_entry_id'], $linked_section_id);
				$list->appendChild(new XMLElement(
					'section', $section->get('name'),
					array(
						'id'		=> $section->get('id'),
						'handle'	=> $section->get('handle')
					)
				));

				foreach ($entries as $count => $entry) {
					$associated = $entry->fetchAllAssociatedEntryCounts();
					$data = $entry->getData();

					$item = new XMLElement('entry');
					$item->setAttribute('id', $entry->get('id'));

					if (is_array($associated) and !empty($associated)) {
						foreach ($associated as $section => $count) {
							$handle = $this->_engine->Database->fetchVar('handle', 0, "
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

						if ($field->get('type') == $this->get('type')) {
							$field->_ignore = $this->_ignore;
							$field->_ignore[] = $this->get('parent_section');

							if (in_array($field->get('parent_section'), $field->_ignore)) continue;
						}

						$field->appendFormattedElement($item, $values, false, $mode);
					}

					$list->appendChild($item);
				}
			}

			$wrapper->appendChild($list);
		}

		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			header('content-type: text/plain');

			$sectionManager = new SectionManager($this->_engine);
			$section = $sectionManager->fetch($this->get('linked_section_id'));
			$entryManager = new EntryManager($this->_engine);
			$fieldManager = new FieldManager($this->_engine);
			$linked = $fieldManager->fetch($this->get('linked_field_id'));
			$custom_link = null; $more_link = null;

			// Not setup correctly:
			if (!$section instanceof Section) {
				return parent::prepareTableValue(array(), $link, $entry_id);
			}

			if ($section instanceof Section) {
				$field = current($section->fetchVisibleColumns());
				$data = $this->prepareData($data);

				if (!is_null($field)) {
					if ($this->get('column_mode') != 'count') {
						if ($this->get('column_mode') == 'last-item') {
							$order = 'ASC';

						} else {
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

					} else {
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

			} else {
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
