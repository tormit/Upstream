<?php namespace Regulus\Upstream;

class Facade extends \Illuminate\Support\Facades\Facade {

	protected static function getFacadeAccessor() { return 'Regulus\Upstream\Upstream'; }

}