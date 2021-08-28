jQuery(document).ready(function(){
	jQuery("#wp-admin-bar-airpress_debugger_toggle").click(function(e){
		e.preventDefault();
		jQuery("#airpress_debugger").toggle();
		jQuery('html, body').animate({ scrollTop: 0 }, 'fast');
	});

	jQuery(".expander").click(function(e){
		e.preventDefault();
		jQuery(this).next().fadeToggle();
	});
});