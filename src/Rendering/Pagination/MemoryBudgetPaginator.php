<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Pagination;
use RetroWEB\Domain\Document\Fragment;

final class MemoryBudgetPaginator
{
    public function paginate(array $fragments, int $budgetBytes, int $fixedOverhead = 0, bool $unsplit = false): array
    {
        if ($budgetBytes <= $fixedOverhead) throw new \InvalidArgumentException('Budget must exceed overhead');
        if ($unsplit) return [$this->unsplit($fragments, $budgetBytes - $fixedOverhead)];
        $parts=[];$part=[];$used=$fixedOverhead;$capacity=$budgetBytes-$fixedOverhead;$queue=[];foreach($fragments as$fragment)foreach($this->fitText($fragment,$capacity)as$item)$queue[]=$item;
        foreach ($queue as $fragment) {
            if (!$fragment instanceof Fragment) throw new \InvalidArgumentException('Invalid fragment');
            $candidate = $fragment;
            if ($fragment->decodedImageBytes > 0 && $fixedOverhead + $fragment->cost() > $budgetBytes) $candidate = $this->linked($fragment);
            if ($part && $used + $candidate->cost() > $budgetBytes) { $parts[] = $part; $part = []; $used = $fixedOverhead; }
            $part[] = $candidate; $used += $candidate->cost();
        }
        if ($part || !$parts) $parts[] = $part;
        return $parts;
    }
    private function unsplit(array $fragments, int $available): array
    {
        $result = []; $used = 0;
        foreach ($fragments as $fragment) {
            if (!$fragment instanceof Fragment) throw new \InvalidArgumentException('Invalid fragment');
            if ($fragment->decodedImageBytes > 0 && $used + $fragment->cost() > $available) $fragment = $this->linked($fragment);
            $result[] = $fragment; $used += $fragment->cost();
        }
        return $result;
    }
    private function linked(Fragment $fragment): Fragment
    {
        if($fragment->memoryFallbackHtml!==null)return new Fragment($fragment->memoryFallbackHtml);
        $label = $fragment->imageLabel ?? 'Image'; return new Fragment('[Image: ' . $label . ' — linked because of memory budget]');
    }
    private function fitText($fragment,int$capacity):array
    {
        if(!$fragment instanceof Fragment)throw new \InvalidArgumentException('Invalid fragment');if($fragment->decodedImageBytes>0||$fragment->cost()<=$capacity)return[$fragment];$text=trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($fragment->html),ENT_QUOTES|ENT_HTML5,'UTF-8')));if($text==='')return[new Fragment('')];$chunks=[];$current='';foreach(preg_split('/\s+/u',$text)?:[]as$word){if(strlen('<p>'.htmlspecialchars($word,ENT_QUOTES,'UTF-8').'</p>')>$capacity){if($current!==''){$chunks[]=new Fragment('<p>'.htmlspecialchars($current,ENT_QUOTES,'UTF-8').'</p>');$current='';}foreach($this->characters($word,$capacity)as$piece)$chunks[]=$piece;continue;}$candidate=$current===''?$word:$current.' '.$word;$html='<p>'.htmlspecialchars($candidate,ENT_QUOTES,'UTF-8').'</p>';if($current!==''&&strlen($html)>$capacity){$chunks[]=new Fragment('<p>'.htmlspecialchars($current,ENT_QUOTES,'UTF-8').'</p>');$current=$word;}else$current=$candidate;}if($current!=='')$chunks[]=new Fragment('<p>'.htmlspecialchars($current,ENT_QUOTES,'UTF-8').'</p>');return$chunks;
    }
    private function characters(string$text,int$capacity):array{$result=[];$current='';foreach(mb_str_split($text)as$character){$candidate=$current.$character;if($current!==''&&strlen('<p>'.htmlspecialchars($candidate,ENT_QUOTES,'UTF-8').'</p>')>$capacity){$result[]=new Fragment('<p>'.htmlspecialchars($current,ENT_QUOTES,'UTF-8').'</p>');$current=$character;}else$current=$candidate;}if($current!=='')$result[]=new Fragment('<p>'.htmlspecialchars($current,ENT_QUOTES,'UTF-8').'</p>');return$result;}
}
