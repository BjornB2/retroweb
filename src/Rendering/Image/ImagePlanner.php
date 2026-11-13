<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Image;

final class ImagePlanner
{
    public function plan(int $sourceWidth, int $sourceHeight, int $maxWidth, int $maxHeight): ImagePlan
    {
        foreach ([$sourceWidth,$sourceHeight,$maxWidth,$maxHeight] as $n) if ($n < 1) throw new \InvalidArgumentException('Image dimensions must be positive');
        $scale=min(1.0,$maxWidth/$sourceWidth,$maxHeight/$sourceHeight);
        $width=max(1,(int)floor($sourceWidth*$scale)); $height=max(1,(int)floor($sourceHeight*$scale));
        // The delivered asset is an indexed GIF. Budget its decoded index buffer
        // plus a full 256-colour palette instead of a modern 32-bit RGBA buffer.
        return new ImagePlan($width,$height,$width*$height+1024);
    }
}
