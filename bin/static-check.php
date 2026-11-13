<?php
declare(strict_types=1);
$root=dirname(__DIR__);$violations=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src',FilesystemIterator::SKIP_DOTS));
foreach($iterator as$file){if(!$file->isFile()||$file->getExtension()!=='php')continue;$path=$file->getPathname();$relative=substr($path,strlen($root)+1);$source=(string)file_get_contents($path);
    if(preg_match('/\$_(?:GET|POST|REQUEST|COOKIE|FILES|SERVER|SESSION)\b/',$source))$violations[]="$relative reads request globals";
    if(preg_match('/(?<!->)(?<!function )\b(?:header|setcookie)\s*\(/i',$source))$violations[]="$relative emits transport headers";
    if(preg_match('/\b(?:curl_init|fsockopen|stream_socket_client)\s*\(/i',$source)&&!str_ends_with($path,'/Infrastructure/Http/CurlHttpTransport.php'))$violations[]="$relative performs upstream network I/O outside CurlHttpTransport";
    if(preg_match('/\b(?:eval|assert|unserialize|shell_exec|passthru|proc_open|popen)\s*\(/i',$source))$violations[]="$relative uses a forbidden dynamic execution primitive";
    if(preg_match('/\bnew\\\\[A-Za-z_]/',$source))$violations[]="$relative uses invalid namespaced constructor syntax";
}
foreach(['/public/index.php','/bin/forward-proxy.php']as$adapter){$source=(string)file_get_contents($root.$adapter);if(!str_contains($source,'RenderVersion::CURRENT'))$violations[]=ltrim($adapter,'/').' does not use the shared render version';}
if($violations){foreach($violations as$violation)fwrite(STDERR,$violation."\n");exit(1);}echo"RetroWEB static architecture check passed.\n";
