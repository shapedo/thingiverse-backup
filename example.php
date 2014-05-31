<?php 

require_once 'ThingiverseBackup.php';

$backup = new ThingiverseBackup();
$shapes = $backup->backup('username', 'destinationFolder');

echo "Backup thingies:\n";
print_r($shapes);