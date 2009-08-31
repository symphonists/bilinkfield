<?php
	
	class Extension_BiLinkField extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		// Doing a submodule test...
		
		public function about() {
			return array(
				'name'			=> 'Field: Bi Link',
				'version'		=> '1.0.13',
				'release-date'	=> '2009-07-07',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://pixelcarnage.com/',
					'email'			=> 'rowan@pixelcarnage.com'
				),
				'description'	=> 'A bi-directional linking system for Symphony.'
			);
		}
		
		public function install() {
			$this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_bilink` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`linked_section_id` INT(11) UNSIGNED DEFAULT NULL,
					`linked_field_id` INT(11) UNSIGNED DEFAULT NULL,
					`allow_multiple` ENUM('yes','no') DEFAULT NULL,
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
		
	/*-------------------------------------------------------------------------
		Utilites:
	-------------------------------------------------------------------------*/
		
		protected $addedHeaders = false;
		
		public function addHeaders($page) {
			if (!$this->addedHeaders) {
				$page->addScriptToHead(URL . '/extensions/bilinkfield/assets/symphony.duplicator.js', 123269780);
				$page->addScriptToHead(URL . '/extensions/bilinkfield/assets/publish.js', 123269781);
				$page->addStylesheetToHead(URL . '/extensions/bilinkfield/assets/publish.css', 'screen', 123269781);
				
				$this->addedHeaders = true;
			}
		}
	}
		
?>