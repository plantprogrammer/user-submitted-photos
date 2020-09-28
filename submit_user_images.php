<?php
/*
Plugin Name: Submit Images for Adventures
Plugin URI: https://iansackofwits.com
Description: Allows users to submit images for Adventures.
Version: 1.0
License: GPLv2
Author: Ross Elliot & Ian Sajkowicz  
Author URI: https://iansackofwits.com
*/

define('MAX_UPLOAD_SIZE_BYTES', 2500000);
define('MAX_UPLOAD_SIZE_MBYTES', 2.5);
define('TYPE_WHITELIST', serialize(array(
  'image/jpeg',
  'image/png',
  'image/gif'
  )));


add_shortcode('adventure_upload', 'sui_form_shortcode');


function sui_form_shortcode(){

  if(isset( $_POST['sui_upload_image_form_submitted'] ) && wp_verify_nonce($_POST['sui_upload_image_form_submitted'], 'sui_upload_image_form') ){  
	  
    $result = sui_parse_file_errors($_FILES['sui_image_file'], $_POST['sui_image_caption']);
    
    if($result['error']){
    
      echo '<p>ERROR: ' . $result['error'] . '</p>';
    
    }
	else
	{
		$user_image_data = array(
			'post_title' => $result['caption'],
			'post_status' => 'pending',
			'post_author' => '1',
			'post_type' => 'user_images'     
		  );
		  if($post_id = wp_insert_post($user_image_data)){

			sui_process_image('sui_image_file', $post_id, $result['caption']);
		  }
	}
  }  
  echo sui_get_upload_image_form($sui_image_caption = $_POST['sui_image_caption']);
  
}


function sui_delete_user_images($images_to_delete){

  $images_deleted = 0;

  foreach($images_to_delete as $user_image){

    if (isset($_POST['sui_image_delete_id_' . $user_image]) && wp_verify_nonce($_POST['sui_image_delete_id_' . $user_image], 'sui_image_delete_' . $user_image)){
    
      if($post_thumbnail_id = get_post_thumbnail_id($user_image)){

        wp_delete_attachment($post_thumbnail_id);      

      }  

      wp_trash_post($user_image);
      
      $images_deleted ++;

    }
  }

  return $images_deleted;

}


function sui_process_image($file, $post_id, $caption){
 
  $attachment_id = media_handle_upload($file, $post_id);
 
  update_post_meta($post_id, '_thumbnail_id', $attachment_id);

  $attachment_data = array(
  	'ID' => $attachment_id,
    'post_excerpt' => $caption
  );
  
  wp_update_post($attachment_data);

  return $attachment_id;

}


function sui_parse_file_errors($file = '', $image_caption){

  $result = array();
  $result['error'] = 0;
  
  if($file['error']){
  
    $result['error'] = "No file uploaded or there was an upload error!";
    
    return $result;
  
  }

  $image_caption = trim(preg_replace('/[^a-zA-Z0-9\s]+/', ' ', $image_caption));
  
  if($image_caption == ''){

    $result['error'] = "Your caption may only contain letters, numbers and spaces!";
    
    return $result;
  
  }
  
  $result['caption'] = $image_caption;  

  $image_data = getimagesize($file['tmp_name']);
  
  if(!in_array($image_data['mime'], unserialize(TYPE_WHITELIST))){
  
    $result['error'] = 'Your image must be a jpeg, png or gif!';
    
  }elseif(($file['size'] > MAX_UPLOAD_SIZE_BYTES)){
  	$file_size_me_bytes = $file['size']/1048576;
    $result['error'] = 'Your image was ' . round($file_size_me_bytes,2) . ' megabytes! It must not exceed ' . MAX_UPLOAD_SIZE_MBYTES . ' megabytes.';
    
  }
    
  return $result;

}



function sui_get_upload_image_form($sui_image_caption = ''){
  
  $out = '';
  $out .= '<form id="sui_upload_image_form" method="post" action="" enctype="multipart/form-data">';

  $out .= wp_nonce_field('sui_upload_image_form', 'sui_upload_image_form_submitted');
  
  $out .= '<label for="sui_image_caption">Please Enter Your Image Name</label><br/>';
  $out .= '<input type="text" id="sui_image_caption" name="sui_image_caption" value="' . $sui_image_caption . '"/><br/>';
  $out .= '<label for="sui_image_file">Select Your Image - ' . MAX_UPLOAD_SIZE_MBYTES . ' megabytes maximum</label><br/>'; 
  $out .= '<input type="file" size="60" name="sui_image_file" id="sui_image_file"><br/>';
    
  $out .= '<input type="submit" id="sui_submit" name="sui_submit" value="Upload Image">';

  $out .= '</form>';

  return $out;
  
}

add_action('init', 'sui_plugin_init');

function sui_plugin_init(){

  $image_type_labels = array(
    'name' => _x('User images', 'post type general name'),
    'singular_name' => _x('User Image', 'post type singular name'),
    'add_new' => _x('Add New User Image', 'image'),
    'add_new_item' => __('Add New User Image'),
    'edit_item' => __('Edit User Image'),
    'new_item' => __('Add New User Image'),
    'all_items' => __('View User Images'),
    'view_item' => __('View User Image'),
    'search_items' => __('Search User Images'),
    'not_found' =>  __('No User Images found'),
    'not_found_in_trash' => __('No User Images found in Trash'), 
    'parent_item_colon' => '',
    'menu_name' => 'User Images'
  );
  
  $image_type_args = array(
    'labels' => $image_type_labels,
    'public' => true,
    'query_var' => true,
    'rewrite' => true,
    'capability_type' => 'post',
    'has_archive' => true, 
    'hierarchical' => false,
    'map_meta_cap' => true,
    'menu_position' => null,
    'supports' => array('title', 'author', 'thumbnail')
  ); 
  
  register_post_type('user_images', $image_type_args);

}
