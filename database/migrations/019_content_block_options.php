<?php

declare(strict_types=1);

return [
    'version'=>19,
    'name'=>'Champs adaptés aux blocs et distinction des médias',
    'up'=>static function(PDO $pdo): void {
        $columns=array_column($pdo->query('PRAGMA table_info(page_blocks)')->fetchAll(PDO::FETCH_ASSOC),'name');
        foreach([
            'image_alt'=>'TEXT',
            'embed_kind'=>"TEXT NOT NULL DEFAULT 'auto' CHECK(embed_kind IN ('auto','media','integration'))",
            'embed_height'=>'INTEGER CHECK(embed_height IS NULL OR embed_height BETWEEN 100 AND 2000)',
        ] as $name=>$definition)if(!in_array($name,$columns,true))$pdo->exec('ALTER TABLE page_blocks ADD COLUMN '.$name.' '.$definition);
    },
];
