<?php

/*------------------------------------------

[Depends]

[Document]

------------------------------------------*/

// PHP5.1.0以上で実行
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// ファイル名ラッパー
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

// 例外クラス
class GifDOM_NotImplementedException extends Exception {}
class GifDOM_MissImplementationException extends GifDOM_NotImplementedException {}
class GifDOM_InvalidDataException extends Exception {}
class GifDOM_InvalidArgumentsException extends Exception {}
class GifDOM_IOException extends Exception {}


?>