<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Database.php';
require_once dirname(__DIR__) . '/app/RegistrationPolicy.php';
require_once dirname(__DIR__) . '/app/LearningActivity.php';
require_once dirname(__DIR__) . '/app/QcmGuest.php';
require_once dirname(__DIR__) . '/app/Housekeeping.php';
$root=dirname(__DIR__);
if(!run_housekeeping(Database::connect($root),$root,true)){
    echo "Un autre traitement ou une maintenance est en cours.\n";
    exit;
}

echo "Purge des inscriptions expirées et des activités de plus d’un mois terminée.\n";
