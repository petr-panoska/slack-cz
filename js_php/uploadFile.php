<?php
    $error = "";
    $msg = "";
    if(!empty($_FILES["highlineNahledAdd"]['error']))
    {
        switch($_FILES["highlineNahledAdd"]['error'])
        {

            case '1':
                $error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
                break;
            case '2':
                $error = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
                break;
            case '3':
                $error = 'The uploaded file was only partially uploaded';
                break;
            case '4':
                $error = 'No file was uploaded.';
                break;

            case '6':
                $error = 'Missing a temporary folder';
                break;
            case '7':
                $error = 'Failed to write file to disk';
                break;
            case '8':
                $error = 'File upload stopped by extension';
                break;
            case '999':
            default:
                $error = 'No error code avaiable';
        }
    }elseif(empty($_FILES["highlineNahledAdd"]['tmp_name']) || $_FILES["highlineNahledAdd"]['tmp_name'] == 'none')
    {
        $error = 'No file was uploaded..';
    }else 
    {
            $msg .= " File Name: " . $_FILES["highlineNahledAdd"]['name'] . ", ";
            //$msg .= " File Size: " . @filesize($_FILES["highlineNahledAdd"]['tmp_name']);
            
            
            move_uploaded_file($_FILES["highlineNahledAdd"]["tmp_name"], "../uzivatel/1/highlineAdd/foto.jpg");
            include('../include/SimpleImage.php');
            $image = new SimpleImage();
            $image->load("uzivatel/1/highlineAdd/foto.jpg");
            $image->resizeToWidth(120);
            $image->save("uzivatel/1/highlineAdd/foto.jpg");

            @unlink($_FILES["highlineNahledAdd"]);        
    }        
    echo "{";
    echo                "error: '".$error."',\n";
    echo                "msg: '".$msg."'\n";
    echo "}";
?>