<?php
// Удаляем куки
setcookie("userid", "", time() - 3600*24*30*12, "/");
setcookie("hash", "", time() - 3600*24*30*12, "/", null, null, true);

// Переадресовываем браузер на страницу проверки нашего скрипта
header("Location: /"); exit;

?>