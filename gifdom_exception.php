<?php

/*------------------------------------------

[Depends]

[Document]

------------------------------------------*/

// PHP5.1.0�ʾ�Ǽ¹�
if(version_compare(phpversion(), "5.1.0", "<")) return 0;

// �ե�����̾��åѡ�
if(! function_exists("_f")) { function _f($fname){ return $fname; } }

// �㳰���饹
class GifDOM_NotImplementedException extends Exception {}
class GifDOM_MissImplementationException extends GifDOM_NotImplementedException {}
class GifDOM_InvalidDataException extends Exception {}
class GifDOM_InvalidArgumentsException extends Exception {}
class GifDOM_IOException extends Exception {}


?>