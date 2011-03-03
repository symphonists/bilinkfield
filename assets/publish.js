/*-----------------------------------------------------------------------------
	Bi Link Field
-----------------------------------------------------------------------------*/
	
	jQuery(document).ready(function() {
		jQuery('.field-bilink ol.multiple')
			.symphonyDuplicator({
				orderable:		true,
				collapsible:	true,
				minimum:		0,
				maximum:		10000000
			});
		
		jQuery('.field-bilink ol.single')
			.symphonyDuplicator({
				orderable:		false,
				collapsible:	true,
				minimum:		0,
				maximum:		1
			});
	});
	
/*---------------------------------------------------------------------------*/