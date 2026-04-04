<?php
exec("python3 /var/www/html/CIS módulo/analizar_cis.py 2>&1", $output, $result);
header("Location: cis.php");
exit;
