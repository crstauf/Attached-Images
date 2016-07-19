jQuery(function($) {

	$.QueryString = (function(a) {
		if (a == "") return {};
		var b = {};
		for (var i = 0; i < a.length; ++i)
		{
			var p=a[i].split('=');
			if (p.length != 2) continue;
			b[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
		}
		return b;
	})(window.location.search.substr(1).split('&'));

	$("#cpmb-attachimgs").ready(function() {
		if ($(this).find('li.no-imgs').length)
			$("#cpmb-attachimgs > .hndle").hide();
	}).on('mouseover',function() {
		$(this).not('.refreshing').find('li.count').addClass('move');
	}).on('mouseout',function() {
		$(this).find('li.count').removeClass('move');
	});

	$("#cpmb-attachimgs li.no-imgs > .hndle").click(function(ev) { ev.preventDefault(); });

});
