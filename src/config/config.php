<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Uploading Defaults
	|--------------------------------------------------------------------------
	|
	| The default setup for uploading files. 
	|
	*/
	'upload' => array(
		'path'                   => 'uploads',
		'fields'                 => true, //use a string like "image" if you have only one file field called "image" that you would like to process or pass an array for specific file fields
		'fieldThumb'             => 'thumbnail_image',
		'createDirectory'        => false,
		'filename'               => false,
		'overwrite'              => false,
		'returnJson'             => false,
		'noCacheUrl'             => true,

		//error triggers
		'fileTypes'              => '*',
		'maxFileSize'            => false,

		//image uploading size limits
		'imageMinWidth'          => false,
		'imageMinHeight'         => false,
		'imageMaxWidth'          => false, //if max is exceeded and imgResizeMax is true, image will be resized to max instead of triggering error
		'imageMaxHeight'         => false,

		//image resizing
		'imageResize'            => false,
		'imageResizeMax'         => false, //used in conjunction with imgMaxWidth and/or imgMaxHeight to resize only if image exceeds maximums; images that are smaller will not be upscaled
		'imageResizeDefaultType' => 'landscape', //if resizing but not cropping, this is the default cropping option (see Resizer bundle options)
		'imageResizeQuality'     => 75,
		'imageThumb'             => false,
		'imageCrop'              => false,
		'imageCropThumb'         => true,
		'imageDimensions'        => array(
			'w'  =>	1024, //image width
			'h'  =>	768,  //image height
			'tw' => 180,  //thumbnail image width
			'th' => 180,  //thumbnail image height
		),

		'displayName'            => false, //use false to use filename as display name
		'defaultThumb'           => 'default-thumb-upload.png',

		'returnSingleResult'     => false,
		'fieldNameAsFileIndex'   => true,
	),

	/*
	|--------------------------------------------------------------------------
	| Cropping Defaults
	|--------------------------------------------------------------------------
	|
	| The default setup for cropping images. 
	|
	*/
	'crop' => array(
		'path'            => true, //set this to true to use the same path as the upload config above
		'newPath'         => false,
		'createDirectory' => false,
		'filename'        => '',
		'newFilename'     => false,
		'overwrite'       => false,
		'cropPosition'    => array(
			'x' => 0,
			'y' => 0,
			'w' => 180,
			'h' => 180,
		),
		'imageDimensions' => array(
			'w' => 180,
			'h' => 180,
		),
	),

	/*
	|--------------------------------------------------------------------------
	| File Type Categories
	|--------------------------------------------------------------------------
	|
	| The file type categories for allowed file types for uploading and for
	| the file type order when getting the contents of a directory. Setting
	| the "fileTypes" upload config variable to "images", for example, will
	| allow the upload() method to automatically allow all image file types.
	|
	*/
	'fileTypeCategories' => array(
		'image'  => array(
			'jpg',
			'jpeg',
			'png',
			'gif',
		),
		'vector' => array(
			'svg',
			'eps',
			'ai',
		),
		'audio'  => array(
			'mp3',
			'ogg',
			'wma',
			'wav',
		),
		'video'  => array(
			'mp4',
			'avi',
			'fla',
			'mov',
			'wmv',
		),
	),

);