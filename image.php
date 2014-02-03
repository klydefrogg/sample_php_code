<?php 
//phpinfo();
if (!defined('MAGICK_PATH')) {
	define('MAGICK_PATH', '/Applications/MAMP/bin/ImageMagick/ImageMagick-6.7.5/bin/convert');
}
class Image {
	
	
	const SMALL_MDPI = 100;
	const NORMAL_MDPI = 133;
	const NORMAL_HDPI = 200;
	const LARGE_HDPI = 225;
	const LARGE_XHDPI = 300;
	const XLARGE_MDPI = 300;
	
	//leveraged by other code
	public $_imagick;
	
	protected $_path;
	
	
	//create a lookup table of resolutions matched with names
	protected $_resolutions = array(
									Image::SMALL_MDPI=>'small_mdpi',
									Image::NORMAL_MDPI=>'normal_mdpi',
									Image::NORMAL_HDPI,'normal_hdpi',
									Image::LARGE_HDPI,'large_hdpi',
									Image::LARGE_XHDPI,'large_xhdpi',
									Image::XLARGE_MDPI,'xlarge_mdpi'
								);
	
	protected $_baseWidth=0;
	protected $_baseHeight=0;
	
	private $_dpi=0;
	
	//this information is useful to other scripts
	public $info;
	
	
	function __construct ($path="") {
		
		//$path should refer to an image
		
		if ($path == "") return false;
		
		$this->_path = pathinfo($path);
		
		$this->_imagick=new Imagick();
		
		$this->_imagick->readImage($path);
		
		$this->info = $this->_imagick->identifyImage();
		
		$this->_dpi = $this->_imagick->getImageResolution();
		
		$this->_dpi = $this->_dpi['y'];
		
		//if the units are in CM for some reason, convert the resolution
		if ($this->info['units']=='PixelsPerCentimeter') {
			$this->_dpi = ceil($this->_dpi * 2.54);
		}
	}
	
	//fit the image with overflow, crop the excess
	function scaleAndCrop($w=0, $h=0) {
		if ($w==0 || $h==0) {
			//no point in the extra processing for cropping, simply resize the image
			return $this->resize($w, $h);
		}
		
		//aspect ratio of requested size
		$ar = $w/$h;
		
		//aspect ratio of current size
		$native_ar = $this->_imagick->getImageWidth()/$this->_imagick->getImageHeight();
		
		if ($ar == $native_ar) {
			//aspect ratio is the same as the original. just resize it
			return $this->resize($w, 0);
		}
		
		if ($ar > $native_ar) { //match the width, crop the top and bottom
			$img = $this->resize($w, 0);
			$y = ceil(($img->getImageHeight() - $h)/2);
			$img->cropImage($w, $h, 0, $y);
		} else { //match the height, crop the sides
			$img = $this->resize(0, $h);
			$x = ceil(($img->getImageWidth() - $w)/2);
			$img->cropImage($w, $h, $x, 0);
		}
		
		return $img;
	}
	
	//simple resize function
	function resize($w=0, $h=0) {
		$new = $this->_imagick->clone();
		
		$new->scaleImage($w, $h, false);
		
		return $new;
	}
	
	//resample based on DPI
	function resample($dpi=72) {
		
		$new = $this->_imagick->clone();
		
		//get scale by comparing resolutions
		$scale = $dpi / $this->_dpi;
		
		//new width
		$w = ceil($this->_imagick->getImageWidth() * $scale);
		
		$new->scaleImage($w, 0, false);
		
		$new->setResolution($dpi,$dpi);
		
		return $new;
	}


	//some images require specific variations.
	//generate those all at once in a specific place
	function generateSizes($outputPath=false) {
		
		if ($outputPath==false) $outputPath=$this->_path['dirname'];
		
		$outputPath=implode(DIRECTORY_SEPARATOR, explode(DIRECTORY_SEPARATOR, $outputPath)).DIRECTORY_SEPARATOR;
		
		if (!file_exists($outputPath)) {
			mkdir($outputPath,0775);
		}
		
		$paths = array();
		
		//cycle through the list of resolutions
		foreach ($this->_resolutions as $res=>$name) {
			
			if ($this->_dpi >= $res) { //make sure we're not scaling up
				$img = $this->resample($res);
				$newName = $this->_path['filename']."_".$name.".".$this->_path['extension'];
				$img->writeImage($outputPath.$newName);
				$paths[$name]=$outputPath.$newName;
			}
		}
		//return the locations/names of all new images
		return $paths;
	}
}

?>