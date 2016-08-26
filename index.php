<?php
setlocale(LC_ALL,'en_US.UTF-8');

$tmp = realpath($_REQUEST['file']);

if($tmp === false)
	err(404,'File or Directory Not Found');
if(substr($tmp, 0,strlen(__DIR__)) !== __DIR__)
	err(403,"Forbidden");

if(!$_COOKIE['_sfm_xsrf'])
	setcookie('_sfm_xsrf',bin2hex(openssl_random_pseudo_bytes(16)));

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
					foreach($files as $entry) if($entry !== basename(__FILE__)) { 
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
		
	}//switch

} else {
	if($_COOKIE['_sfm_xsrf'] !== $_POST['xsrf'] || !$_POST['xsrf'])
		err(403,"XSRF Failure");
}

function err($code,$msg) {
	echo json_encode(array('error' => array('code'=>intval($code), 'msg' => $msg)));
	exit;
}

?>

<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

</head>
<body>


<ol class="breadcrumb"></ol>

<div class="table-responsive">
  <table class="table table-striped" id = "table">
    <thead>
      <tr>
	    <th>Name<span class="indicator glyphicon glyphicon-sort-by-attributes-alt" aria-hidden="true"></span></th>
	    <th>Size</th>
	    <th>Modified</th>
	    <th id="misc">Actions</th>
     </tr>
    </thead>
    <tbody id="list">
    </tbody>
  </table>
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/clipboard.js/1.5.12/clipboard.min.js"></script>

<script>
(function($) {
    $.fn.tablesorter = function() {
        var $table = this;
        this.find('th').click(function() {
            var idx = $(this).index();
            var direction = $(this).hasClass('sort_asc');
            $table.tablesortby(idx, direction);
        });
        //Remove click from actions tab
        $("#misc").off('click', null);
        return this;
    };
    $.fn.tablesortby = function(idx, direction) {
        var $rows = this.find('tbody tr');

        function elementToVal(a) {
            var $a_elem = $(a).find('td:nth-child(' + (idx + 1) + ')');
            var a_val = $a_elem.attr('data-sort') || $a_elem.text();
            return (a_val == parseInt(a_val) ? parseInt(a_val) : a_val);
        }
        $rows.sort(function(a, b) {
            var a_val = elementToVal(a),
                b_val = elementToVal(b);
            return (a_val > b_val ? 1 : (a_val == b_val ? 0 : -1)) * (direction ? 1 : -1);
        })
        this.find('th').removeClass('sort_asc sort_desc');
        $(this).find('thead th:nth-child(' + (idx + 1) + ')').addClass(direction ? 'sort_desc' : 'sort_asc');
        for (var i = 0; i < $rows.length; i++)
            this.append($rows[i]);
        this.settablesortmarkers();
        return this;
    }
    $.fn.retablesort = function() {
        var $e = this.find('thead th.sort_asc, thead th.sort_desc');
        if ($e.length)
            this.tablesortby($e.index(), $e.hasClass('sort_desc'));

        return this;
    }
    $.fn.settablesortmarkers = function() {
        this.find('thead th span.indicator').remove();
        this.find('thead th.sort_asc').append('<span class="indicator glyphicon glyphicon-sort-by-attributes-alt" aria-hidden="true"></span>');
        this.find('thead th.sort_desc').append('<span class="indicator glyphicon glyphicon-sort-by-attributes" aria-hidden="true"></span>');
        return this;
    }

    var clipboard = new Clipboard('.btn');

    clipboard.on('success', function(e) {
        console.info('Action:', e.action);
        console.info('Text:', e.text);
        console.info('Trigger:', e.trigger);

        e.clearSelection();
    });

    clipboard.on('error', function(e) {
        console.error('Action:', e.action);
        console.error('Trigger:', e.trigger);
    });

})(jQuery);

$(function() {
    var $tbody = $('#list');
    var counter = 0;

    $(window).bind('hashchange', list).trigger('hashchange');
    $('#table').tablesorter();

    function list() {
        var hashval = window.location.hash.substr(1);
        $.get('?', {
            'do': 'list',
            'file': hashval
        }, function(data) {
            if (data.success) {

                $tbody.empty();
                $('.breadcrumb').empty().html(renderBreadcrumbs(hashval));

                $.each(data.results, function(k, v) {
                    $tbody.append(renderFileRow(v));
                });
                !data.results.length && $tbody.append('<tr><td class="empty" colspan=5>This folder is empty</td></tr>')
                data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write');

            } else {
                console.warn(data.error.msg);
            }
        }, 'json');
        $('#table').retablesort();

    }

    function renderFileRow(data) {
        var object_html = "";

        var copy_link_url = $('<a class="name" />').attr('href', './' + data.path);
        var copy_link_btn = '<button class="btn btn-primary" data-clipboard-text="' + copy_link_url[0] + '">Copy link</button>';


        switch (data.filetype[0]) {
            case 'image':
                object_html = '<span class="glyphicon glyphicon-picture" aria-hidden="true"></span> ' + data.name;
                break;

            default:
                object_html = '<span class="glyphicon glyphicon-file" aria-hidden="true"></span> ' + data.name;
                break;
        }


        var $link = $('<a class="name" />')
            .attr('href', data.is_dir ? '#' + data.path : './' + data.path)
            .html(data.is_dir ? '<span class="glyphicon glyphicon-folder-open" aria-hidden="true"></span> ' + data.name : object_html);

        var $html = $('<tr />')
            .addClass(data.is_dir ? 'is_dir' : '')
            .append($('<td class="first" />').append($link))
            .append($('<td/>').attr('data-sort', data.is_dir ? -1 : data.size)
                .html($('<span class="size" />').text(formatFileSize(data.size))))
            .append($('<td/>').attr('data-sort', data.mtime).text(formatTimestamp(data.mtime)))
            .append($('<td/>').append(copy_link_btn))
        return $html;

    }

    function formatTimestamp(unix_timestamp) {
        var m = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var d = new Date(unix_timestamp * 1000);
        return [m[d.getMonth()], ' ', d.getDate(), ', ', d.getFullYear(), " ",
            (d.getHours() % 12 || 12), ":", (d.getMinutes() < 10 ? '0' : '') + d.getMinutes(),
            " ", d.getHours() >= 12 ? 'PM' : 'AM'
        ].join('');
    }

    function formatFileSize(bytes) {
        var s = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        for (var pos = 0; bytes >= 1000; pos++, bytes /= 1024);
        var d = Math.round(bytes * 10);
        return pos ? [parseInt(d / 10), ".", d % 10, " ", s[pos]].join('') : bytes + ' bytes';
    }

    function renderBreadcrumbs(path) {
        var base = "";
        $(".breadcrumb").append($('<li><a href="./">Home</a></li>'));

        $.each(path.split('/'), function(k, v) {
            if (v) {
                var tmpli = base + v;
                $(".breadcrumb").append('<li><a href="#' + tmpli + '">' + v + '</a></li>')
                base += v + '/';
            }
        });
        $(".breadcrumb li:last").addClass("active").find('a').contents().unwrap();
    }

});
</script>


</body>
</html>
