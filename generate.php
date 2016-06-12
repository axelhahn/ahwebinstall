<?php

require_once 'classes/ahwi-generator.class.php';

echo "-------------------------------------------------------------------------------\n"
. "WEBINSTALLER :: GENERATE ALL\n"
. "-------------------------------------------------------------------------------\n";
$oGenerator=new ahwigenerator();
$oGenerator->generateAll();
