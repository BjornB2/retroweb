<?php
declare(strict_types=1);
namespace RetroWEB\Transport\ForwardProxy;
use RetroWEB\Infrastructure\Persistence\FileSettingsRepository;use RetroWEB\Domain\Settings\SettingsOverrides;
final class RepositoryImageModeUpdater implements QuickImageModeUpdater{public function __construct(private FileSettingsRepository$settings){}public function update(string$clientIdentity,string$mode,int$now):void{if(!in_array($mode,['inline','links'],true))throw new \InvalidArgumentException('Invalid image mode');$state=$this->settings->loadState($clientIdentity,$now);$saved=$state[0]??new SettingsOverrides();$this->settings->save($clientIdentity,new SettingsOverrides($saved->values,$mode,$saved->selectedProfile),$now,$state[1]??false);}}
