<?php namespace Aquanode\Upstream;

/*----------------------------------------------------------------------------------------------------------
	Upstream
		A simple composer package that assists in file uploads and image resizing/cropping.
		Works great with jCrop and jquery-file-upload.

		created by Cody Jassman / Aquanode - http://aquanode.com
		last updated on March 10, 2013
----------------------------------------------------------------------------------------------------------*/

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

use SimpleResize as Resize;

class Upstream {

	public $config;
	public $returnData;

	/**
	 * Create an instance of Upstream with configuration settings (default and modified via array).
	 *
	 * @param  array    $id
	 * @return array
	 */
	public function __construct($config)
	{
		//default settings
		$this->config = array_merge(Config::get('upstream::upload'), $config);
	}

	//function called by the form
	public static function make($config = array())
	{
		return new Upstream($config);
	}

	public function upload($config = array())
	{
		//modify upload settings through a single config parameter
		if (!empty($config)) $this->config = array_merge($this->config, $config);

		//set file types config
		$fileTypes_img = false;
		if ($this->config['fileTypes'] != '*') {
			if (!is_array($this->config['fileTypes']) && $this->config['fileTypes'] == "image") {
				$fileTypes_img = true;
				$this->config['fileTypes'] = array('jpg', 'gif', 'png');
			}
			if (!is_array($this->config['fileTypes'])) $this->config['fileTypes'] = explode('|', $this->config['fileTypes']); // if file types is set as a string like "jpg|gif|png", explode it into an array

			//add JPEG if JPG is set as file type
			if (is_array($this->config['fileTypes']) && in_array('jpg', $this->config['fileTypes']) && !in_array('jpeg', $this->config['fileTypes'])) $this->config['fileTypes'][] = 'jpeg';
		}

		//format error triggers
		if ($this->config['maxFileSize'])  $this->config['maxFileSize'] = strtoupper(str_replace(' ', '', $this->config['maxFileSize']));
		if ($this->config['imgMinWidth'])  $this->config['imgMinWidth'] = str_replace('px', '', strtolower($this->config['imgMinWidth']));
		if ($this->config['imgMinHeight']) $this->config['imgMinHeight'] = str_replace('px', '', strtolower($this->config['imgMinHeight']));

		$this->returnData = array();
		$this->returnData[] = array('error'=> 'Something went wrong. Please try again.');

		if ($_FILES) {
			if (substr($this->config['path'], -1) != "/") $this->config['path'] .= "/"; //add trailing slash to path if it doesn't exist
			$originalFilename = ""; $originalFileExt = "";

			//create files array
			$files = array();
			foreach ($_FILES as $key => $fileInfo) {
				if (!empty($fileInfo)) {
					if (is_array($fileInfo['name'])) { //array of files exists rather than just a single file; loop through them
						for ($f=0; $f < count($fileInfo['name']); $f++) {
							$files[] = array('name'    => trim($fileInfo['name'][$f]),
										 	 'type'    => $fileInfo['type'][$f],
											 'tmpName' => $fileInfo['tmp_name'][$f],
											 'error'   => $fileInfo['error'][$f],
											 'size'    => $fileInfo['size'][$f],
											 'key'     => $key);
						}
					} else {
						$files[] = array('name'    => trim($fileInfo['name']),
										 'type'    => $fileInfo['type'],
										 'tmpName' => $fileInfo['tmp_name'],
										 'error'   => $fileInfo['error'],
										 'size'    => $fileInfo['size'],
										 'key'     => $key);
					}
				}
			}

			$f = 1;
			foreach ($files as $file) {
				$originalFilename = $file['name'];
				$originalFileExt = strtolower(File::extension($originalFilename));

				if (!$this->config['filename']) {
					$filename = static::filename($originalFilename);
				} else {
					if (in_array($this->config['filename'], array('LOWERCASE', 'UNDERSCORE', 'LOWERCASE-UNDERSCORE', 'RANDOM'))) {
						$filename = static::filename($originalFilename, $this->config['filename']);
					} else {
						$filename = static::filename($this->config['filename']);
					}
				}
				$fileExt = File::extension($filename);

				//if file extension doesn't exist, use original extension
				if ($fileExt == "") {
					$fileExt = $originalFileExt;
					$filename .= '.'.$fileExt;
				}

				//create directory if necessary
				if ($this->config['createDirectory'] && !is_dir($this->config['path'])) static::createDirectory($this->config['path']);

				//if file is an image
				$dimensions = array();
				if (in_array($originalFileExt, array('jpg', 'jpeg', 'gif', 'png'))) {
					$dimensions = static::imageSize($file['tmpName']);
				}

				//check for errors
				$error = false;

				//error check 1: file exists and overwrite not set
				if (is_file($this->config['path'].$filename)) {
					if ($this->config['overwrite']) { //delete existing file if it exists and overwrite is set
						unlink($this->config['path'].$filename);
					} else { //file exists but overwrite is not set; do not upload
						$error = 'A file already exists with the name specified ('.$filename.').';
					}
				}

				//error check 2: file type
				if (!$error && $this->config['fileTypes'] != '*' && is_array($this->config['fileTypes'])) {
					if (!in_array($originalFileExt, $this->config['fileTypes'])) {
						if ($fileTypes_img) {
							$error = 'You must upload a file in one of the following formats: '.implode(', ', $this->config['fileTypes']).'.';
						} else {
							$error = 'You must upload an image file.';
						}
					}
				}

				//error check 3: maximum file size
				if (!$error && $this->config['maxFileSize']) {
					$maxFileSize = $this->config['maxFileSize'];
					if (substr($this->config['maxFileSize'], -2) == "KB") {
						$maxFileSize_bytes = str_replace('KB', '', $this->config['maxFileSize']) * 1024;
					} else if (substr($this->config['maxFileSize'], -2) == "MB") {
						$maxFileSize_bytes = str_replace('MB', '', $this->config['maxFileSize']) * 1024 * 1024;
					} else {
						$maxFileSize_bytes = str_replace('B', '', $this->config['maxFileSize']);
						$maxFileSize = $this->config['maxFileSize'].'B';
					}
					if ($file['size'] > $maxFileSize_bytes) {
						$error = 'Your file must not exceed '.$maxFileSize.'.';
					}
				}

				//error check 4: minimum image dimensions
				if (!$error && in_array($originalFileExt, array('jpg', 'jpeg', 'gif', 'png')) && !empty($dimensions) && ($this->config['imgMinWidth'] || $this->config['imgMinHeight'])) {
					$errorWidth = false; $errorHeight = false;
					if ($this->config['imgMinWidth'] && $dimensions['w'] < $this->config['imgMinWidth'])	$errorWidth = true;
					if ($this->config['imgMinHeight'] && $dimensions['h'] < $this->config['imgMinHeight'])	$errorHeight = true;

					if ($errorWidth || $errorHeight) {
						if ($this->config['imgMinWidth'] && $this->config['imgMinHeight']) {
							$error = 'Your image must be at least '.$this->config['imgMinWidth'].' x '.$this->config['imgMinHeight'].'. ';
						} else if ($this->config['imgMinWidth']) {
							$error = 'Your image must be at least '.$this->config['imgMinWidth'].' pixels in width.';
						} else if ($this->config['imgMinHeight']) {
							$error = 'Your image must be at least '.$this->config['imgMinHeight'].' pixels in height.';
						}
						$error .=	'Your uploaded image dimensions were '.$dimensions['w'].' x '.$dimensions['h'].'.';
					}
				}

				//error check 5: maximum image dimensions
				$maxWidthExceeded = false; $maxHeightExceeded = false;
				if (!$error && in_array($originalFileExt, array('jpg', 'jpeg', 'gif', 'png')) && !empty($dimensions) && ($this->config['imgMaxWidth'] || $this->config['imgMaxHeight'])) {
					if ($this->config['imgMaxWidth'] && $dimensions['w'] > $this->config['imgMaxWidth'])	$maxWidthExceeded = true;
					if ($this->config['imgMaxHeight'] && $dimensions['h'] > $this->config['imgMaxHeight'])	$maxHeightExceeded = true;

					if (!$this->config['imgResizeMax'] && ($maxWidthExceeded || $maxHeightExceeded)) {
						if ($this->config['imgMaxWidth'] && $this->config['imgMaxHeight']) {
							$error = 'Your image must be '.$this->config['imgMaxWidth'].' x '.$this->config['imgMaxHeight'].' or less. ';
						} else if ($this->config['imgMaxWidth']) {
							$error = 'Your image must be '.$this->config['imgMaxWidth'].' pixels in width or less.';
						} else if ($this->config['imgMinHeight']) {
							$error = 'Your image must be '.$this->config['imgMaxHeight'].' pixels in height or less.';
						}
						$error .=	'Your uploaded image dimensions were '.$dimensions['w'].' x '.$dimensions['h'].'.';
					}
				}

				if (!$error) {
					//upload file to selected directory
					if (move_uploaded_file($file['tmpName'], $this->config['path'].$filename)) {

						//resize image if necessary
						if (in_array($originalFileExt, array('jpg', 'jpeg', 'gif', 'png'))) {
							if ($this->config['imgResize'] || ($this->config['imgResizeMax'] && ($maxWidthExceeded || $maxHeightExceeded))) {

								//configure resized image dimensions
								$resizeType = $this->config['imgResizeDefaultType'];
								if ($this->config['imgCrop']) $resizeType = "crop";
								if ($this->config['imgResize']) {
									$resizeDimensions = array('w' => $this->config['imgDimensions']['w'],
															  'h' => $this->config['imgDimensions']['h']);
								
								} else {
									if ($maxWidthExceeded && $maxHeightExceeded) {
										$resizeDimensions = array('w' => $this->config['imgMaxWidth'],
																  'h' => $this->config['imgMaxHeight']);
									} else if ($maxWidthExceeded) {
										$resizeDimensions = array('w' => $this->config['imgMaxWidth'],
																  'h' => false);
										$resizeType = 'landscape';
									} else if ($maxHeightExceeded) {
										$resizeDimensions = array('w' => false,
																  'h' => $this->config['imgMaxHeight']);
										$resizeType = 'portrait';
									}
								}

								//resize image with SimpleResize
								$resize = new Resize($this->config['path'].$filename);
								$resize->resizeImage($resizeDimensions['w'], $resizeDimensions['h'], $resizeType);
								$resize->saveImage($this->config['path'].$filename, $this->config['imgResizeQuality']);
							}

							//create thumbnail image if necessary
							if ($this->config['imgThumb']) {
								$resizeDimensions = array('w'=> $this->config['imgDimensions']['tw'],
														   'h'=> $this->config['imgDimensions']['th']);

								$thumbsPath = $this->config['path'].'thumbs/';
								if ($this->config['createDirectory'] && !is_dir($thumbsPath)) static::createDirectory($thumbsPath);
								if (is_dir($thumbsPath)) {
									//resize image with SimpleResize
									$resize = new Resize($this->config['path'].$filename);
									$resize->resizeImage($resizeDimensions['w'], $resizeDimensions['h'], 'crop');
									$resize->saveImage($thumbsPath.$filename, $this->config['imgResizeQuality']);
								}
							}
						}

						//set URL for return data
						$url = URL::to(str_replace('public/', '', $this->config['path'].$filename));
						if ($this->config['noCacheUrl']) $url .= '?'.rand(1, 99999);

						//set thumbnail image for return data
						if ($this->config['imgThumb']) {
							$thumbnailUrl = URL::to(str_replace('public/', '', $this->config['path'].'thumbs/'.$filename));
							if ($this->config['noCacheUrl']) $thumbnailUrl .= '?'.rand(1, 99999);
						} else {
							$thumbnailUrl = $this->config['defaultThumb'];
						}

						//set name for return data
						if ($this->config['displayName'] && is_string($this->config['displayName'])) {
							$displayName = $this->config['displayName'];
						} else {
							$displayName = $filename;
						}

						$this->returnData[($f - 1)] = 	array(
							'name'         => $displayName,
							'filename'     => $filename,
							'path'         => $this->config['path'],
							'url'          => $url,
							'fileSize'     => $file['size'],
							'fileType'     => $originalFileExt,
							'isImage'      => in_array($originalFileExt, array('png', 'jpg', 'jpeg', 'gif')) ? true:false,
							'thumbnailUrl' => $thumbnailUrl,
							'deleteUrl'    => '', //some of these variables are added as dummy variables to allow it to work with jquery-file-upload out of the box
							'deleteType'   => 'DELETE',
							'error'        => false
						);
					} else {
						$this->returnData[($f - 1)]['error'] = 'Something went wrong. Please try again.';
					}
				} else {
					$this->returnData[($f - 1)]['error'] = $error;
				}
				$f ++;
			} //end foreach files

			//return result
			if ($this->config['returnJson']) {
				return json_encode($this->returnData);
			} else {
				return $this->returnData;
			}
		}
	}

	public static function cropImage($configCrop = array())
	{
		$config = array_merge(Config::get('upstream::crop'), $configCrop);

		$returnData = array('error'=> 'Something went wrong. Please try again.');

		$path = $config['path'];
		$originalFilename = $config['filename'];
		$originalFileExt = File::extension($config['filename']);

		//error check 1: file not found
		if (!is_file($path.$originalFilename)) {
			$returnData['error'] = 'The file you specified was not found ('.$originalFilename.').';
			return $returnData;
		}

		//error check 2: file is not an image
		if (!in_array($originalFileExt, array('jpg', 'jpeg', 'gif', 'png'))) {
			$returnData['error'] = 'The file you specified was not an image ('.$originalFilename.').';
			return $returnData;
		}

		if (!$config['newPath'])		$config['newPath'] = $config['path'];
		if (!$config['newFilename'])	$config['newFilename'] = $config['filename'];

		$newPath = $config['newPath'];

		if (!$config['newFilename']) {
			$filename = static::filename($config['filename']);
		} else {
			$filename = static::filename($config['newFilename']);
		}
		$fileExt = File::extension($filename);

		//if file extension doesn't exist, use original extension
		if ($fileExt == "") {
			$fileExt = $originalFileExt;
			$filename .= '.'.$fileExt;
		}

		//create image data from image file depending on file type
		$fileType = "";
		if (in_array($originalFileExt, array('jpg', 'jpeg'))) {
			$img_original = imagecreatefromjpeg($path.$originalFilename);
			$fileType = "jpg";
		} else if ($originalFileExt == "gif") {
			$img_original = imagecreatefromgif($path.$originalFilename);
			$fileType = "gif";
		} else if ($originalFileExt == "png") {
			$img_original = imagecreatefrompng($path.$originalFilename);
			$fileType = "png";
		}

		if (isset($img_original)) {
			//error check 3: file exists and overwrite not set
			if (is_file($newPath.$filename)) {
				if ($config['overwrite']) { //delete existing file if it exists and overwrite is set
					unlink($newPath.$filename);
				} else {
					$returnData['error'] = 'A file already exists with the name specified ('.$filename.').';
					return $returnData;
				}
			}

			if (!is_dir($config['newPath'])) {
				if ($config['createDirectory']) {
					static::createDirectory($config['newPath']);
				} else {
					$returnData['error'] = 'The directory you specified does not exist ('.$config['newPath'].').';
					return $returnData;
				}
			}

			//crop image
			$imgCropped = imagecreatetruecolor($config['imgDimensions']['w'], $config['imgDimensions']['h']);
			imagecopyresampled($imgCropped, $img_original, 0, 0,
							   $config['cropPosition']['x'], $config['cropPosition']['y'],
							   $config['imgDimensions']['w'], $config['imgDimensions']['h'],
							   $config['cropPosition']['w'], $config['cropPosition']['h']);

			//save cropped image to file
			if ($fileType == "jpg") {
				imagejpeg($imgCropped, $newPath.$filename, 72);
			} else if ($fileType == "gif") {
				imagegif($imgCropped, $newPath.$filename);
			} else if ($fileType == "png") {
				imagepng($imgCropped, $newPath.$filename, 72);
			}
			$returnData['error'] = false;
			$returnData['name'] = $filename;
			$returnData['path'] = $newPath;
		}
		return $returnData;
	}

	public static function filename($filename, $filenameModifier = false, $suffix = false)
	{
		$fileExt = File::extension($filename);

		$newFilename = strtr($filename, 'ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 'AAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
		$filename = preg_replace('/([^.a-z0-9]+)/i', '_', $filename); //replace characters other than letters, numbers and . by _

		//get filename
		if ($filenameModifier == "LOWERCASE") {
			$newFilename = strtolower($newFilename);
		} else if ($filenameModifier == "UNDERSCORE") {
			$newFilename = str_replace(' ', '_', str_replace('-', '_', $newFilename));
		} else if ($filenameModifier == "LOWERCASE-UNDERSCORE") {
			$newFilename = strtolower(str_replace(' ', '_', str_replace('-', '_', $newFilename)));
		} else if ($filenameModifier == "RANDOM") {
			$newFilename = substr(md5(rand(1, 9999999)), 0, 10);
		} else {
			if ($suffix && $suffix != "") { //append suffix if it is set
				$fileExt = File::extension($newFilename);
				$newFilename = str_replace('.'.$fileExt, '', $newFilename); //remove extension
				$newFilename .= '_'.$suffix.'.'.$fileExt;
			}
		}

		//get file extension
		$newFileExt = File::extension($newFilename);
		if ($fileExt == "ext") $newFileExt = $fileExt;
		$addExt = false;
		if ($newFileExt == "") {
			$newFileExt = $fileExt;
			$addExt = true;
		}
		if ($newFileExt == "jpeg") $newFileExt = "jpg";
		if ($addExt && $newFileExt != "") $filename .= '.'.$newFileExt;
		$newFilename = str_replace('.ext', '.'.$fileExt, $newFilename); //replace .ext with original file extension

		return $newFilename;
	}

	public static function dirFiles($path = false, $config = array())
	{
		if (!$path) {
			$configDefault = Config::get('upstream::upload');
			$path = $configDefault['path'];
		}

		if (!isset($config['deleteURL']))     $config['deleteURL'] = "";
		if (!isset($config['fileTypeOrder'])) $config['fileTypeOrder'] = false;

		$result = array();
		if (is_dir($path)) {
			if (substr($path, -1) != "/") $path .= "/";
			if ($handle = opendir($path)) {
				if ($config['fileTypeOrder']) {
					$files = glob($path.'*.{'.implode(',', $config['fileTypeOrder']).'}', GLOB_BRACE);
				} else {
					$files = scandir($path);
				}
				foreach ($files as $entry) {
					if ($config['fileTypeOrder']) $entry = str_replace($path, '', $entry); //if glob, remove path from filename
					if (is_file($path.$entry)) {
						$filename = $entry;
						$fileExt = File::extension($filename);

						$deleteFullURL = $config['deleteURL'];
						if ($config['deleteURL'] != "") $deleteFullURL .= "/".str_replace('.', '_', $filename);

						$file = array('name'       => $filename,
									  'url'        => URL::to($path.$filename),
									  'fileSize'   => filesize($path.$filename),
									  'fileType'   => filetype($path.$filename),
									  'isImage'    => in_array($fileExt, array('png', 'jpg', 'jpeg', 'gif')) ? true:false,
									  'deleteURL'  => $deleteFullURL,
									  'deleteType' => 'DELETE',
									  'error'      => false);
						if ($file['isImage'] && is_file($path.'thumbs/'.$filename)) {
							$file['thumbnailUrl'] = URL::to($path.'thumbs/'.$filename);
						} else {
							$file['thumbnailUrl'] = "";
						}
						$result[] = $file;
					}
				}
			}
		}
		return $result;
	}

	public static function dirFilenames($path = '', $config = array())
	{
		if (!isset($config['fileTypeOrder'])) $config['fileTypeOrder'] = false;

		$files = array();
		if (substr($path, -1) != "/") $path .= "/";
		if (is_dir($path) && $handle = opendir($path)) {
			if ($config['fileTypeOrder']) {
				$files_list = glob($path.'*.{'.implode(',', $config['fileTypeOrder']).'}', GLOB_BRACE);
			} else {
				$files_list = scandir($path);
			}
			foreach ($files_list as $entry) {
				$entry = str_replace($path, '', $entry); //if glob, remove path from filename
				if (is_file($path.$entry)) $files[] = $entry;
			}
		}
		if (isset($config['returnJson']) && $config['returnJson']) {
			return json_encode($files);
		} else if (isset($config['returnStr']) && $config['returnStr']) {
			return implode(', ', $files);
		} else {
			return $files;
		}
	}

	public static function uriToFilename($uri = '')
	{
		$sections = explode('_', $uri); $filename = "";
		$last = count($sections) - 1;
		for ($s=0; $s < $last; $s++) {
			if ($filename != "") $filename .= "_";
			$filename .= $sections[$s];
		}
		$filename .= ".".$sections[$last];
		return $filename;
	}

	public static function createDirectory($path)
	{
		$pathArray = explode('/', $path);
		$pathPartial = "";
		$directoriesCreated = 0;
		for ($p=0; $p < count($pathArray); $p++) {
			if ($pathArray[$p] != "") {
				if ($pathPartial != "") $pathPartial .= "/";
				$pathPartial .= $pathArray[$p];
				if (!is_dir($pathPartial)) {
					mkdir($pathPartial);
					$directoriesCreated ++;
				}
			}
		}
		return $directoriesCreated;
	}

	public static function imageSize($image)
	{
		if (is_file($image)) {
			$img = getimagesize($image);
			return array('w'=>	$img[0],
						 'h'=>	$img[1]);
		} else {
			return array('w'=>	0,
						 'h'=>	0);
		}
	}

	public static function fileSize($file, $convert = true)
	{
		if (is_file($file)) {
			$fileSize = filesize($file);

			if ($convert) {
				return $this->convertFileSize($fileSize);
			} else {
				return $fileSize;
			}
		} else {
			if ($convert) {
				return '0.00 KB';
			} else {
				return 0;
			}
		}
	}

	public static function convertFileSize($fileSize)
	{
		if ($fileSize < 1024) {
			return $fileSize .' B';
		} else if ($fileSize < 1048576) {
			return round($fileSize / 1024, 2) .' KB';
		} else if ($fileSize < 1073741824) {
			return round($fileSize / 1048576, 2) . ' MB';
		} else if ($fileSize < 1099511627776) {
			return round($fileSize / 1073741824, 2) . ' GB';
		} else if ($fileSize < 1125899906842624) {
			return round($fileSize / 1099511627776, 2) .' TB';
		} else {
			return round($fileSize / 1125899906842624, 2) .' PB';
		}
	}

	//function delete file
	public function delete($file) {
		$this->returnData = array('success' => false);
		if (is_file($file)) $this->returnData['success']  = unlink($file);
		$fileExt = File::extension($file);

		//delete thumbnail image if it exists
		if (in_array($fileExt, array('png', 'jpg', 'gif'))) {
			$pathArray = explode('/', $file);
			$path = "";
			$last = count($pathArray) - 1;
			for ($p=0; $p < $last; $p++) {
				if ($path != "") $path .= "/";
				$path .= $pathArray[$p];
			}
			if (is_file($path.'/thumbs/'.$pathArray[$p])) unlink($path.'/thumbs/'.$pathArray[$p]);
		}

		if (IS_AJAX) {
			return json_encode($this->returnData);
		} else {
			return $this->returnData;
		}
	}

	//load the files
	public function getFiles()
	{
		$this->getScanFiles();
	}

	//get info and scan the directory
	public function getScanFiles()
	{
		$fileName = isset($_REQUEST['file']) ?
		basename(stripslashes($_REQUEST['file'])) : null;
		if ($fileName) {
			$info = $this->getFileObject($fileName);
		} else {
			$info = $this->getFileObjects();
		}
		header('Content-type: application/json');
		echo json_encode($info);
	}

	protected function getFileObject($fileName)
	{
		$file_path = $this->getPathImgUploadFolder() . $fileName;
		if (is_file($file_path) && $fileName[0] !== '.') {

			$file = new stdClass();
			$file->name = $fileName;
			$file->size = filesize($file_path);
			$file->url = $this->get_path_url_img_upload_folder() . rawurlencode($file->name);
			$file->thumbnailURL = $this->get_path_url_imgThumb_upload_folder() . rawurlencode($file->name);
			//File name in the url to delete
			$file->deleteURL = $this->get_delete_img_url() . rawurlencode($file->name);
			$file->deleteType = 'DELETE';

			return $file;
		}
		return null;
	}

	//scan
	protected function getFileObjects()
	{
		return array_values(array_filter(array_map(array($this, 'getFileObject'), scandir($this->getPathImgUploadFolder()))));
	}

	public function setAllowedTypes($types)
	{
		if (!is_array($types) && $types != '*') $types = explode('|', $types);
		$this->allowed_types = $types;
	}

	public function dirFileLimits($directory = '', $limits = array())
	{
		if (substr($directory, -1) != "/") $directory .= "/"; //add trailing slash to directory if it doesn't exist
		$deletedFiles = array();

		if (is_dir($directory) && $handle = opendir($directory)) {
			foreach ($limits as $types=>$limit) {

				if ($types == "image")  $types = array('jpg', 'gif', 'png');
				if ($types == "vector") $types = array('ai', 'eps', 'svg');
				if (!is_array($types))  $types = explode('|', $types);

				$filesForType = array();
				$quantity = 0;

				while (false !== ($entry = readdir($handle))) {
					if (is_file($directory.$entry)) {
						$fileExt = File::extension($entry);
						if ($fileExt) {
							if (in_array(strtolower($fileExt), $types) && !in_array($directory.$entry, $filesForType)) {
								$filesForType[] = $directory.$entry;
								$quantity ++;
							}
						} //end if file extension exists (entry is not a directory)
					}
				} //end foreach file in directory

				while ($quantity > $limit) { //if there are too many files of filetype being checked delete until at limit starting with oldest files
					$oldestFile = -1;
					foreach ($filesForType as $index => $file) {
						if ($oldestFile == -1) {
							$oldestFile = $index;
						} else {
							if (filemtime($file) < filemtime($filesForType[$oldestFile])) $oldestFile = $index;
						}
					}

					if (isset($filesForType[$oldestFile]) && is_file($filesForType[$oldestFile])) {
						unlink($filesForType[$oldestFile]);
						$deletedFiles[] = $filesForType[$oldestFile];
						unset($filesForType[$oldestFile]);
						$quantity --;
					}
				} //end while quantity > limit
				rewinddir($handle);
			} //end foreach limits
		} //end if directory can be opened

		return $deletedFiles;
	}

}