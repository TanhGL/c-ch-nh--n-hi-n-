<?php

class Image
{
	const CHMOD = 775;
	
	private static function random_str($len = 10){
		$chr = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$chr_len = strlen($chr);
		$random_str = '';
		
		for($i = 0; $i < $len; $i++){
			$random_str .= $chr[rand(0, $chr_len - 1)];
		}
		
		return $random_str;
	}
	
	private static function thumb($source_path, $thumb_path){
		ini_set('memory_limit', '128M');
		
		$thumb_w = 476;
		$thumb_h = 476;
		
		$source_details = getimagesize($source_path);
		$source_w = $source_details[0];
		$source_h = $source_details[1];
		
		if($source_w > $source_h){
			$new_w = $thumb_w;
			$new_h = intval($source_h * $new_w / $source_w);
		} else {
			$new_h = $thumb_h;
			$new_w = intval($source_w * $new_h / $source_h);
		}
		
		switch($source_details[2]){
			case IMAGETYPE_GIF:
				$imgt = "ImageGIF";
				$imgcreatefrom = "ImageCreateFromGIF";
				break;
			
			case IMAGETYPE_JPEG:
				$imgt = "ImageJPEG";
				$imgcreatefrom = "ImageCreateFromJPEG";
				break;
			
			case IMAGETYPE_PNG:
				$imgt = "ImagePNG";
				$imgcreatefrom = "ImageCreateFromPNG";
				break;
			
			default:
				return false;
		}
		
		$old_image = $imgcreatefrom($source_path);
		$new_image = imagecreatetruecolor($new_w, $new_h);
		imagecopyresampled($new_image, $old_image, 0, 0, 0, 0, $new_w, $new_h, $source_w, $source_h);
		//$new_image = imagecreatetruecolor($thumb_w, $thumb_h);
		//imagecopyresized($new_image, $old_image, $dest_x, $dest_y, 0, 0, $new_w, $new_h, $source_w, $source_h);
		return $imgt($new_image, $thumb_path);
	}
	
	public static function upload($name, $data){
		ini_set('memory_limit', '128M');

		$photo = null;
		$ext = null;
		
		if($data){
			preg_match('/^data\:image\/(jpe?g|png|gif)\;base64,(.*)$/', $data, $m);
			
			if(!$m){
				throw new Excrption("Invalid file.");
			}
			
			$ext = $m[1];
			if($ext == "jpeg") $ext = "jpg";
			
			// Decode photo
			$photo = base64_decode($m[2]);
		}
		
		if($_FILES){
			$photo = file_get_contents($_FILES["file"]["tmp_name"]);
			$name = $_FILES['file']['name'];
			$ext = pathinfo($name, PATHINFO_EXTENSION);
		}
		
		if(!$_FILES && !$data){
			throw new Excrption("No file.");
		}
		
		// Create MD5
		$md5 = md5($photo);
		
		// Find duplicate
		if($d = DB::get_instance()->query("SELECT `path`, `thumb` FROM `images` WHERE `md5` = ? AND `status` = 1 LIMIT 1", $md5)->first()){
			return $d;
		}
		
		// Save to DB
		$id = DB::get_instance()->query(
			"INSERT INTO `images` ".
			"(`id`, `name`, `path`, `thumb`, `type`, `md5`, `datetime`, `status`) ".
			"VALUES (NULL, ?, NULL, NULL, ?, ?, NOW(), 1);",
			$name, $ext, $md5
		)->last_id();
		
		// Create path name
		$name = dechex($id).self::random_str(3).".".$ext;
		$path = 'i/'.$name;
		$thumb = 't/'.$name;
		
		// Save path
		if(false === file_put_contents($path, $photo)){
			// Try to set chmod
			if(chmod("i", Image::CHMOD) && chmod("t", Image::CHMOD)){
				throw new Exception("Can't write to file. Chmod has been changed, try again.");
			}
			
			DB::get_instance()->query("UPDATE `images` SET `status` = 0 WHERE `id` = ?", $id);
			throw new Exception("Can't write to file.");
		}
		
		// Create thumb
		self::thumb($path, $thumb);
		
		// Save to DB
		DB::get_instance()->query("UPDATE `images` SET `path` = ?, `thumb` = ?, `status` = 1 WHERE `id` = ?", $path, $thumb, $id);
		return [
			"path" => $path,
			"thumb" => $thumb
		];
	}
}