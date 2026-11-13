<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Image;

final class ImageConverter
{
    public function __construct(private ImagePlanner $planner){}
    public function toGif(string $source,int $maxWidth,int $maxHeight):array
    {
        if(strlen($source)>8388608)throw new \LengthException('Image source too large');$metadata=@getimagesizefromstring($source);$width=(int)($metadata[0]??0);$height=(int)($metadata[1]??0);if($width<1||$height<1)throw new \DomainException('Unsupported image');if($width>12000||$height>12000||$width*$height>16000000)throw new \LengthException('Decoded image dimensions are too large');$input=@imagecreatefromstring($source);if(!$input)throw new \DomainException('Unsupported image');$plan=$this->planner->plan($width,$height,$maxWidth,$maxHeight);
        $output=imagecreatetruecolor($plan->width,$plan->height);$white=imagecolorallocate($output,255,255,255);imagefill($output,0,0,$white);imagecopyresampled($output,$input,0,0,0,0,$plan->width,$plan->height,$width,$height);ob_start();imagegif($output);$gif=(string)ob_get_clean();unset($input,$output);return ['data'=>$gif,'plan'=>$plan];
    }
}
