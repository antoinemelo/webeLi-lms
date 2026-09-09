<?php

declare(strict_types=1);

final class ContentBlock
{
    public const TYPES=['markdown','image','file','media','iframe','submission'];

    public static function mediaType(string $source): ?string
    {
        $embed=Embed::parse($source);if(!$embed)return null;
        $path=strtolower(parse_url($embed['url'],PHP_URL_PATH)??'');
        $extension=pathinfo($path,PATHINFO_EXTENSION);
        if(in_array($extension,['mp3','wav','ogg','m4a','aac','flac'],true))return 'audio';
        if(in_array($extension,['mp4','webm','ogv','m4v','mov'],true))return 'video';
        $host=strtolower(parse_url($embed['url'],PHP_URL_HOST)??'');
        if((in_array($host,['youtube.com','www.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com'],true)&&str_starts_with($path,'/embed'))
            ||(in_array($host,['rts.ch','www.rts.ch'],true)&&rtrim($path,'/')==='/play/embed'))return 'embed';
        return str_starts_with(strtolower(trim($source)),'<iframe')?'embed':null;
    }

    public static function kind(array $block): string
    {
        if($block['type']!=='iframe')return $block['type'];
        $kind=$block['embed_kind']??'auto';
        return $kind==='media'||($kind==='auto'&&self::mediaType(Embed::parse($block['body'])['url']??'')!==null)?'media':'iframe';
    }

    public static function localFile(string $source,string $root): ?string
    {
        if(!preg_match('~^(?:uploads|assets)/[^\x00-\x1F\\\\]+$~',$source))return null;
        $base=realpath($root);$path=realpath($root.'/'.$source);
        return $base&&$path&&str_starts_with($path,$base.DIRECTORY_SEPARATOR)&&is_file($path)?$path:null;
    }

    public static function uploadLimit(): int
    {
        $bytes=static function(string $value):int{
            $value=trim($value);$number=(float)$value;
            return (int)($number*match(strtolower(substr($value,-1))){'g'=>1024**3,'m'=>1024**2,'k'=>1024,default=>1});
        };
        $limits=[10*1024*1024];
        foreach(['upload_max_filesize','post_max_size'] as $setting){$limit=$bytes((string)ini_get($setting));if($limit>0)$limits[]=$limit;}
        return min($limits);
    }

    public static function sizeLabel(int $size): string
    {
        return $size>=1024*1024?rtrim(rtrim(number_format($size/1024/1024,1,',',''),'0'),',').' '.t('Mo'):(string)max(1,(int)ceil($size/1024)).' '.t('Ko');
    }

    /** Pure validation: no uploaded file is moved until the edit lock is accepted. */
    public static function validate(string $kind,string $body,array $options,?array $file,string $root,?array $stored=null): array
    {
        if(!in_array($kind,self::TYPES,true))throw new InvalidArgumentException('Type de bloc invalide.');
        $body=trim($body);$fileError=(int)($file['error']??UPLOAD_ERR_NO_FILE);$hasFile=$fileError!==UPLOAD_ERR_NO_FILE;
        $supportsUpload=in_array($kind,['image','file'],true);
        if($hasFile&&!$supportsUpload)throw new InvalidArgumentException('Ce type de bloc ne permet pas l’import de fichier.');
        $source=$options['source']??($hasFile||str_starts_with($body,'uploads/')?'upload':'url');
        $upload=null;
        if($supportsUpload){
            if(!in_array($source,['upload','url'],true))throw new InvalidArgumentException('Choisissez une source : import ou adresse.');
            if($source==='url'&&$hasFile)throw new InvalidArgumentException('Choisissez une seule source : import ou adresse.');
            if($hasFile){
                if($fileError!==UPLOAD_ERR_OK)throw new InvalidArgumentException('L’import a échoué. Vérifiez le fichier et la limite du serveur.');
                $tmp=(string)($file['tmp_name']??'');
                if(!is_file($tmp)||filesize($tmp)===false||filesize($tmp)>self::uploadLimit())throw new InvalidArgumentException('Le fichier dépasse la limite d’import autorisée.');
                $mime=(string)(mime_content_type($tmp)?:'application/octet-stream');
                $extension=strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION));
                if($kind==='image'){
                    $dimensions=@getimagesize($tmp);
                    $formats=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/avif'=>'avif','image/bmp'=>'bmp'];
                    if(!$dimensions||!isset($formats[$dimensions['mime']??'']))throw new InvalidArgumentException('Choisissez une image JPEG, PNG, GIF, WebP, AVIF ou BMP.');
                    $extension=$formats[$dimensions['mime']];
                }elseif(!in_array($extension,['pdf','txt','md','csv','json','xml','zip','7z','rar','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','rtf','epub','png','jpg','jpeg','gif','webp','avif','bmp','mp3','mp4','wav','webm','py','js','css'],true)){
                    throw new InvalidArgumentException('Ce format de document n’est pas pris en charge.');
                }
                $upload=['tmp_name'=>$tmp,'extension'=>$extension,'name'=>(string)($file['name']??''),'mime'=>$mime];
            }elseif($source==='upload'){
                if(!$stored||$body!==$stored['body']||!self::localFile($body,$root))throw new InvalidArgumentException('Sélectionnez un fichier à importer.');
            }elseif($body!==''&&!self::validAddress($body)){
                // Existing packaged assets remain usable without requiring re-upload.
                if(!$stored||$body!==$stored['body']||!self::localFile($body,$root))throw new InvalidArgumentException('Saisissez une adresse http(s) valide.');
            }
            if($body===''&&!$upload)throw new InvalidArgumentException('Ajoutez un fichier ou une adresse.');
            if($kind==='image'&&!$upload){
                $local=self::localFile($body,$root);
                if($local&&!@getimagesize($local)&&!($stored&&$stored['type']==='image'&&$stored['body']===$body))throw new InvalidArgumentException('Ce fichier ne peut pas être affiché comme une image.');
                $extension=strtolower(pathinfo(parse_url($body,PHP_URL_PATH)??'',PATHINFO_EXTENSION));
                if(in_array($extension,['pdf','doc','docx','zip','txt','mp4','mp3'],true))throw new InvalidArgumentException('Cette adresse désigne un document. Utilisez le bloc Document.');
            }
        }
        $height=null;$embedKind='auto';
        if(in_array($kind,['iframe','media'],true)){
            if(!Embed::parse($body))throw new InvalidArgumentException('Intégration invalide : collez une URL http(s) ou un code iframe complet.');
            if($kind==='media'&&self::mediaType($body)===null)throw new InvalidArgumentException('Collez une adresse de média, un lien YouTube/RTS ou le code du lecteur.');
            $embedKind=$kind==='media'?'media':'integration';
            if($kind==='iframe'&&trim((string)($options['height']??''))!==''){
                $raw=(string)$options['height'];
                if(!ctype_digit($raw)||(int)$raw<100||(int)$raw>2000)throw new InvalidArgumentException('La hauteur doit être comprise entre 100 et 2 000 pixels.');
                $height=(int)$raw;
            }
        }
        $alt=$kind==='image'?(string)($options['alt']??$stored['image_alt']??$stored['caption']??''):($stored['image_alt']??null);
        if($alt!==null&&mb_strlen($alt)>512)throw new InvalidArgumentException('La description alternative est limitée à 512 caractères.');
        return ['type'=>$kind==='media'?'iframe':$kind,'body'=>$body,'image_alt'=>$alt,'embed_kind'=>$embedKind,'embed_height'=>$height,'upload'=>$upload];
    }

    public static function validAddress(string $body): bool {return WorkSubmission::validUrl($body);}

    public static function storeUpload(array $upload,string $root): string
    {
        $directory=rtrim($root,'/').'/uploads';
        if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('Le dossier des fichiers ne peut pas être créé.');
        $stem=preg_replace('/[^A-Za-z0-9_-]/','-',pathinfo($upload['name'],PATHINFO_FILENAME))?:'document';
        $name=substr($stem,0,80).'-'.bin2hex(random_bytes(8)).'.'.$upload['extension'];
        if(!move_uploaded_file($upload['tmp_name'],$directory.'/'.$name))throw new RuntimeException('Le fichier importé ne peut pas être enregistré.');
        return 'uploads/'.$name;
    }

    public static function renderMedia(array $block): string
    {
        $embed=Embed::parse($block['body']);$type=self::mediaType($block['body']);
        if($embed&&in_array($type,['audio','video'],true))return '<'.$type.' class="block-media" controls preload="metadata" aria-label="'.e($block['caption']?:t('Vidéo / audio')).'" src="'.e($embed['url']).'"></'.$type.'>';
        return Embed::render($block['body'],$block['caption']);
    }

    public static function fileInfo(array $block,string $root): string
    {
        $path=self::localFile($block['body'],$root);$extension=strtoupper(pathinfo(parse_url($block['body'],PHP_URL_PATH)??'',PATHINFO_EXTENSION));
        return implode(' · ',array_filter([$extension,$path?self::sizeLabel((int)filesize($path)):null]));
    }
}
