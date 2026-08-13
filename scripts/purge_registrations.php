<?php

declare(strict_types=1);

session_save_path(sys_get_temp_dir());
require_once dirname(__DIR__) . '/app/bootstrap.php';

echo "Purge des inscriptions expirées et des activités de plus d’un mois terminée.\n";
