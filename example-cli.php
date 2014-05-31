<?php 

require_once 'ThingiverseBackup.php';

$backup = new ThingiverseBackup();
$thingies = $backup->backup($argv[1], $argv[2]);

echo "Backup thingies:\n";
print_r($thingies);
