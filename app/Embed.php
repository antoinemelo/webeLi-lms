<?php

declare(strict_types=1);

/** Interpret an embed without ever inserting pasted HTML into the page. */
final class Embed
{
    /** @return array{url:string,title:string,width:?int,height:?int}|null */
    public static function parse(string $source): ?array
    {
        $source=trim($source);$attributes=[];
        if(str_starts_with($source,'<')){
            if(!preg_match('~^<iframe\b((?:[^\'">]|"[^"]*"|\'[^\']*\')*)>\s*</iframe\s*>$~is',$source,$tag))return null;
            preg_match_all('~([^\s=\'"<>/]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\'"=<>`]+)))?~',$tag[1],$matches,PREG_SET_ORDER|PREG_UNMATCHED_AS_NULL);
            foreach($matches as $match){
                $name=strtolower($match[1]);
                if(!array_key_exists($name,$attributes))$attributes[$name]=html_entity_decode($match[2]??$match[3]??$match[4]??'',ENT_QUOTES|ENT_HTML5,'UTF-8');
            }
            $source=trim($attributes['src']??'');
        }
        if(str_starts_with($source,'//'))$source='https:'.$source;
        $parts=parse_url($source);
        if(!$parts||!in_array(strtolower($parts['scheme']??''),['http','https'],true)||empty($parts['host'])
            ||isset($parts['user'])||isset($parts['pass'])||preg_match('/[\x00-\x20<>"\'\\\\]/',$source))return null;
        $host=strtolower($parts['host']);$path=$parts['path']??'';$id=null;
        parse_str($parts['query']??'',$query);
        if(in_array($host,['youtu.be','www.youtu.be'],true))$id=trim($path,'/');
        elseif(in_array($host,['youtube.com','www.youtube.com','m.youtube.com'],true)){
            if($path==='/watch')$id=$query['v']??null;
            elseif(preg_match('~^/(?:shorts|live)/([^/]+)/?$~',$path,$match))$id=$match[1];
        }
        if(is_string($id)&&preg_match('/^[A-Za-z0-9_-]{11}$/',$id)){
            $params=[];
            foreach(['start','end','list','index'] as $key)if(isset($query[$key])&&is_string($query[$key]))$params[$key]=$query[$key];
            $time=$query['t']??null;
            if(!isset($params['start'])&&is_string($time)&&preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/',$time,$match)){
                $seconds=(int)($match[1]??0)*3600+(int)($match[2]??0)*60+(int)($match[3]??0);
                if($seconds>0)$params['start']=$seconds;
            }
            $source='https://www.youtube.com/embed/'.$id.($params?'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986):'');
        }
        $dimension=static function(string $name)use($attributes):?int{
            $value=trim($attributes[$name]??'');
            return preg_match('/^\d{1,4}(?:px)?$/',$value)&&(int)$value>0&&(int)$value<=4096?(int)$value:null;
        };
        return ['url'=>$source,'title'=>trim($attributes['title']??$attributes['name']??''),'width'=>$dimension('width'),'height'=>$dimension('height')];
    }

    public static function render(string $source,string $caption='',?int $height=null): string
    {
        $embed=self::parse($source);
        if($embed===null)return '<p class="muted-copy">'.e(t('Intégration invalide : collez une URL http(s) ou un code iframe complet.')).'</p>';
        $title=trim($caption)?:($embed['title']?:t('Contenu intégré / vidéo'));
        $style='';
        // Play RTS now uses a full player even for audio; older embed codes still specify 58px.
        $url=parse_url($embed['url']);
        $legacyRtsAudio=in_array(strtolower($url['host']??''),['rts.ch','www.rts.ch'],true)
            &&rtrim($url['path']??'','/')==='/play/embed'&&$embed['height']!==null&&$embed['height']<150;
        if($embed['height']!==null&&!$legacyRtsAudio){
            // Compact audio players must retain their controls at every screen width.
            $style=$embed['width']!==null&&$embed['height']>=150
                ?' style="padding-top:'.number_format($embed['height']/$embed['width']*100,4,'.','').'%"'
                :' style="padding-top:0;height:'.$embed['height'].'px"';
        }
        if($height!==null&&$height>=100&&$height<=2000)$style=' style="padding-top:0;height:'.$height.'px"';
        return '<div class="iframe-wrap"'.$style.'><iframe src="'.e($embed['url']).'" title="'.e($title).'" loading="lazy" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>';
    }
}
