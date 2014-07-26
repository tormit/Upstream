<?php namespace Aquanode\Upstream;

class Facade extends \Illuminate\Support\Facades\Facade {

	protected static function getFacadeAccessor() { return 'upstream'; }

}