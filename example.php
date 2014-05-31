<?php 

require_once 'ThingiverseBackup.php';

$backup = new ThingiverseBackup();
$thingies = $backup->backup('username', 'destinationFolder');

echo "Backup thingies:\n";
print_r($thingies);