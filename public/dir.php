<?php
declare(strict_types=1);
echo __DIR__ . '/../lib/guard.php'."</br>";
echo dirname(__DIR__)."</br>";
echo dirname(__DIR__,2)."</br>";
echo __DIR__."</br>";
echo __DIR__."/../</br>";
echo __DIR__.'../api/'."</br>";
echo __DIR__.'/../api/'."</br>";
echo $_SERVER['DOCUMENT_ROOT'] . '/archivo.php'."</br>";
echo $_SERVER['DOCUMENT_ROOT'] . '../archivo.php'."</br>";
echo $_SERVER['DOCUMENT_ROOT'] . '/../archivo.php'."</br>";
$file= "../../../../../../home/u695435470/domains/atiscsl.esy.es/public_html/unificado/parent.php";
echo $file."</br>";
?>
<a href="<?php echo $file ?>">Link text</a>;
