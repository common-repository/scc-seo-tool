<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function sccstslug_is_assoc($arr) {
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function sccstslug_get_currenturl($query_string = true) {
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on" ? 'https://' : 'http://';

    $url = $_SERVER['REQUEST_URI'];
    if (!$query_string)
        $url = str_replace('?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);

    $url = $scheme . $_SERVER['HTTP_HOST'] . $url;

    return $url;
} 

// plugins_loaded xử lý khi có post content
function sccstslug_process_auto_post_content(){
    if(is_admin()) return;
    $page = sccstslug_get_request('page');
   
    if($page == "autopostcontent"){
        $id = sccstslug_get_request('id',1);
        $items = get_option(SCCST_OPT_NAME);
       
        $item_pos = $id - 1;
        if(isset($items[$item_pos])){
            $itemPage = $items[$item_pos];
            $itemPage = wp_parse_args($itemPage, sccstslug_get_default_item_setting());
            $itemPage = (object)$itemPage;
           
            $secret_key = sccstslug_get_request('secret_key','');
            
            echo '<meta http-equiv="content-type" content="text/html; charset=utf-8" />';
            if($secret_key == ""){
              //  require_once (dirname(__FILE__)."/view-post.php"); die;
              return;
            }
            sccstslug_process_auto_post($itemPage);
        }
        exit;
    }
}

// lấy trên request
function sccstslug_get_request($name, $default = ""){
    $value = isset($_REQUEST[$name])?$_REQUEST[$name]:$default;
    return $value;
}


function sccstslug_process_auto_post($itemPage){
    $title = sccstslug_get_request('title','');
    $introtext = sccstslug_get_request('introtext','');
    $fulltext = isset($_POST['fulltext'])?$_POST['fulltext']:'';
    $secret_key = sccstslug_get_request('secret_key','');
    $cdate = sccstslug_get_request('cdate',"");
    if($cdate == "") $cdate = date("Y-m-d H:i:s");
    $meta_key = sccstslug_get_request('meta_key', "");
    $thumbnail = sccstslug_get_request('thumbnail', "");
    $meta_key = explode(",", $meta_key);
    
    if($title == "" OR $fulltext == ""){
        echo "Phải có tiêu đề và nội dung";
        return;
    }
   
    if($itemPage->secret_key != $secret_key){ echo 'Không thành công.'; return false; }
    $slug = strtolower(sccstslug_convertalias($title));
    global $wpdb;
    $query = "SELECT * FROM $wpdb->posts WHERE post_title = %s OR post_name = %s"; 
    $old_post = $wpdb->get_row( $wpdb->prepare($query , $title, $slug )); // get_var, get_row
    $post_id  =  0;
    if($old_post != null){
        $post_id = $old_post->ID;
        echo "Bài đã có >> $post_id >> $title";
        return;
    }
    
    $fulltext = preg_replace('/<script[^>]*>.*?<\/script>/ism', '', $fulltext);
    $fulltext = preg_replace('/<style[^>]*>.*?<\/style>/ism', '', $fulltext);
     
    $uploads  = wp_upload_dir();
    $path_save = $uploads['path'];
    $link_file = $uploads['url'];
    
    $arr_image = array();
    if($itemPage->take_thumb == 1 OR $itemPage->take_image == 1){
        
        $horizon_wish_width ='620';
        $vertical_wish_width ='620';
        if($itemPage->take_thumb == 1 AND $thumbnail != ""){
            //$save_thumb = "/wp-content/uploads".$uploads['subdir'];
            list($thumbnail, $thumbnail_name) = sccstslug_takeOneImage($thumbnail,$slug . "-thumb",$path_save, $link_file, $thumb_type);
        }
       
        if($itemPage->take_image == 1){
            list($first_image, $thumbnail320, $thumbnail750, $arr_image) = sccstslug_takeAllImage($fulltext, $path_save, $link_file, "sieucongcu", $slug);

            if($thumbnail == ""){
                if($thumbnail320 != "")
                    $thumbnail = $thumbnail320;
                else $thumbnail = $first_image;
            }
        }
    }
     
    $fulltext = trim($fulltext);
    $fulltext = html_entity_decode($fulltext, ENT_QUOTES, 'UTF-8');
    $post_content = "$fulltext";
    if($introtext != "") $post_content = "$introtext<!--more-->$fulltext";
    $itemPage->time_delay = intval($itemPage->time_delay);
    if($itemPage->time_delay<0) $itemPage->time_delay = 0;
    $publish_up = date("Y-m-d H:i:s", current_time('timestamp') + $itemPage->time_delay* 60);  
    // mysql, timestamp,  PHP date format
    if($itemPage->status == 1)
        $status = "publish"; // pending, publish
    else $status = "pending"; // pending, publish
    $arr_cat = $itemPage->cat;
   
    $post_id = wp_insert_post(array(
        "ID"=>$post_id, 
        "post_title"=>$title, 
        "post_date"=>$publish_up, 
        "post_content" => $post_content,
        "post_status" => $status,
        "post_category" => $arr_cat,
       // "tax_input" => $meta_key,
    ));
    
    wp_set_post_tags($post_id, $meta_key);
    
    $abs_path = $uploads['subdir'];
    
    if(count($arr_image)){
        foreach($arr_image as $i_title => $image){ 
            $filename = preg_replace( '/\.[^.]+$/', '', $i_title );
            $attach_id = sccstslug_save_attachment($post_id, $image[0], $image[1], $filename, $abs_path."/".$i_title);
        }
    }
    
    if($thumbnail != ""){
        $thumbnail_name = basename($thumbnail);
        $filename = preg_replace( '/\.[^.]+$/', '', $thumbnail_name );
        $path_thumb = $path_save."/".$thumbnail_name;
        $info = getimagesize($path_thumb);
        $thumb_type = $info['mime'];
        $attach_id = sccstslug_save_attachment($post_id, $thumbnail, $thumb_type, $filename, $abs_path."/".$thumbnail_name);
        set_post_thumbnail( $post_id, $attach_id );
    }
    echo "Đã lưu bài: $post_id >> $title";
}

function sccstslug_save_attachment($post_id, $link_file, $mime_type, $slug, $abs_file = "/wp-content/to/file-name.jpg"){
    if(!function_exists('wp_generate_attachment_metadata'))
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
    
    $attachment = array(
            'guid'           => $link_file, 
            'post_mime_type' => $mime_type,
            'post_title'     => $slug,
            'post_content'   => '',
            'post_status'    => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $abs_file, $post_id );
    $attach_data = wp_generate_attachment_metadata( $attach_id, $link_file );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    return $attach_id;
}

function sccstslug_convertalias($string) {
        $alias = $string;

        $coDau = array("à", "á", "ạ", "ả", "ã", "â", "ầ", "ấ", "ậ", "ẩ", "ẫ", "ă",
            "ằ", "ắ", "ặ", "ẳ", "ẵ",
            "è", "é", "ẹ", "ẻ", "ẽ", "ê", "ề", "ế", "ệ", "ể", "ễ",
            "ì", "í", "ị", "ỉ", "ĩ",
            "ò", "ó", "ọ", "ỏ", "õ", "ô", "ồ", "ố", "ộ", "ổ", "ỗ", "ơ"
            , "ờ", "ớ", "ợ", "ở", "ỡ",
            "ù", "ú", "ụ", "ủ", "ũ", "ư", "ừ", "ứ", "ự", "ử", "ữ",
            "ỳ", "ý", "ỵ", "ỷ", "ỹ",
            "đ",
            "À", "Á", "Ạ", "Ả", "Ã", "Â", "Ầ", "Ấ", "Ậ", "Ẩ", "Ẫ", "Ă"
            , "Ằ", "Ắ", "Ặ", "Ẳ", "Ẵ",
            "È", "É", "Ẹ", "Ẻ", "Ẽ", "Ê", "Ề", "Ế", "Ệ", "Ể", "Ễ",
            "Ì", "Í", "Ị", "Ỉ", "Ĩ",
            "Ò", "Ó", "Ọ", "Ỏ", "Õ", "Ô", "Ồ", "Ố", "Ộ", "Ổ", "Ỗ", "Ơ"
            , "Ờ", "Ớ", "Ợ", "Ở", "Ỡ",
            "Ù", "Ú", "Ụ", "Ủ", "Ũ", "Ư", "Ừ", "Ứ", "Ự", "Ử", "Ữ",
            "Ỳ", "Ý", "Ỵ", "Ỷ", "Ỹ",
            "Đ", "ê", "ù", "à");

        $khongDau = array("a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a"
            , "a", "a", "a", "a", "a", "a",
            "e", "e", "e", "e", "e", "e", "e", "e", "e", "e", "e",
            "i", "i", "i", "i", "i",
            "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o"
            , "o", "o", "o", "o", "o",
            "u", "u", "u", "u", "u", "u", "u", "u", "u", "u", "u",
            "y", "y", "y", "y", "y",
            "d",
            "A", "A", "A", "A", "A", "A", "A", "A", "A", "A", "A", "A"
            , "A", "A", "A", "A", "A",
            "E", "E", "E", "E", "E", "E", "E", "E", "E", "E", "E",
            "I", "I", "I", "I", "I",
            "O", "O", "O", "O", "O", "O", "O", "O", "O", "O", "O", "O"
            , "O", "O", "O", "O", "O",
            "U", "U", "U", "U", "U", "U", "U", "U", "U", "U", "U",
            "Y", "Y", "Y", "Y", "Y",
            "D", "e", "u", "a");

        $alias = str_replace($coDau, $khongDau, $alias);

        $coDau = array("̀", "́", "̉", "̃", "̣", "“", "”", ".");
        $khongDau = array("", "", "", "", "", "", "", "");
        $alias = str_replace($coDau, $khongDau, $alias);

        $alias = preg_replace('/[^a-zA-Z0-9-.]/', '-', $alias);
        $alias = preg_replace('/^[-]+/', '', $alias);
        $alias = preg_replace('/[-]+$/', '', $alias);
        $alias = preg_replace('/[-]{2,}/', '-', $alias);
        return $alias;
    }
    
// lấy tất cả ảnh
function sccstslug_takeAllImage(& $str_in, $path_save, $path_url, $prefix ="",$_image_filename = "", $horizon_wish_width = 620, $vertical_wish_width = 620 ){    
    $webroot = get_site_url();
    if(trim($str_in) == "") return false;
    
    if (!is_dir($path_save))
    {
        if (!mkdir($path_save, 0755, true)) {
            return $source;
            //die('Failed to create folders...');
        }
    }
    $path_save = rtrim($path_save,"/")."/";
    $path_url = rtrim($path_url,"/")."/";
     
    $dom = new domDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = TRUE; 
    
    $str_in = str_replace('<figure', '<div class="row-caption"', $str_in);
    $str_in = str_replace('</figure', '</div', $str_in);
    $str_in = str_replace('<figcaption', '<div class="caption-content"', $str_in);
    $str_in = str_replace('</figcaption', '</div', $str_in);
    
    $str_in = preg_replace('/(<img[^>]*)\sheight=/ism', '$1class="caption-img" rel-h=', $str_in);
    $str_in = preg_replace('/(<img[^>]*)\swidth=/ism', '$1class="caption-img" rel-w=', $str_in);
    $str_in = preg_replace('/(<img[^>]*)class="caption-img"([^>]*)class="caption-img"([^>]*)>/ism', '$1class="caption-img"$2$3>', $str_in);
    $str_in = preg_replace('/(<img[^>]*)class="caption-img"([^>]*)class="caption-img"([^>]*)>/ism', '$1class="caption-img"$2$3>', $str_in);
    
    $str_load = $str_in;
    $str_load = str_replace('<aside', '<div', $str_load);
    $str_load = str_replace('</aside', '</div', $str_load);
    
    $internalErrors = libxml_use_internal_errors(true);
    $dom->loadHTML($str_load);
    libxml_use_internal_errors($internalErrors);
    $text_items = $dom->getElementsByTagName('img') ;
    $first_image = "";
    $thumbnail = "";
    $thumbnail_slide = "";
    $arr_image = array();
    if ($text_items) {
        $itemnn=0;
         
        if($prefix === null OR $prefix === false OR $prefix === "" OR $prefix === 0)
            $prefix = date("Ymd-His");
         
        foreach($text_items as $text_item)  
        {
            $itemnn++;         
            $external_image_url0=$text_item->getAttribute('src');
            $external_image_url = str_replace('https://','http://',$external_image_url0); 
            $external_image_url = preg_replace("/\?.*$/is","",$external_image_url); // bo cac ky tu sau ?
           if ($_image_filename) {
                $image_filename=$prefix.'-'.$itemnn.'-'.$_image_filename;
            } else { 
                $image_filename=$prefix.'-'. $itemnn;                    
            } 
             
            if(strpos($external_image_url, "//") === 0) $external_image_url = "http:$external_image_url";

            if ($external_image_url<>'' && strpos($external_image_url,$webroot)===FALSE 
                    && strpos($external_image_url,'http://')!==FALSE) 
            {
                list($image_filename,$image_filename_new,$load_status, $type)=sccstslug_load_and_resize_image($external_image_url,$path_save,$image_filename,$horizon_wish_width,$vertical_wish_width,$flag_resize=false,$flag_url_source=true); 
             
                if ($load_status==1) {
                     $new_image_url = $path_url.$image_filename_new;
                     $str_in = str_replace($external_image_url0,$new_image_url,$str_in);
                     $str_in = str_replace(htmlspecialchars($external_image_url),$new_image_url,$str_in);
                     
                     $file_name = $path_save . $image_filename_new;
                     
                     $file_info = getimagesize($file_name);
                     $arr_image[$image_filename] = array($new_image_url,$file_info['mime']);
                     
                    if($first_image == "")
                        $first_image = $new_image_url;
                    if($thumbnail == "" AND $file_info[0] > 320)
                        $thumbnail = $new_image_url;
                    if($image_filename != $image_filename_new){
                        $old_file = $path_save.$image_filename;
                        $file_info = getimagesize($old_file);
                        $image_filename_new = $image_filename;
                    }
                    if($thumbnail_slide == "" AND $file_info[0] > 750)
                        $thumbnail_slide = $path_url.$image_filename_new;
                }
            }
        }
    } 
    
    return array($first_image, $thumbnail, $thumbnail_slide, $arr_image);
}


// lấy 1 ảnh
function sccstslug_takeOneImage($img_src, $_image_filename, $path_save, $link_file, & $type = "image/jpeg", $webroot = null){
    if($webroot == null) $webroot = get_site_url();

    if(strpos($img_src, WEB_URL)  !== false) return $img_src;     
    if(strpos($img_src, "://") === 0) $img_src = "http:$img_src";
    if(strpos($img_src, "//")=== 0) $img_src = "http$img_src";
    if(strpos($img_src, "http://") === false AND strpos($img_src, "https://") === false ) return $img_src;
    $img_src = preg_replace("/\?.*$/is","",$img_src); // bo cac ky tu sau ?
   
    list($image_filename,$image_filename_new,$load_status, $type) = sccstslug_load_and_resize_image($img_src,$path_save,$_image_filename);
    if($load_status == 1){
        $img_src = rtrim($link_file,"/")."/$image_filename";
    }    
    return array($img_src,$image_filename);
}

//
function sccstslug_load_and_resize_image($source,$path_save,$filename_save='',$horizon_wish_width = null,$vertical_wish_width = null,$flag_resize=false,$flag_url_source=true)
{
    $source = str_replace('https://','http://',$source);
    
    if (!is_dir($path_save))
    {
        if (!mkdir($path_save, 0755, true)) {
            return $source;
            //die('Failed to create folders...');
        }
    }
    $path_save = rtrim($path_save,"/")."/";
    $file_mime = null;
    $load_status=0;                            
    $image_filename = '';
    $image_filename_new ='';
    $width = '';
    $height = ''; 
    $image_type = '';
    $error = '';
    if (!$flag_url_source)
    {
        preg_match_all('/src="(.*?)"/', $source, $out, PREG_SET_ORDER);
        if (!isset($out[0][1]))
        {
            echo "Image source must be image tag!";
            return;    
        }
        else
            $source = $out[0][1];
    }

    if ($source <> '' && strpos($source, 'http://') !== FALSE) 
    {        
        $source = trim($source);
        // lay domain de lam referer                       
        $referer_path = preg_replace("/^(http:\/\/)*(www.)*/is", "", $source);
        $referer_path = 'http://'.preg_replace("/\/.*$/is" , "" , $referer_path);
        if (!$filename_save) 
        {
            $image_filename=sccstslug_take_file_name($source);    
        }
        else
        {
            $image_filename=$filename_save;               
        }             

        $url_to_download = str_replace(' ', '%20', $source); 
         
        $args = array(
            'timeout'=> 240, 
            'redirection' => 10, 
            'filename' => $path_save.$image_filename, 
            'user-agent' => "MozillaMozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.11) Gecko/2009060215 Firefox/3.0.9", // who am i
            'stream'=>true,
            );
        $downloadInfo = wp_remote_get($url_to_download, $args);
        
//      $downloadInfo['headers']['content-type']    
        if ($downloadInfo['response']['code'] == 200)
        {
            // = 200 thi nghia la da load duoc anh thanh cong
            $info = getimagesize($path_save.$image_filename);
            $width = $info[0]; $height = $info[1]; $type = $info[2];
            //doi ten file cho co bao gom extension   
            $file_mime = $info['mime'];
            if ($type) 
            {
                $load_status =1;                     
                $image_type = image_type_to_extension($type);                                
                if (strripos($image_filename, $image_type) === false || (strripos($image_filename, $image_type) !== false && strtolower(substr($image_filename, -strlen($image_type))) !== strtolower($image_type))) 
                {
                    //    unlink($path_save.$image_filename.$image_type);   //delete to avoide doublicate.                       
                    rename($path_save.$image_filename, $path_save.$image_filename.$image_type);  //change name to include correct extension 
                    $image_filename = $image_filename.$image_type;      
                }                                
            } 
            else 
            {
                $load_status =1; 
                // xoa khi khong phai la anh    
                //    unlink ($path_save.$image_filename);
                //   $error = 'Source is not image!';
            }    
        }     
        else 
            $error = 'Load image <b>'.$image_filename.'</b> fail! <br />Reason: '.$error;
    }
    $image_filename_new = $image_filename;
    if (!$error and $flag_resize) // load success and wish resize
    {
        $oldimg = $path_save.$image_filename;
        $image_filename_new= "best_".$image_filename;
        $newimg = $path_save.$image_filename_new;               
        $resize_status=sccstslug_resize_image($oldimg,$newimg,$width, $height, $type, $horizon_wish_width,$vertical_wish_width); 
        if (!$resize_status)  $image_filename_new=$image_filename;    // no resize so use the old one                 
    }

    if ($error == '')
    {
        return array($image_filename,$image_filename_new,$load_status, $file_mime);                       
    }
    else
    {
        return array('','',$load_status);
    }
}

function sccstslug_resize_image($oldimg,$newimg,$imagewidth, $imageheight, $imagetype, $horizon_wish_width,$vertical_wish_width)
{
    $process_status = 1;
    if ($horizon_wish_width < 150 )
    {
        $jpg_quality=75;        
    }
    else
        if ($horizon_wish_width < 250 )
        {
            $jpg_quality=83;        
        }
        else
            if ($horizon_wish_width < 500 )
            {
                $jpg_quality=85;        
            }
            else
            {
                $jpg_quality=90;        
    }

    if (!$imagewidth)
    {
        list($imagewidth, $imageheight, $imagetype, $attr) = GetImageSize($oldimg);
    }

    if ($imagewidth)
    {
        if ($imagewidth > $imageheight) // horizon image
        {
            if ($imagewidth > $horizon_wish_width)
            {
                $width_new = $horizon_wish_width;
                $height_new = round($imageheight * ($width_new / $imagewidth));        
                // create thumb
                $maxwidth =  $width_new;
                $maxheight = $height_new;
                $shrinkage = 1;
                if($imagewidth > $maxwidth) {
                    $shrinkage = $maxwidth / $imagewidth;
                }
                if($shrinkage != 1) {
                    $dest_height = $shrinkage * $imageheight;
                    $dest_width = $maxwidth;
                }
                else 
                {
                    $dest_height = $imageheight;
                    $dest_width = $imagewidth;
                }
                if($dest_height > $maxheight) {
                    $shrinkage = $maxheight / $dest_height;
                    $dest_width = $shrinkage * $dest_width;
                    $dest_height = $maxheight;
                }

                if($imagetype == 2) {
                    $src_img = @imagecreatefromjpeg($oldimg);
                    $dst_img = @imagecreatetruecolor($dest_width,$dest_height);
                    imagefill($dst_img,0,0,imagecolorallocate($dst_img,255,255,255));
                    imagecopyresampled($dst_img,$src_img,0,0,0,0,$dest_width,$dest_height,$imagewidth,$imageheight);
                    imagejpeg($dst_img,$newimg,$jpg_quality);  //75 quality
                    imagedestroy($src_img);
                    imagedestroy($dst_img);
                } elseif($imagetype == 3) {
                    $src_img = @imagecreatefrompng($oldimg);
                    $dst_img = @imagecreatetruecolor($dest_width,$dest_height);
                    imagefill($dst_img,0,0,imagecolorallocate($dst_img,255,255,255));
                    imagecopyresampled($dst_img,$src_img,0,0,0,0,$dest_width,$dest_height,$imagewidth,$imageheight);
                    imagepng($dst_img,$newimg);
                    imagedestroy($src_img);
                    imagedestroy($dst_img);
                }
                else {
                    $src_img = @imagecreatefromgif($oldimg);
                    $dst_img = @imagecreatetruecolor($dest_width,$dest_height);
                    imagefill($dst_img,0,0,imagecolorallocate($dst_img,255,255,255));
                    imagecopyresampled($dst_img,$src_img,0,0,0,0,$dest_width,$dest_height,$imagewidth,$imageheight);
                    imagegif($dst_img,$newimg);
                    imagedestroy($src_img);
                    imagedestroy($dst_img);
                }
                // end create thumb                    
            } 
            else
            {
                $process_status = 0;
            }
        }
        else // vertical image
        {
            if ($imagewidth > $vertical_wish_width)    
            {
                $width_new = $vertical_wish_width;
                $height_new = round($imageheight * ($width_new / $imagewidth));
                $maxwidth =  $width_new;
                $maxheight = $height_new;

                $shrinkage = 1;
                if($imagewidth > $maxwidth) {
                    $shrinkage = $maxwidth / $imagewidth;
                }
                if($shrinkage != 1) {
                    $dest_height = $shrinkage * $imageheight;
                    $dest_width = $maxwidth;
                }
                else {
                    $dest_height = $imageheight;
                    $dest_width = $imagewidth;
                }
                if($dest_height > $maxheight) {
                    $shrinkage = $maxheight / $dest_height;
                    $dest_width = $shrinkage * $dest_width;
                    $dest_height = $maxheight;
                }

                if($imagetype == 2) {
                    $src_img = @imagecreatefromjpeg($oldimg);
                    $dst_img = @imagecreatetruecolor($dest_width,$dest_height);
                    imagefill($dst_img,0,0,imagecolorallocate($dst_img,255,255,255));
                    imagecopyresampled($dst_img,$src_img,0,0,0,0,$dest_width,$dest_height,$imagewidth,$imageheight);
                    imagejpeg($dst_img,$newimg,$jpg_quality);  //75 % qulity
                    imagedestroy($src_img);
                    imagedestroy($dst_img);
                } elseif($imagetype == 3) {
                    $src_img = @imagecreatefrompng($oldimg);
                    $dst_img = @imagecreatetruecolor($dest_width,$dest_height);
                    imagefill($dst_img,0,0,imagecolorallocate($dst_img,255,255,255));
                    imagecopyresampled($dst_img,$src_img,0,0,0,0,$dest_width,$dest_height,$imagewidth,$imageheight);
                    imagepng($dst_img,$newimg);
                    imagedestroy($src_img);
                    imagedestroy($dst_img);
                }
                else {
                    $src_img = @imagecreatefromgif($oldimg);
                    $dst_img = @imagecreatetruecolor($dest_width,$dest_height);
                    imagefill($dst_img,0,0,imagecolorallocate($dst_img,255,255,255));
                    imagecopyresampled($dst_img,$src_img,0,0,0,0,$dest_width,$dest_height,$imagewidth,$imageheight);
                    imagegif($dst_img,$newimg);
                    imagedestroy($src_img);
                    imagedestroy($dst_img);
                }
                // end create thumb                                            
            }   
            else
            {
                $process_status = 0;                    
            }
        }                 
    }
    else
    {
        $process_status = 0;
    }
    return $process_status;    
}

function sccstslug_take_file_name($external_image_url) 
{
    $image_filename=preg_replace('/.*\/(.*)/','$1', urldecode($external_image_url));
    //                        \ / : * ? " < > |           
    $image_filename = preg_replace('/[^a-zA-Z0-9-.]/', '-', $image_filename);
    $image_filename=str_replace(' ','-',$image_filename);            
    $image_filename = preg_replace('/^[-]+/', '', $image_filename);
    $image_filename = preg_replace('/[-]+$/', '', $image_filename);
    $image_filename = preg_replace('/[-]{2,}/', ' ', $image_filename); 

    $image_filename=str_replace('.jpg','.jpeg',$image_filename);
    if (strlen($image_filename) > 80) $image_filename = substr($image_filename,-80);
    $image_filename=trim($image_filename,'-');                               
    $image_filename=trim($image_filename);
    return $image_filename;    
} 

function sccstslug_get_default_item_setting(){
    return array(
        'title' => '',
        'cat' => '',
        'take_thumb' => 0,
        'take_image' => 0,
        'time_delay' => '0',
        'status' => '1',
        'secret_key' => "",
    );;
}
