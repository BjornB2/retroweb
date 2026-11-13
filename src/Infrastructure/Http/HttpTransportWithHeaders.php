<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Http;
interface HttpTransportWithHeaders extends HttpTransport{public function requestWithHeaders(string$url,string$method,?string$body,array$addresses,int$maxBytes,array$headers):HttpResponse;}
