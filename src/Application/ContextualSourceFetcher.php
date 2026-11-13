<?php
declare(strict_types=1);
namespace RetroWEB\Application;
interface ContextualSourceFetcher{public function fetchFor(RenderRequest$request):SourceResponse;}
