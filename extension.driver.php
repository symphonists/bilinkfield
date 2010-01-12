<?php
	
	class Extension_BiLinkField extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'Field: Bi Link',
				'version'		=> '1.0.14',
				'release-date'	=> '2009-09-03',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://pixelcarnage.com/',
					'email'			=> 'rowan@pixelcarnage.com'
				),
				'description'	=> 'A bi-directional linking system for Symphony.'
			);
		}
		
		public function install() {
			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_bilink` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`linked_section_id` INT(11) UNSIGNED DEFAULT NULL,
					`linked_field_id` INT(11) UNSIGNED DEFAULT NULL,
					`allow_editing` ENUM('yes','no') DEFAULT 'no',
					`allow_multiple` ENUM('yes','no') DEFAULT 'no',
					`column_mode` ENUM('count','first-item','last-item','small-list','large-list') DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`),
					KEY `linked_section_id` (`linked_section_id`),
					KEY `linked_field_id` (`linked_field_id`)
				)
			");
			
			return true;
		}
		
		public function uninstall() {
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_bilink`");
			
			return true;
		}
		
		public function update($previousVersion) {
			if (version_compare($previousVersion, '1.0.14', '<')) {
				Symphony::Database()->query("
					ALTER TABLE `tbl_fields_bilink`
					ADD COLUMN `allow_editing` ENUM('yes','no') DEFAULT 'no';
				");
			}
			
			return true;
		}
		
	/*-------------------------------------------------------------------------
		Utilites:
	-------------------------------------------------------------------------*/
		
		protected $addedHeaders = false;
		
		public function addHeaders($page) {
			if (!$this->addedHeaders) {
				$page->addScriptToHead(URL . '/extensions/bilinkfield/assets/publish.js', 123269781);
				
				$this->addedHeaders = true;
			}
		}
	}
		
?>