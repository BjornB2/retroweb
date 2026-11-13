<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Image;
final class ImagePlan { public function __construct(public int $width, public int $height, public int $decodedBytes) {} }
