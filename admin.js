jQuery(function($) {

	$("#cpmb-attachimgs").on('mouseover',function() {
		$(this).find('li.count').addClass('move');
	}).on('mouseout',function() {
		$(this).find('li.count').removeClass('move');
	});

	$("#cpmb-attachimgs li:not(.count,.viewall)").on('mouseover',function() {
		$(this).stop(true).animate({opacity: 0.7},200);
	}).on('mouseout',function() {
		$(this).stop(true).animate({opacity: 1},200);
	});

	$("#cpmb-attachimgs li.viewall").on('mouseover',function() {
		$(this).animate({color: 'red'},200);
	}).on('mouseout',function() {
		$(this).animate({color: '#666'},200);
	}).on('click',function() {
		$("#insert-media-button").click();
	});

});
