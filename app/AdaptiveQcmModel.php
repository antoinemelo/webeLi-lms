<?php
declare(strict_types=1);

/** Conservative Rasch estimation. No wall-clock term in the score. */
final class AdaptiveQcmModel
{
    public const MIN_ANSWERS=5;
    public const PRIOR_WEIGHT=10;

    public static function probability(float $ability,float $difficulty): float
    {
        return 1/(1+exp(max(-700,min(700,$difficulty-$ability))));
    }

    public static function difficulty(float $rate): float
    {
        $p=max(0.01,min(0.99,$rate/100));
        return log((1-$p)/$p);
    }

    /** A teacher estimate has the weight of ten provisional observations, never fake counters. */
    public static function rate(int $correct,int $resolved,float $prior): float
    {
        return 100*($correct+self::PRIOR_WEIGHT*$prior/100)/($resolved+self::PRIOR_WEIGHT);
    }

    /**
     * All resolved responses contribute; timeouts with a full question window count as incorrect.
     * Prior N(0,1.5²), fixed quadrature [-6,6], step .1. The first-question setting is not a grading prior.
     * The reported score estimates success across the WHOLE frozen bank, rather than an empirical percentile.
     */
    public static function estimate(array $pool,array $answers,bool $withScore=false): array
    {
        $byId=array_column($pool,null,'id');$observed=[];$resolved=[];
        foreach($answers as $answer){
            if(!in_array($answer['result']??null,['correct','incorrect','timeout'],true)||!isset($byId[$answer['question_id']]))continue;
            $observed[]=[self::difficulty((float)$byId[$answer['question_id']]['rate']),$answer['result']==='correct'];
            $resolved[$answer['question_id']]=(int)($answer['result']==='correct');
        }
        $grid=[];$maximum=-INF;
        for($i=-60;$i<=60;$i++){
            $theta=$i/10;$logWeight=-$theta*$theta/(2*1.5*1.5);
            foreach($observed as [$b,$correct]){
                $p=self::probability($theta,$b);$logWeight+=log($correct?$p:1-$p);
            }
            $grid[]=[$theta,$logWeight];$maximum=max($maximum,$logWeight);
        }
        $sum=0;$mean=0;
        foreach($grid as &$point){$point[1]=exp($point[1]-$maximum);$sum+=$point[1];$mean+=$point[0]*$point[1];}unset($point);
        $mean/=$sum;$variance=0;$cumulative=0;$low=null;$high=null;
        foreach($grid as [$theta,$weight]){
            $variance+=$weight*($theta-$mean)**2/$sum;$cumulative+=$weight/$sum;
            if($low===null&&$cumulative>=0.025)$low=$theta;
            if($high===null&&$cumulative>=0.975)$high=$theta;
        }
        $out=['answered'=>count($observed),'ability'=>$mean,'uncertainty'=>sqrt($variance),
            'cursor'=>100*self::probability($mean,0),'score'=>null,'interval'=>null];
        if($withScore&&$pool){
            $unseen=array_filter($pool,static fn($q)=>!array_key_exists($q['id'],$resolved));
            $difficulties=array_map(static fn($q)=>self::difficulty((float)$q['rate']),$unseen);
            $knownCorrect=array_sum($resolved);$bankSize=count($pool);
            $expected=static function(float $theta)use($difficulties,$knownCorrect,$bankSize):float{
                $total=$knownCorrect;foreach($difficulties as $difficulty)$total+=self::probability($theta,$difficulty);
                return 100*$total/$bankSize;
            };
            $score=0;foreach($grid as [$theta,$weight])$score+=$weight/$sum*$expected($theta);
            // Less than five usable responses never produces an automatic grade.
            if(count($observed)>=self::MIN_ANSWERS)$out['score']=round($score/10,1)*10;
            $out['interval']=[round($expected($low??-6)/10,1),round($expected($high??6)/10,1)];
        }
        return $out;
    }
}
