<?php

//error_log("c5 data api connecting...");
define ("DIR_BASE", dirname(__FILE__)."/../");

define ("C5_ENVIRONMENT_ONLY", true);

require(DIR_BASE.'concrete/dispatcher.php');
 
require_once($_SERVER['DOCUMENT_ROOT']."/../application/models/Dto/Product.php");




$_SESSION['data']="";
class C5_API {
	
	//the root page IDs for these categories. children will be accessed from these IDs
	private static $phone_cID = 122;
	private static $accessory_cID = 123;
	
	
	private function _getProductBasicFromPageId($v, $p=false) {
		$product = array();
		
		if ($p==false) {
			$p = Page::getByID($v);
		} 
		
		$product['id']=$v;
		
		$product['isActive'] = (bool)$p->getAttribute('isActive');
		
		$roles = $p->getAttribute('permissions');
		
		$product['roles']=array();
		
		foreach ($roles as $v) {
			$r = explode(":",$v);
			$product['roles'][$r[1]]=true;
		}
		
		$roles = $p->getAttribute('permissions_all_or_any');
		
		foreach ($roles as $v) {
			$product['rolesMatchAll'] = (bool)(($v=="All")?1:0);
		}
		
		$product['publishAt'] = strtotime($p->getAttribute('publish_date'));
		
		$product['publicAt'] = strtotime($p->getAttribute('public_date'));
		
		$product['expiresAt'] = strtotime($p->getAttribute('expiration_date'));
		
		$product['page']=$p;
		
		return $product;
	}
	
	public function _getAllProductsList() {
		$_products = Page::getByID(self::$phone_cID);
		$_products = $_products->getCollectionChildrenArray(1);
		$products = array();
		
		foreach ($_products as $v) {
			$products[]=self::_getProductBasicFromPageId($v);
		}
		
		return $products;
	}
	
	public function getProductFromPage($page) {
		error_log(__LINE__);
		$p = new Model_Dto_Product($page);
		error_log(__LINE__);
		if(WEB_REQUEST) {
			error_log(__LINE__);
			$showAudioButton = $page['page']->getAttribute('show_mute_button');
			error_log(__LINE__);
			$p->showMuteButton = (bool)$showAudioButton;
			error_log(__LINE__);
		}
		error_log(__LINE__);
		return $p;
	}
	
	public function getProducts(&$results, $userRoles=false) {
		
		//get all product page IDs
		$products = self::_getAllProductsList();
		foreach ($products as $v) {
			
			if (self::productIsVisible($v, $userRoles)) {
				$results[]=new Model_Dto_Product($v);
			} else {
				//$error_log("product not visible");
			}
		}
	}
	
	public function productIsVisible($product, $userRoles=false) {
		$now = time();
		
		//not active, return false for all
		
		if ($product['isActive']==false) return false;
		
		//product has expired, false for all
		//don't use for now		
		//if ($product['expiresAt']<$now) return false;
		
		//not public, false for anonymous users
		if ($product['publicAt']>$now && $userRoles == false) return false;
		
		//not published yet... visible only to admin
		if ($product['publishAt']>$now && !isset($userRoles[11])) return false;
		
		//public... visible to all anonymous
		if ($product['publicAt']<=$now && $userRoles == false) return true;
		
		//catch-all for anonymous users
		if ($userRoles == false) return false;
		
		if (isset($userRoles[11])) return true;
		//product-specific permissions for logged-in users
		if ($product['publishAt']<=$now) {
			if ($product['rolesMatchAll']) {
				$allowed=true;
				foreach($product['roles'] as $k=>$v) {
					if (!isset($userRoles[$k])) $allowed = false;
				}
			} else {
				$allowed=false;
				foreach($product['roles'] as $k=>$v) {
					if (isset($userRoles[$k])) $allowed = true;
				}
			}
			return $allowed;
		}
		
		//probably not visible if we're still here
		return false;
	}
	
	public function productExists($id) {
		$p = Page::getByID($id);
		if ($p===false) {
			return false;
		}
		if ($p->vObj->ctHandle != "phone") {
			return false;
		} else {
			return self::_getProductBasicFromPageId($id, $p);
		}
	}
	
	private function _getChildPages($id, $viewed=false, $first = false) {
		//$conn = $GLOBALS['conn'];
		
		$is_mobile = false;
		if (strtolower($headers['TOT-Mobile-App'])=="true" || strpos($_SERVER['HTTP_USER_AGENT'], 'Android')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile')!==false) {
			$is_mobile = true;
			error_log("IS MOBILE: TRUE");
		} else {
			error_log("IS MOBILE: FALSE");
		}
		
		if ($first===true) {
			//error_log("Checking for tree cache");
			
			if ($is_mobile===false) $mobile = "";
			else $mobile = "m_";
			
			
			if (!defined("CACHE_DISABLED") && file_exists($_SERVER['DOCUMENT_ROOT'].'/../caches/tree_'.$mobile.$id.'.php') && filemtime($_SERVER['DOCUMENT_ROOT'].'/../caches/tree_'.$mobile.$id.'.php') > (time()-60*60*3) ) {
				$tree = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/../caches/tree_'.$mobile.$id.'.php');
				$tree = unserialize($tree);
				return $tree;
			}
		}
		
		$list = array();
		$page = Page::getById($id);
		
		$pages = $page->getCollectionChildrenArray(1);
		
		
		//if (WEB_REQUEST) $is_mobile = false;
		
		foreach ($pages as $p) {
			$pp = Page::getById($p);
			$active = ($pp->getAttribute('isActive')=='1')?true:false;
			$valid = true;//($is_mobile && $pp->getAttribute('desktop_only')=='1')?false:true;
			
			if ($is_mobile===true && $pp->getAttribute('desktop_only')!==false) $valid = false;
			
			if ($active==true && $valid===true) {// && (!WEB_REQUEST || (WEB_REQUEST && $pp->vObj->ctHandle!="resources")) ) {
				$item = array();
				
				//$item['desktop_only'] = ($pp->getAttribute('desktop_only')===false)?"No":"YES";
				$item['is_mobile']=$is_mobile;
				
				$item['excludeFromLinearNav'] = ($pp->getAttribute('exclude_from_linear_nav')=='1')?true:false;
				
				$item['pageIsPopup'] = ($pp->getAttribute('display_as_popup')=='1')?true:false;
				
				$item['children']=self::_getChildPages($p, $viewed);
				if ($item['children']==false) unset($item['children']);
				
				$item['title']=$pp->getAttribute('title_html');
				if (empty($item['title']) || count($item['title'])==0 || $item['title']==false) {
					$item['title']=$pp->vObj->cvName;
				}
				
				$item['appearsInMenu']=(bool)$pp->getAttribute('appearsinmenu');
				$item['required']=(bool)$pp->getAttribute('required');
				$item['url']="http://".$_SERVER['HTTP_HOST']."/c5".$pp->cPath;
				$item['modifiedAt']=strtotime($pp->cDateModified);
				$item['createdAt']=strtotime($pp->cDateAdded);
				$item['pageId']=intval("999".$pp->cID);
				$item['locale']="en-US";
				switch ($pp->vObj->ctHandle) {
					case("specifications"):
						$item['pageType']="specifications";
					break;
					case("feature_main"):
						$item['pageType']="feature";
					break;
					case("accessories"):
						$item['pageType']="accessories";
					break;
					case("feature_demo"):
						$item['pageType']="demo";
					break;
					case("callouts"):
						$item['pageType']="key_callouts";
					break;
					case("quiz"):
						$item['pageType']="quiz";
					break;
					case("splash"):
						$item['pageType']="splash";
					break;
					case("resources"):
						$item['pageType']="resources";
					break;
					case("section_head_no_content"):
						$item['pageType']="no_content";
						$item['url']="";
					break;
					default:
						$item['pageType']="content";
					break;
				}
				
				$item['userHasViewed']=false;
				
				if ($viewed!=false && $viewed[intval("999".$pp->cID)]) {
					$item['userHasViewed']=true;
				}
				
				
				$list[]=$item;
				//error_log(print_r($p,true));
			}
		}
		
		if (count($list)==0) return false;
		
		if (!defined("CACHE_DISABLED") && $first===true) {
			$tree = serialize($list);
			file_put_contents($_SERVER['DOCUMENT_ROOT'].'/../caches/tree_'.$mobile.$id.'.php',$tree);
		}
		return $list;
		
	}
	
	private function markNavViewed(&$nav, $viewed=false) {
		if ($viewed == false && IS_MOBILE) return $nav;
		//error_log("Mark Nav Viewed: ");
		foreach ($nav as $k=>$v) {
			//error_log(print_r($v,true));
			if (isset($viewed[$v['pageId']])) {
				//error_log("marking viewed: ".$v['pageId']);
				$nav[$k]['userHasViewed']=true;
			}
			if (WEB_REQUEST && $nav[$k]['pageType']=="resources") {
				unset($nav[$k]);
			} else if (isset($nav[$k]['children'])) {
				self::markNavViewed($nav[$k]['children'], $viewed);
			}
		}
	}
	
	public function getProductNavigation($prim, $viewed=false) {
		$nav =  self::_getChildPages($prim['id'], false, true);
		self::markNavViewed($nav, $viewed);
		
		return array_values($nav);
	}
	
	public function generateImageSizes($img) {
		$result = array("xhdpi"=>"", "hdpi"=>"", "mdpi"=>"");
		
		$iPathAbs = $img->getPath();
		$iPathRel = $img->getRelativePath();
		
		$iParts = pathinfo($iPathAbs);
		//if (!file_exists($iParts['dirname'].'/'.$iParts['filename'].'_mdpi.'.$iParts['extension'])) {
			//$im = Loader::helper('image');
			//$im->create($_SERVER['DOCUMENT_ROOT'].'/c5/'.$iPathRel, $iParts['dirname'].'/'.$iParts['filename'].'_mdpi.'.$iParts['extension'], 80, 80);
			//$im->create($_SERVER['DOCUMENT_ROOT'].'/c5/'.$iPathRel, $iParts['dirname'].'/'.$iParts['filename'].'_hdpi.'.$iParts['extension'], 120, 120);
		//}
		$iParts = pathinfo($iPathRel);
		
		$result['xhdpi'] = "http://".$_SERVER['HTTP_HOST'].'/c5'.$iPathRel;
		$result['mdpi'] = "http://".$_SERVER['HTTP_HOST'].'/c5'.$iParts['dirname'].'/'.$iParts['filename'].'.'.$iParts['extension']."?s=mdpi";
		$result['hdpi'] = "http://".$_SERVER['HTTP_HOST'].'/c5'.$iParts['dirname'].'/'.$iParts['filename'].'.'.$iParts['extension']."?s=hdpi";
		
		return $result;
	}
	
	public function getQuizForProduct($product) {
		$pages = $product['page']->getCollectionChildrenArray(1);
		
		//probably at the end, so reverse it to speed up most
		array_reverse($pages);
		foreach($pages as $page) {
			$page = Page::getById($page);
			if ($page->getAttribute('isactive')!==0 && $page->vObj->ctHandle=="quiz") {
				return $page;
			}
		}
		return false;
	}
	
	public function getResourcesForProduct($product) {
		$p = $product['page'];
		$blocks = $p->getBlocks("Resources");
		$arr = array();
		$di = 1;
		foreach ($blocks as $b) {
			$i = $b->instance;
			//error_log(print_r($b,true));
			$f = File::getById($i->field_4_file_fID);
			$localeCode = "en-US";
			switch($i->field_5_select_value) {
				case 1: $localeCode = "en-US";break;
				case 2: $localeCode = "es-LA";break;
			}
			$type = "other";
			switch($field_3_select_value) {
				case 1: $type = "training_deck"; break;
				case 2: $type="brochure"; break;
				case 3: $type= "user_guide"; break;
				case 4: $type= "data_sheet"; break;
				case 5: $type= "spec_sheet"; break;
				case 6: $type= "matrix"; break;
				case 7: $type= "user_manual"; break;
				case 8: $type= "quick_start_guide"; break;
				case 9: $type= "other"; break;
			}
			
			$params = array(
								"isC5"=>true,
								"id"=>$i->bID,
								"productId"=>$p->cID,
								"isActive"=>$i->field_1_select_value,
								"isPublic"=>$i->field_2_select_value,
								"modifiedAt"=>$f->fDateAdded,
								"createdAt"=>$f->fDateAdded,
								"displayIndex"=>$di,
								"name"=>strip_tags(html_entity_decode($i->field_4_file_linkText)),
								"nameHtml"=>$i->field_4_file_linkText,
								"localeCode"=>$localeCode,
								"resourceType"=>$type,
								"src"=>"http://".$_SERVER['HTTP_HOST']."/c5".File::getRelativePathFromID($i->field_4_file_fID),
								//"src"=>"http://".$_SERVER['HTTP_HOST']."/c5".View::url('/download_file', $i->field_4_file_fID, $p->getCollectionID()),
								"contentLength"=>filesize($f->getPath()),
								"mimeType"=>mime_type($f->getPath())
								
							);
			$arr[]=new Model_Dto_Resource($params);
			$di++;
		}
		return $arr;
	}
	
}
$results = false;
?>
