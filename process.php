<?php
date_default_timezone_set('UTC');

include 'imageProc.php';

$proc = new imageProc();

try {
    $proc->setInputDir('./Matthews Pictures')
         ->setOutputDir('./MPx')
         ->setDirMonth()
         ->setDirYear()
         //->setPackageImages()
         ->go();
} catch (Exception $e) {
    print $e->getMessage() ."\n";
}


