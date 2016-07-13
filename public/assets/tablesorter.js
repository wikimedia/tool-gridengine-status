$(document).ready(function() {
	$(".tablesorter").tablesorter({
		sortList: [[0,0]],
		widgets: ["zebra"],
		widgetOptions : {
			zebra : [ "normal-row", "alt-row" ]
		}
	});
});
