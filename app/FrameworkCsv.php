<?php

declare(strict_types=1);

function framework_csv_safe_cell(mixed $value): string
{
    $cell=(string)$value;
    if(preg_match('/^-?\d+(?:[.,]\d+)?$/',$cell))return $cell;
    return preg_match('/^[=+\-@]/u',$cell)?"'".$cell:$cell;
}

function framework_csv_document(array $headers, array $rows): string
{
    $stream=fopen('php://temp','w+b');
    if($stream===false)throw new TransferException('Le fichier CSV ne peut pas être créé.');
    fwrite($stream,"\xEF\xBB\xBF");
    fputcsv($stream,array_map('framework_csv_safe_cell',$headers),';','"','\\',"\r\n");
    foreach($rows as $row)fputcsv($stream,array_map('framework_csv_safe_cell',$row),';','"','\\',"\r\n");
    rewind($stream);$contents=stream_get_contents($stream);fclose($stream);
    if($contents===false)throw new TransferException('Le fichier CSV ne peut pas être créé.');
    return $contents;
}

function framework_csv_parse(string $contents, int $columns): array
{
    if(strlen($contents)>2*1024*1024)throw new TransferException('Le fichier CSV dépasse la limite de 2 Mo.');
    $contents=preg_replace('/^\xEF\xBB\xBF/','',$contents)??$contents;
    $firstLine=strtok(str_replace(["\r\n","\r"],"\n",$contents),"\n")?:'';
    $delimiters=[';',',',"\t"];$delimiter=';';$best=0;
    foreach($delimiters as $candidate){$count=count(str_getcsv($firstLine,$candidate,'"','\\'));if($count>$best){$best=$count;$delimiter=$candidate;}}
    if($best<$columns)throw new TransferException('Le fichier CSV ne contient pas les colonnes attendues.');
    $stream=fopen('php://temp','w+b');if($stream===false)throw new TransferException('Le fichier CSV ne peut pas être lu.');
    fwrite($stream,$contents);rewind($stream);$rows=[];$line=0;
    while(($row=fgetcsv($stream,0,$delimiter,'"','\\'))!==false){$line++;if($line===1)continue;$row=array_map(static function(mixed $value):string{$cell=trim((string)$value);return preg_match('/^\'[=+\-@]/u',$cell)?substr($cell,1):$cell;},$row);if(!array_filter($row,static fn(string $value):bool=>$value!==''))continue;if(count($row)<$columns)throw new TransferException('Une ligne du fichier CSV est incomplète.');$rows[]=array_slice($row,0,$columns);if(count($rows)>500)throw new TransferException('Le fichier CSV contient plus de 500 lignes.');}
    fclose($stream);
    if(!$rows)throw new TransferException('Le fichier CSV ne contient aucune donnée.');
    return $rows;
}

function pathway_objectives_csv(PDO $pdo, int $courseId): string
{
    $rows=array_map(static fn(array $objective):array=>[$objective['title'],$objective['description'],implode(', ',$objective['item_positions'])],pathway_objectives($pdo,$courseId));
    return framework_csv_document([t('Objectif'),t('Description'),t('Étapes')],$rows);
}

function pathway_skills_csv(PDO $pdo, int $courseId): string
{
    $rows=[];foreach(pathway_sidebar_skills($pdo,$courseId) as $skill)$rows[]=[$skill['code'],$skill['title'],$skill['description']];
    return framework_csv_document([t('Code'),t('Compétence'),t('Description')],$rows);
}

function pathway_rewards_csv(PDO $pdo, int $courseId): string
{
    $query=$pdo->prepare('SELECT name,icon,default_points FROM reward_types WHERE course_id=? AND active=1 ORDER BY name');$query->execute([$courseId]);
    $rows=array_map(static fn(array $reward):array=>[$reward['name'],$reward['icon'],(int)$reward['default_points']],$query->fetchAll(PDO::FETCH_ASSOC));
    return framework_csv_document([t('Nom'),t('Icône'),t('Points')],$rows);
}

function import_pathway_skills_csv(PDO $pdo, int $courseId, string $contents): int
{
    $rows=framework_csv_parse($contents,3);$position=(int)$pdo->query('SELECT COALESCE(MAX(position),0) FROM course_skills WHERE course_id='.(int)$courseId)->fetchColumn();
    $statement=$pdo->prepare('INSERT INTO course_skills(course_id,code,title,description,position) VALUES(?,?,?,?,?) ON CONFLICT(course_id,code) DO UPDATE SET title=excluded.title,description=excluded.description');
    $pdo->beginTransaction();
    try{foreach($rows as [$code,$title,$description]){$code=mb_substr(mb_strtoupper(trim($code),'UTF-8'),0,50);$title=mb_substr(trim($title),0,200);$description=mb_substr(trim($description),0,2000);if($code===''||$title==='')throw new TransferException('Chaque compétence doit contenir un code et un titre.');$statement->execute([$courseId,$code,$title,$description,++$position]);}$pdo->commit();return count($rows);}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function import_pathway_rewards_csv(PDO $pdo, int $courseId, string $contents): int
{
    $rows=framework_csv_parse($contents,3);$find=$pdo->prepare('SELECT id FROM reward_types WHERE course_id=? AND lower(name)=lower(?)');$update=$pdo->prepare('UPDATE reward_types SET name=?,icon=?,default_points=?,active=1 WHERE id=?');$insert=$pdo->prepare('INSERT INTO reward_types(course_id,name,icon,color,default_points,active) VALUES(?,?,?,?,?,1)');
    $pdo->beginTransaction();
    try{foreach($rows as [$name,$icon,$points]){$name=mb_substr(trim($name),0,160);$icon=mb_substr(trim($icon),0,20)?:'✨';if($name==='')throw new TransferException('Chaque encouragement doit contenir un nom.');$normalizedPoints=normalize_reward_points($points);$find->execute([$courseId,$name]);$id=$find->fetchColumn();if($id!==false)$update->execute([$name,$icon,$normalizedPoints,(int)$id]);else $insert->execute([$courseId,$name,$icon,'#6d5dfc',$normalizedPoints]);}$pdo->commit();return count($rows);}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}
