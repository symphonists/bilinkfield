<?php
	
	class Extension_BiLinkField extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'Field: Bi Link',
				'version'		=> '1.001',
				'release-date'	=> '2009-03-05',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://pixelcarnage.com/',
					'email'			=> 'rowan@pixelcarnage.com'
				),
				'description'	=> 'Real Parent to Child relationships for Symphony.'
			);
		}
		
		public function install() {
			$this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_bilink` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`linked_section_id` int(11) unsigned default NULL,
					`linked_field_id` int(11) unsigned default NULL,
					`allow_multiple` enum('yes','no') default NULL,
					`column_mode` enum('count','first-item','last-item','small-list','large-list') default NULL,
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
	}
		
?>