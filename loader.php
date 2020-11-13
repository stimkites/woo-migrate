<?php namespace Wetail\Woo\Migration;use Exception;final class __loader{protected static$list=[];private static$args=[];const defaults=['prefix'=>'class','dir'=>__DIR__,'path'=>'includes','functions'=>'functions','classes'=>'classes','separator'=>'-'];static function init($args=[]){self::$args=array_merge(self::defaults,$args);$mask=rtrim(self::$args['dir'],'/').'/'.trim(self::$args['path'],'/').'/'.trim(self::$args['classes'],'/').'/'.trim(self::$args['prefix'],self::$args['separator']).self::$args['separator'].'*.php';self::$list=self::index(self::scan($mask));spl_autoload_register(__CLASS__.'::load');self::load_functions();return true;}static function scan($mask,$flags=0){$files=glob($mask,$flags);foreach(glob(dirname($mask).'/*',GLOB_ONLYDIR|GLOB_NOSORT)as$dir){$files=array_merge($files,self::scan($dir.'/'.basename($mask),$flags));}return($files?$files:[]);}private static function index($files){$r=[];$prefix=trim(self::$args['prefix'],self::$args['separator']).self::$args['separator'];foreach($files as$file){$parts=explode('/',$file);$last_part=end($parts);$index=substr($last_part,strpos($last_part,$prefix)+strlen($prefix));$index=substr($index,0,strlen($index)-4);$r[$index]=$file;}return$r;}private static function find_file($class_name){if(__NAMESPACE__!==substr($class_name,0,strlen(__NAMESPACE__)))return'';$parts=explode('\\',$class_name);$index=strtolower(str_replace('_',self::$args['separator'],ltrim(end($parts),'_')));if(empty(self::$list[$index])||!file_exists(self::$list[$index]))die('File for class <b>"'.$class_name.'"</b> with index <i>"'.$index.'"</i> not found!');return self::$list[$index];}static function load($class){try{$file=self::find_file($class);}catch(Exception$e){die(__NAMESPACE__.' AUTOLOADER EXCEPTION:<br/>'.$e->getMessage());}finally{if(empty($file))return;require_once$file;}}private static function load_functions(){$fun_mask=rtrim(self::$args['dir'],'/').'/'.trim(self::$args['path'],'/').'/'.trim(self::$args['functions'],'/').'/'.'*.php';foreach(self::scan($fun_mask)as$file)if(file_exists($file))require_once$file;}}__loader::init();