<?php
/**
 This script handles all requests for images in this directory or subdirectories
 looking for specific size requests.
 
 Image sources are located in another folder and accessed when needed.
 
 This script is the handler for 404 errors in the /images/dynamic/ directory.
 */


require_once($_SERVER['DOCUMENT_ROOT']."/../include/globals.php"); //contains mime_type function
require_once($_SERVER['DOCUMENT_ROOT'].'/../include/classes/image.php');

//perform image resizing and save the new version
//if no resize is necessary, save the image in a cached location
//resize based on pixels
function doImageResize($img, $pathinfo, $newfilename, $w=0, $h=0) {
	
	$path = $pathinfo['basename'];
	
	//set up the output format
		
	if ($pathinfo['extension']=='jpg' || $pathinfo['extension']=='jpeg') {
		//save as JPEG
		$fmt = "jpeg";
		$qual = 60;
	} else {
		//save as PNG
		$fmt = "png";
		$qual = 100;
	}
	
	$ext=$pathinfo['extension'];
	
	
	if ($h!==0 || $w!==0) { //new size has been requested. generate it now
		$newImg = $img->resize($w, $h);
	} else { 
		//not requesting a new size, just the first request of this specific image. 
		//cache it for later requests
		$newImg = $img->_imagick;
	}
	//output the new image with the proper formatting
	$newImg->setImageFormat($fmt);
	$newImg->setCompressionQuality($qual);
	$newImg->writeImage($_SERVER['DOCUMENT_ROOT'].'/images/dynamic/processed/'.$newfilename);

}

//perform image resizing and save the new version
//if no resize is necessary, save the image in a cached location
//resize based on percentage of the original size
function doImageResizePct($img, $pathinfo, $newfilename, $scale=1) {
	
	$path = $pathinfo['basename'];
	
	if ($pathinfo['extension']=='jpg' || $pathinfo['extension']=='jpeg') {
		//save as JPEG
		$fmt = "jpeg";
		$qual = 60;
	} else {
		//save as PNG
		$fmt = "png";
		$qual = 100;
	}
	
	$ext=$pathinfo['extension'];
	
	$geom = $img->_imagick->getImageGeometry();
	
	
	$ww = ceil($scale * $geom['width']);
	$hh = ceil($scale * $geom['height']);
	
	$newImg = $img->scaleAndCrop($ww, $hh);
	$newImg->setImageFormat($fmt);
	$newImg->setCompressionQuality($qual);
	$newImg->writeImage($_SERVER['DOCUMENT_ROOT'].'/images/dynamic/processed/'.$newfilename);
}

//Handle the 404 error here...
//get the request information so we can parse it and look for an original image
$thisURI = $_SERVER["REQUEST_URI"];

$path=explode("/", $thisURI);
array_shift($path);

//get all the uri parameters by parsing the request string
//handling through the apache 404 redirect does not set the $_GET array
$request = explode("?",$path[count($path)-1]);
if (isset($request[1])) {
	$get = explode("&", $request[1]);
	
	foreach ($get as $v) {
		$parts = explode("=",$v);
		$_GET[$parts[0]]=$parts[1];
	}
}
$path_parts = pathinfo($request[0]);

//default scale mode
$scaleMode = "px";

//appending the original image name with the parameters used for scaling. 
//if the same request is made again we'll just return the cached version
$path_adjust = "";

if (isset($_GET['s'])) { //scale by android scale string (xhdpi, etc)
	$path_adjust="_".$_GET['s'];
	$scaleMode = "pct";
	switch(strtolower($_GET['s'])) {
		case "xhdpi": $scale=1;break;
		case "hdpi": $scale=.75; break;
		case "mdpi": $scale=.5; break;
	}
} else {
	if (isset($_GET['w'])) { //scale based on width requested
	
		$path_adjust .= "_w".$_GET['w'];
		
		$ww = intval($_GET['w']);
		if (strpos($_GET['w'], 'pct')!==false) {
			$scaleMode = "pct";
			$scale = ($ww)/100;
		}
	} else {
		$ww = 0; //let width be calculated by requested height, or don't scale at all
	}
	if (isset($_GET['h'])) { //scale based on height requested
		$path_adjust .= "_h".$_GET['h'];
		$hh = intval($_GET['h']);
		if (strpos($_GET['h'], 'pct')!==false) {
			$scaleMode = "pct";
			$scale = ($hh)/100;
		}
	} else {
		$hh = 0; //let height be calculated by requested width, or don't scale at all
	}
}

if (file_exists($_SERVER['DOCUMENT_ROOT']."/../image_sources/".$path_parts['basename'])) { //make sure source the image exists
	$img = new Image($_SERVER['DOCUMENT_ROOT']."/../image_sources/".$path_parts['basename']);
	
	if ($scaleMode == "px") {
		//we don't want to scale up, so if a request is made for a larger size than the original return the original
		if ($ww > $img->info['geometry']['width']) {
			$ww = $img->info['geometry']['width'];
			$path_adjust="_w$ww";
		}
		if ($hh > $img->info['geometry']['height']) {
			$hh = $img->info['geometry']['height'];
			$path_adjust="_h$hh";
		}
	}
	
	$realFileName = $path_parts['filename'].$path_adjust.".".$path_parts['extension'];
	
	// look for an existing image in the requested size
	
	//if the requested size already exists and it's not older than the original, return the cached version
	if (file_exists("./processed/".$realFileName) && filemtime("./processed/".$realFileName) >= filemtime($_SERVER['DOCUMENT_ROOT']."/../image_sources/".$path_parts['basename'])) {
		//clear out the 404
		header("HTTP/1.1 200 OK");
		header("Content-Type: ".mime_type($realFileName));
		header("Content-Length: ".filesize("./processed/".$realFileName));
		echo file_get_contents("./processed/".$realFileName);
		exit;
	}
	
	//continue on with the new image request
	
	//error_log("IMAGE NEEDS CREATED");
	if ($scaleMode == "px") {
		doImageResize($img, $path_parts, $realFileName, $ww, $hh);
	} else {
		doImageResizePct($img, $path_parts, $realFileName, $scale);
	}
	//clear out the 404
	header("HTTP/1.1 200 OK");
	header("Content-Type: ".mime_type($realFileName));
	header("Content-Length: ".filesize("./processed/".$realFileName));
	echo file_get_contents("./processed/".$realFileName);
	exit;
		
}


//if found, resize to request and save & send



//if not found, proceed with 404
?>
<h1>Not Found</h1>