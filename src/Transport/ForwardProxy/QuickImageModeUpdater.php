<?php
declare(strict_types=1);
namespace RetroWEB\Transport\ForwardProxy;
interface QuickImageModeUpdater{public function update(string$clientIdentity,string$mode,int$now):void;}
