<?php namespace Aquanode\Upstream;

/*----------------------------------------------------------------------------------------------------------
	Upstream
		A simple composer package that assists in file uploads and image resizing/cropping, as well as some
		other file management functions. Works great with jCrop and jquery-file-upload.

		created by Cody Jassman / Aquanode - http://aquanode.com
		last updated on July 25, 2014
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
	public function __construct($config = array())
	{
		//default settings
		$this->config = array_merge(Config::get('upstream::upload'), $config);
	}

	/**
	 * Method for instantiating Upstream.
	 *
	 * @param  array    $config
	 * @return Upstream
	 */
	public function make($config = array())
	{
		return new static($config);
	}

	/**
	 * Upload files based on configuration.
	 *
	 * @param  array    $config
	 * @return array
	 */
	public function upload($config = array())
	{
		//modify upload settings through a single config parameter
		if (!empty($config)) $this->config = array_merge($this->config, $config);

		//set file types config
		if ($this->config['fileTypes'] != '*') {
			if ($this->config['fileTypes'] == "image" || $this->config['fileTypes'] == "images") {
				$fileTypesImg = true;
			} else {
				$fileTypesImg = false;
			}

			$this->config['fileTypes'] = $this->formatFileTypesList($this->config['fileTypes']);
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
			foreach ($_FILES as $field => $filesInfo) {
				if (!empty($filesInfo)) {

					//check if field is set to be uploaded in "fields" configuration
					if ((is_string($this->config['fields']) && $this->config['fields'] == $field)
					|| (is_array($this->config['fields']) && in_array($field, $this->config['fields']))
					|| (is_bool($this->config['fields']) && $this->config['fields'])) {
						$uploadFile = true;
					} else {
						$uploadFile = false;
					}

					if ($uploadFile && isset($filesInfo['name'])) {
						if (is_array($filesInfo['name'])) { //array of files exists rather than just a single file; loop through them
							$keys = array_keys($filesInfo['name']);
							foreach ($keys as $key) {
								$files[] = array(
									'name'    => trim($filesInfo['name'][$key]),
									'type'    => $filesInfo['type'][$key],
									'tmpName' => $filesInfo['tmp_name'][$key],
									'error'   => $filesInfo['error'][$key],
									'size'    => $filesInfo['size'][$key],
									'field'   => $field,
									'key'     => $key,
								);
							}
						} else {
							$fileInfo = $filesInfo;
							$files[]  = array(
								'name'    => trim($fileInfo['name']),
								'type'    => $fileInfo['type'],
								'tmpName' => $fileInfo['tmp_name'],
								'error'   => $fileInfo['error'],
								'size'    => $fileInfo['size'],
								'field'   => $field,
								'key'     => 0,
							);
						}
					}
				}
			}

			$f = 1;
			foreach ($files as $file) {
				$originalFilename = $file['name'];
				$originalFileExt  = strtolower(File::extension($originalFilename));

				if (!$this->config['filename']) {
					$filename = $this->filename($originalFilename);
				} else {
					if (in_array($this->config['filename'], array('[LOWERCASE]', '[UNDERSCORE]', '[LOWERCASE-UNDERSCORE]', '[RANDOM]')))
						$filename = $this->filename($originalFilename, $this->config['filename']);
					else
						$filename = $this->filename($this->config['filename']);
				}
				$filename = str_replace('[KEY]', $file['key'], $filename);
				$fileExt  = File::extension($filename);

				//if file extension doesn't exist, use original extension
				if ($fileExt == "") {
					$fileExt   = $originalFileExt;
					$filename .= '.'.$fileExt;
				}

				//create directory if necessary
				if ($this->config['createDirectory'] && !is_dir($this->config['path'])) $this->createDirectory($this->config['path']);

				//get image dimensions if file is an image
				$dimensions = array();
				if (in_array($originalFileExt, array('jpg', 'jpeg', 'gif', 'png'))) {
					$dimensions = $this->imageSize($file['tmpName']);
				}

				//check for errors
				$error           = false;
				$attemptedUpload = false;

				if ($file['name'] != "")
					$attemptedUpload = true;

				//error check 1: file exists and overwrite not set
				if (is_file($this->config['path'].$filename)) {
					if ($this->config['overwrite']) //delete existing file if it exists and overwrite is set
						unlink($this->config['path'].$filename);
					else //file exists but overwrite is not set; do not upload
						$error = 'A file already exists with the name specified ('.$filename.').';
				}

				//error check 2: file type
				if (!$error && $this->config['fileTypes'] != '*' && is_array($this->config['fileTypes'])) {
					if ($file['name'] != "") {
						if (!in_array($originalFileExt, $this->config['fileTypes'])) {
							if ($fileTypesImg)
								$error = 'You must upload an image file.';
							else
								$error = 'You must upload a file in one of the following formats: '.implode(', ', $this->config['fileTypes']).'. ('.$file['name'].')';
						}
					} else {
						if ($fileTypesImg)
							$error = 'You must upload an image.';
						else
							$error = 'You must upload a file.';
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

						$error .= 'Your uploaded image dimensions were '.$dimensions['w'].' x '.$dimensions['h'].'.';
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

						$error .= 'Your uploaded image dimensions were '.$dimensions['w'].' x '.$dimensions['h'].'.';
					}
				}

				if (!$error) {
					//upload file to selected directory
					if (move_uploaded_file($file['tmpName'], $this->config['path'].$filename)) {

						//resize image if necessary
						if (in_array($originalFileExt, array('jpg', 'jpeg', 'gif', 'png'))) {
							if ($this->config['imgResize'] || ($this->config['imgResizeMax'] && ($maxWidthExceeded || $maxHeightExceeded)))
							{
								//configure resized image dimensions
								$resizeType = $this->config['imgResizeDefaultType'];
								if ($this->config['imgCrop']) $resizeType = "crop";
								if ($this->config['imgResize']) {
									$resizeDimensions = array(
										'w' => $this->config['imgDimensions']['w'],
										'h' => $this->config['imgDimensions']['h'],
									);
								} else {
									if ($maxWidthExceeded && $maxHeightExceeded) {
										$resizeDimensions = array(
											'w' => $this->config['imgMaxWidth'],
											'h' => $this->config['imgMaxHeight'],
										);
									} else if ($maxWidthExceeded) {
										$resizeDimensions = array(
											'w' => $this->config['imgMaxWidth'],
											'h' => false,
										);
										$resizeType = 'landscape';
									} else if ($maxHeightExceeded) {
										$resizeDimensions = array(
											'w' => false,
											'h' => $this->config['imgMaxHeight'],
										);
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
								$resizeDimensions = array(
									'w' => $this->config['imgDimensions']['tw'],
									'h' => $this->config['imgDimensions']['th'],
								);

								$thumbsPath = $this->config['path'].'thumbnails/';
								if ($this->config['createDirectory'] && !is_dir($thumbsPath)) $this->createDirectory($thumbsPath);
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
							$thumbnailURL = URL::to(str_replace('public/', '', $this->config['path'].'thumbs/'.$filename));
							if ($this->config['noCacheUrl']) $thumbnailURL .= '?'.rand(1, 99999);
						} else {
							$thumbnailURL = $this->config['defaultThumb'];
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
							'thumbnailURL' => $thumbnailURL,
							'deleteURL'    => '', //some of these variables are added as dummy variables to allow it to work with jquery-file-upload out of the box
							'deleteType'   => 'DELETE',
							'error'        => false,
						);
					} else {
						$this->returnData[($f - 1)]['error'] = 'Something went wrong. Please try again.';
					}
				} else {
					$this->returnData[($f - 1)]['error'] = $error;
				}

				$this->returnData[($f - 1)]['field']     = $file['field'];
				$this->returnData[($f - 1)]['key']       = $file['key'];
				$this->returnData[($f - 1)]['attempted'] = $attemptedUpload;

				$f ++;
			} //end foreach files

			//if there is only one file being uploaded, return index 0 of the results array
			if (is_string($this->config['fields']) && count($files) <= 1)
				$this->returnData = $this->returnData[0];

			//return result
			if ($this->config['returnJson'])
				return json_encode($this->returnData);
			else
				return $this->returnData;
		}
	}

	/**
	 * Crop images based on configuration.
	 *
	 * @param  array    $config
	 * @return array
	 */
	public function cropImage($config = array())
	{
		$config = array_merge(Config::get('upstream::crop'), $config);

		$returnData = array('error'=> 'Something went wrong. Please try again.');

		$path = $config['path'];

		$originalFilename = $config['filename'];
		$originalFileExt  = File::extension($config['filename']);

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

		if (!$config['newPath'])     $config['newPath']     = $config['path'];
		if (!$config['newFilename']) $config['newFilename'] = $config['filename'];

		$newPath = $config['newPath'];

		if (!$config['newFilename'])
			$filename = $this->filename($config['filename']);
		else
			$filename = $this->filename($config['newFilename']);

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
					$this->createDirectory($config['newPath']);
				} else {
					$returnData['error'] = 'The directory you specified does not exist ('.$config['newPath'].').';
					return $returnData;
				}
			}

			//crop image
			$imgCropped = imagecreatetruecolor($config['imgDimensions']['w'], $config['imgDimensions']['h']);

			imagecopyresampled(
				$imgCropped, $img_original, 0, 0,
				$config['cropPosition']['x'],  $config['cropPosition']['y'],
				$config['imgDimensions']['w'], $config['imgDimensions']['h'],
				$config['cropPosition']['w'],  $config['cropPosition']['h']
			);

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

	/**
	 * Set a filename for an uploaded file.
	 *
	 * @param  string   $filename
	 * @param  mixed    $filenameModifier
	 * @param  boolean  $suffix
	 * @return string
	 */
	public function filename($filename, $filenameModifier = false, $suffix = false)
	{
		$fileExt = File::extension($filename);

		$newFilename = strtr($filename, 'ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 'AAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
		$filename    = preg_replace('/([^.a-z0-9]+)/i', '_', $filename); //replace characters other than letters, numbers and . by _

		//get filename
		if ($filenameModifier == "[LOWERCASE]") {
			$newFilename = strtolower($newFilename);
		} else if ($filenameModifier == "[UNDERSCORE]") {
			$newFilename = str_replace(' ', '_', str_replace('-', '_', $newFilename));
		} else if ($filenameModifier == "[LOWERCASE-UNDERSCORE]") {
			$newFilename = strtolower(str_replace(' ', '_', str_replace('-', '_', $newFilename)));
		} else if ($filenameModifier == "[RANDOM]") {
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
		if ($fileExt == "ext")
			$newFileExt = $fileExt;

		$addExt = false;

		if ($newFileExt == "") {
			$newFileExt = $fileExt;
			$addExt     = true;
		}

		if ($newFileExt == "jpeg")
			$newFileExt = "jpg";

		if ($addExt && $newFileExt != "")
			$filename .= '.'.$newFileExt;

		$newFilename = str_replace('.ext', '.'.$fileExt, $newFilename); //replace .ext with original file extension

		return $newFilename;
	}

	/**
	 * Get the files in a directory.
	 *
	 * @param  mixed    $path
	 * @param  array    $config
	 * @return array
	 */
	public function dirFiles($path = false, $config = array())
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

					$config['fileTypeOrder'] = $this->formatFileTypesList($config['fileTypeOrder']);

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
						if ($config['deleteURL'] != "") $deleteFullURL .= "/".str_replace(' ', '__', $filename);

						$file = array(
							'name'       => $filename,
							'url'        => URL::to($path.$filename),
							'fileSize'   => filesize($path.$filename),
							'fileType'   => filetype($path.$filename),
							'isImage'    => $this->isImage($filename),
							'deleteURL'  => $deleteFullURL,
							'deleteType' => 'DELETE',
							'error'      => false,
						);

						if ($file['isImage'] && is_file($path.'thumbs/'.$filename))
							$file['thumbnailURL'] = URL::to($path.'thumbs/'.$filename);
						else
							$file['thumbnailURL'] = "";

						$result[] = $file;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Get the filenames in a directory.
	 *
	 * @param  mixed    $path
	 * @param  array    $config
	 * @return array
	 */
	public function dirFilenames($path = '', $config = array())
	{
		if (!isset($config['fileTypeOrder'])) $config['fileTypeOrder'] = false;

		$files = array();

		if (substr($path, -1) != "/")
			$path .= "/";

		if (is_dir($path) && $handle = opendir($path)) {
			if ($config['fileTypeOrder']) {

				$config['fileTypeOrder'] = $this->formatFileTypesList($config['fileTypeOrder']);

				$files_list = glob($path.'*.{'.implode(',', $config['fileTypeOrder']).'}', GLOB_BRACE);
			} else {
				$files_list = scandir($path);
			}

			foreach ($files_list as $entry) {
				$entry = str_replace($path, '', $entry); //if glob, remove path from filename
				if (is_file($path.$entry)) $files[] = $entry;
			}
		}

		if (isset($config['returnJson']) && $config['returnJson'])
			return json_encode($files);
		else if (isset($config['returnStr']) && $config['returnStr'])
			return implode(', ', $files);
		else
			return $files;
	}

	/**
	 * Create arrays of file types to remove (file type categories), and file types to add (from file type categories).
	 *
	 * @param  array    $fileTypes
	 * @return array
	 */
	public function formatFileTypesList($fileTypes = array())
	{
		$fileTypesFormatted = array();

		if (!is_array($fileTypes))
			$fileTypes = explode('|', $fileTypes);

		$fileTypeCategories = Config::get('upstream::fileTypeCategories');
		for ($t=0; $t < count($fileTypes); $t++) {
			$category = false;
			foreach ($fileTypeCategories as $fileTypeCategory => $fileTypesForCategory) {
				if ($fileTypes[$t] == $fileTypeCategory) {
					$category = true;
					foreach ($fileTypesForCategory as $fileType) {
						$fileTypesFormatted[] = $fileType;
					}
				}
			}

			if (!$category) {
				$fileTypesFormatted[] = $fileTypes[$t];
			}
		}

		return $fileTypesFormatted;
	}

	/**
	 * Convert a URL-friendly filename to the actual filename.
	 *
	 * @param  string   $uri
	 * @return string
	 */
	public function uriToFilename($uri = '')
	{
		$sections = explode('_', $uri); $filename = "";
		$last     = count($sections) - 1;

		for ($s=0; $s < $last; $s++) {
			if ($filename != "")
				$filename .= "_";

			$filename .= $sections[$s];
		}

		$filename .= ".".$sections[$last];

		return $filename;
	}

	/**
	 * Convert a URL-friendly filename to the actual filename.
	 *
	 * @param  string   $path
	 * @param  integer  $permissions
	 * @return integer
	 */
	public function createDirectory($path, $permissions = 0777)
	{
		$pathArray          = explode('/', $path);
		$pathPartial        = "";
		$directoriesCreated = 0;

		for ($p=0; $p < count($pathArray); $p++) {
			if ($pathArray[$p] != "") {
				if ($pathPartial != "")
					$pathPartial .= "/";

				$pathPartial .= $pathArray[$p];

				if (!is_dir($pathPartial)) {
					mkdir($pathPartial);
					chmod($pathPartial, sprintf('%04d', $permissions));
					$directoriesCreated ++;
				}
			}
		}

		return $directoriesCreated;
	}

	/**
	 * Create an array of image dimensions for a specified image path.
	 *
	 * @param  string   $image
	 * @return array
	 */
	public function imageSize($image)
	{
		if (is_file($image)) {
			$img = getimagesize($image);
			return array(
				'w' => $img[0],
				'h' => $img[1],
			);
		} else {
			return array(
				'w' => 0,
				'h' => 0,
			);
		}
	}

	/**
	 * Get the file size of a specified file.
	 *
	 * @param  string   $file
	 * @param  boolean  $convert
	 * @return mixed
	 */
	public function fileSize($file, $convert = true)
	{
		if (is_file($file)) {
			$fileSize = filesize($file);

			if ($convert)
				return $this->convertFileSize($fileSize);
			else
				return $fileSize;

		} else {
			if ($convert)
				return '0.00 KB';
			else
				return 0;
		}
	}

	/**
	 * Convert a file size in bytes to the most logical units.
	 *
	 * @param  integer  $fileSize
	 * @return string
	 */
	public function convertFileSize($fileSize)
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

	/**
	 * Delete a file.
	 *
	 * @param  string   $file
	 * @return boolean
	 */
	public function deleteFile($file)
	{
		$success = false;

		$file = str_replace('__', ' ', $file);
		if (is_file($file))
			$success = unlink($file);

		$fileExt = File::extension($file);

		//delete thumbnail image if it exists
		if (in_array($fileExt, array('jpg', 'jpeg', 'png', 'gif'))) {
			$pathArray = explode('/', $file);
			$path      = "";
			$last      = count($pathArray) - 1;

			for ($p=0; $p < $last; $p++) {
				if ($path != "")
					$path .= "/";

				$path .= $pathArray[$p];
			}

			if (is_file($path.'/thumbs/'.$pathArray[$p]))
				unlink($path.'/thumbs/'.$pathArray[$p]);
		}

		return $success;
	}

	/**
	 * Apply file limits by type to a specified directory. If a file type's limit is exceeded,
	 * files will be deleted starting with the oldest files. Example array: array('jpg' => 3, 'pdf' => 3);
	 *
	 * @param  string   $directory
	 * @param  array    $limits
	 * @return array
	 */
	public function dirFileLimits($directory = '', $limits = array())
	{
		if (substr($directory, -1) != "/") $directory .= "/"; //add trailing slash to directory if it doesn't exist
		$deletedFiles = array();

		if (is_dir($directory) && $handle = opendir($directory)) {
			foreach ($limits as $fileTypes => $limit) {

				$fileTypes = $this->formatFileTypesList($fileTypes);

				$filesForType = array();
				$quantity = 0;

				while (false !== ($entry = readdir($handle))) {
					if (is_file($directory.$entry)) {
						$fileExt = File::extension($entry);
						if ($fileExt) {
							if (in_array(strtolower($fileExt), $fileTypes) && !in_array($directory.$entry, $filesForType)) {
								$filesForType[] = $directory.$entry;
								$quantity ++;
							}
						} //end if file extension exists (entry is not a directory)
					}
				} //end while file in directory

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
			} //end while limits
		} //end if directory can be opened

		return $deletedFiles;
	}

	/**
	 * Delete a directory and, optionally, all contents contained within it.
	 *
	 * @param  string   $directory
	 * @param  boolean  $deleteAllContents
	 * @return integer
	 */
	public function deleteDirectory($directory = '', $deleteAllContents = false)
	{
		if (substr($directory, -1) != "/") $directory .= "/"; //add trailing slash to directory if it doesn't exist
		$deletedFiles = 0;

		if (is_dir($directory) && $handle = opendir($directory)) {
			while (false !== ($entry = readdir($handle))) {
				if (!$deleteAllContents) return $deletedFiles;

				if ($entry != "." && $entry != "..") {
					if (is_file($directory.$entry)) {
						unlink($directory.$entry);
						$deletedFiles ++;
					} else if (is_dir($directory.$entry)) { //delete sub-directory and all files/directorys it contains
						$deletedFiles += $this->deleteDirectory($directory.$entry, true);	
					}
				}
			} //end while file in directory

			//remove directory
			rmdir($directory);

		} //end if directory can be opened

		return $deletedFiles;
	}

	/**
	 * Check if a file is an image based on the extension.
	 *
	 * @param  string   $file
	 * @return boolean
	 */
	public function isImage($file = '')
	{
		if (in_array(strtolower(File::extension($file)), array('png', 'jpg', 'jpeg', 'gif', 'svg'))) return true;
		return false;
	}

}