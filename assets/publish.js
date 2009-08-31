/*-----------------------------------------------------------------------------
	Bi Link Field
-----------------------------------------------------------------------------*/
	
	jQuery(document).ready(function() {
		$('.field-bilink ol.multiple').duplicator({
			orderable:		true,
			collapsible:	false,
			minimum:		0,
			maximum:		10000000
		});
		$('.field-bilink ol.single').duplicator({
			orderable:		false,
			collapsible:	false,
			minimum:		0,
			maximum:		1
		});
	});
	
/*---------------------------------------------------------------------------*/