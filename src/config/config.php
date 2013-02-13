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
		'createDir'              => false,
		'filename'               => false,
		'overwrite'              => false,
		'returnJSON'             => false,

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
		'imgResize_default_type' => 'landscape', //if resizing but not cropping, this is the default cropping option (see Resizer bundle options)
		'imgResize_quality'      => 75,
		'imgThumb'               => false,
		'imgCrop'                => false,
		'imgCropThumb'           => true,
		'imgDimensions'          => array(
			'w'  =>	480,	//image width
			'h'  =>	480,	//image height
			'tw' => 120,	//thumbnail image width
			'th' => 120,	//thumbnail image height
		),
	),

	'crop' => array(
		'path'          => true, //set this to true to use the same path as the upload config above
		'newPath'       => false,
		'createDir'     => false,
		'filename'      => '',
		'newFilename'   => false,
		'overwrite'     => false,
		'cropPosition'  => array(
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

);