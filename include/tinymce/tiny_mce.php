	<script language="javascript" type="text/javascript" src="include/tinymce/tiny_mce/tiny_mce.js"></script>
	<script language="javascript" type="text/javascript" src="include/tinymce/tinyupload/tinyupload.js"></script>
	<script type="text/javascript">
		tinyMCE.init({
            // General options 
			mode : "textareas",
			theme : "advanced",
			plugins : "safari,style,table,advhr,advimage,advlink,iespell,insertdatetime,preview,searchreplace,contextmenu,paste,fullscreen,visualchars,nonbreaking,xhtmlxtras,template,directionality",
		    
            // Theme options 
            theme_advanced_buttons1 : "undo,redo,|,forecolor,|,image,|,link,unlink,|,bold,italic,underline,strikethrough,sub,sup,|,justifyleft,justifycenter,justifyright,justifyfull,|,bullist,numlist,|,fontsizeselect,|,code,|,fullscreen",
			theme_advanced_toolbar_location : "top",
			theme_advanced_toolbar_align : "left",
			theme_advanced_statusbar_location : "bottom",
			theme_advanced_resizing : true,
			theme_advanced_resize_horizontal : false,            
			force_br_newlines : true,
			force_p_newlines : false,
			forced_root_block : "",
			
			relative_urls : false,//Tiny upload returns absolute urls, we dont want tinymce changing them to relative.
			file_browser_callback:tinyupload//Hookup tinyupload the the filebrowser call back.
		});
	</script>