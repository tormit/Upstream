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
		'path'                   => 'public/uploads/',
		'createDirectory'        => false,
		'filename'               => false,
		'overwrite'              => false,
		'returnJson'             => false,
		'noCacheUrl'             => true,

		//error triggers
		'fileTypes'              => '*',
		'maxFileSize'            => false,

		//image uploading
		'imgMinWidth'            => false,
		'imgMinHeight'           => false,
		'imgMaxWidth'            => false, //if max is exceeded and imgResizeMax is true, image will be resized to max instead of triggering error
		'imgMaxHeight'           => false,

		//image resizing
		'imgResize'              => false,
		'imgResizeMax'           => false, //used in conjunction with imgMaxWidth and/or imgMaxHeight to resize only if image exceeds maximums
		'imgResizeDefaultType'   => 'landscape', //if resizing but not cropping, this is the default cropping option (see Resizer bundle options)
		'imgResizeQuality'       => 75,
		'imgThumb'               => false,
		'imgCrop'                => false,
		'imgCropThumb'           => true,
		'imgDimensions'          => array(
			'w'  =>	480,	//image width
			'h'  =>	480,	//image height
			'tw' => 120,	//thumbnail image width
			'th' => 120,	//thumbnail image height
		),

		'displayName'            => false, //use false to use filename as display name
		'defaultThumb'           => 'default-thumb-upload.png',
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
			'w' => 120,
			'h' => 120,
		),
		'imgDimensions' => array(
			'w' => 120,
			'h' => 120,
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
		'image' => array(
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
		'audio' => array(
			'mp3',
			'ogg',
			'wma',
			'wav',
		),
		'video' => array(
			'mp4',
			'avi',
			'fla',
			'mov',
			'wmv',
		),
	),
);