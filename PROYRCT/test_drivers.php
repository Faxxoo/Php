<?php
header('Content-Type: text/plain');
echo "PHP Version: " . phpversion() . "\n";
echo "Loaded php.ini: " . php_ini_loaded_file() . "\n";
echo "Available Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
?>
