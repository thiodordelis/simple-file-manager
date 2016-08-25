<?php
setlocale(LC_ALL,'en_US.UTF-8');

$file = $_REQUEST['file'] ?: '.';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    switch ($_GET['do']) {
		case 'list':
			// Scan directory for files
			if (is_dir($file)) {
					$directory = $file;
					$dirResults = array();
					$fileResults = array();
					$files = array_diff(scandir($directory), array('.','..'));
					foreach($files as $entry) if($entry !== basename(__FILE__)) { //__FILE__ is diabling this filemanager to scan for its self
						$i = $directory . '/' . $entry;
						$stat = stat($i);
						if(is_dir($i)) {
						  $dirResults[] = array(
							'mtime' => $stat['mtime'],
							'size' => $stat['size'],
							'name' => basename($i),
							'is_dir' => is_dir($i),
							'path' => preg_replace('@^\./@', '', $i),
							'filetype'  => explode("/",mime_content_type($i) )									    
						  );
						} else {
						  $fileResults[] = array(
							'mtime' => $stat['mtime'],
							'size' => $stat['size'],
							'name' => basename($i),
							'path' => preg_replace('@^\./@', '', $i),
							'filetype'  => explode("/",mime_content_type($i) ),
						  );
						} //if else
					}
				} else {
					err(412,"Not a Directory");
				}
				ksort($fileResults);
				$result = array_merge($dirResults, $fileResults);			
				echo json_encode(array('success' => true, 'is_writable' => is_writable($file), 'results' => $result),JSON_PRETTY_PRINT);
				exit;				
			break;
		
		default:
			# code...
			break;
	}//switch

} else {

}

?>

<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

</head>
<body>

<table class="table table-striped" id = "table">
  <thead>
    <tr>
	  <th>Name</th>
	  <th>Size</th>
	  <th>Modified</th>
	  <th id="misc">Actions</th>
   </tr>
  </thead>

  <tbody id="list">

  </tbody>
</table>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" crossorigin="anonymous">

<script>
(function($){
	$.fn.tablesorter = function() {
		var $table = this;
		this.find('th').click(function() {
			var idx = $(this).index();
			var direction = $(this).hasClass('sort_asc');
			$table.tablesortby(idx,direction);
		});
		$("#misc").off('click', null);
		return this;
	};
	$.fn.tablesortby = function(idx,direction) {
		var $rows = this.find('tbody tr');
		function elementToVal(a) {
			var $a_elem = $(a).find('td:nth-child('+(idx+1)+')');
			var a_val = $a_elem.attr('data-sort') || $a_elem.text();
			return (a_val == parseInt(a_val) ? parseInt(a_val) : a_val);
		}
		$rows.sort(function(a,b){
			var a_val = elementToVal(a), b_val = elementToVal(b);
			return (a_val > b_val ? 1 : (a_val == b_val ? 0 : -1)) * (direction ? 1 : -1);
		})
		this.find('th').removeClass('sort_asc sort_desc');
		$(this).find('thead th:nth-child('+(idx+1)+')').addClass(direction ? 'sort_desc' : 'sort_asc');
		for(var i =0;i<$rows.length;i++)
			this.append($rows[i]);
		this.settablesortmarkers();
		return this;
	}
	$.fn.retablesort = function() {
		var $e = this.find('thead th.sort_asc, thead th.sort_desc');
		if($e.length)
			this.tablesortby($e.index(), $e.hasClass('sort_desc') );
		
		return this;
	}
	$.fn.settablesortmarkers = function() {
		this.find('thead th span.indicator').remove();
		this.find('thead th.sort_asc').append('<span class="indicator">&darr;<span>');
		this.find('thead th.sort_desc').append('<span class="indicator">&uarr;<span>');
		return this;
	}
})(jQuery);

$(function() {
  var $tbody = $('#list');
	$(window).bind('hashchange',list).trigger('hashchange');
	$('#table').tablesorter();

  function list() {
    var hashval = window.location.hash.substr(1);
		$.get('?',{'do':'list','file':hashval},function(data) {
			if(data.success) {
				$tbody.empty();
				$.each(data.results,function(k,v){
					$tbody.append(renderFileRow(v));
				});				
				!data.results.length && $tbody.append('<tr><td class="empty" colspan=5>This folder is empty</td></tr>')
				data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write');
				
			} else {
				console.warn(data.error.msg);
			}
		},'json');
		$('#table').retablesort();

  }

  function renderFileRow(data) {
    var object_html = "";
		
		switch (data.filetype[0]) {
			case 'image':
				object_html = '<span class="glyphicon glyphicon-picture" aria-hidden="true"></span> '+data.name;
				break;
				
			default:
				object_html = '<span class="glyphicon glyphicon-file" aria-hidden="true"></span> '+data.name;
				break;
		}
		

		var $link = $('<a class="name" />')
			.attr('href', data.is_dir ? '#' + data.path : './'+data.path)
		    .html(data.is_dir ? '<span class="glyphicon glyphicon-folder-open" aria-hidden="true"></span> '+data.name : object_html);
		
		var $html = $('<tr />')
			.addClass(data.is_dir ? 'is_dir' : '')
			.append( $('<td class="first" />').append($link) )
			.append( $('<td/>').attr('data-sort',data.is_dir ? -1 : data.size)
				.html($('<span class="size" />').text(data.size)) ) 
			.append( $('<td/>').attr('data-sort',data.mtime).text(data.mtime) )
			.append( $('<td/>').append(data.name).append( data.is_deleteable ? $delete_link : '') )
		return $html;
	}

});
</script>


</body>
</html>
