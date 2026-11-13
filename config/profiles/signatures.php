<?php
return [
 ['profile'=>'nintendo_ds','status'=>'verified','pattern'=>'/^Mozilla\/4\.0 \(compatible; MSIE 6\.0; Nitro\) Opera 8\.50 \[[a-z]{2}(?:-[a-z]{2})?\]$/i','provenance'=>'community Nintendo DS (NTR/USG) Opera 8.50 submission, 2026-11-12'],
 ['profile'=>'palm_iiic','status'=>'verified','pattern'=>'/^(?:RetroModemBrowser|RetroWEBBrowser)\/0\.1\.35 \(PalmOS 4; DeviceID ____\)$/i','provenance'=>'physical Palm IIIc submission; blank device ID, exact observed version only, 2026-11-10'],
 ['profile'=>'palm_tungsten_t','status'=>'verified','pattern'=>'/^(?:RetroModemBrowser|RetroWEBBrowser)\/[0-9]+(?:\.[0-9]+)* \(PalmOS 5; DeviceID Frg1\)$/i','provenance'=>'physical Palm Tungsten T RBrowser submission, 2026-11-10'],
 ['profile'=>'pocket_ie401','status'=>'verified','pattern'=>'/^Mozilla\/4\.0 \(compatible; MSIE 4\.01; Windows CE; PPC; 240x320\)$/i','provenance'=>'community Dell Axim X30 submission; shared Pocket PC browser UA, 2026-11-09'],
 ['profile'=>'nintendo_dsi','status'=>'verified','pattern'=>'/^Opera\/9\.50 \(Nintendo DSi; Opera\/[0-9]+; U; [a-z]{2}(?:-[a-z]{2})?\)$/i','provenance'=>'community Nintendo DSi Opera 507 submission, 2026-11-09'],
 ['profile'=>'nokia9500','status'=>'verified','pattern'=>'/\bSeries80\/2\.0\s+Nokia9500\/[0-9.]+\b/i','provenance'=>'physical Nokia 9500 built-in browser submission, 2026-11-08'],
 ['profile'=>'psion_revo','status'=>'verified','pattern'=>'/^EPOC32-WTL\/2\.0 \(RVGA\) STNC-WTL\/2\.0\(230\)$/i','provenance'=>'physical Psion Revo Web 2.01 submission, 2026-11-08'],
 ['profile'=>'macweb','status'=>'verified','pattern'=>'/\bMacWeb\/(?:F)?2\.0(?:\b|$)/i','provenance'=>'verified working V1 signature'],
 ['profile'=>'netscape2_mac','status'=>'verified','pattern'=>'/Mozilla\/2\.02.*Macintosh.*68K/i','provenance'=>'verified working V1 signature'],
 ['profile'=>'psion5mx','status'=>'verified','pattern'=>'/^EPOC32-WTL\/2\.0 STNC-WTL\/2\.0\(230\)$/i','provenance'=>'physical Psion Series 5mx submission, 2026-11-07'],
 ['profile'=>'psion5mx','status'=>'verified','pattern'=>'/(?:Series[ \/-]?5mx|Psion[^;)]*5mx|\bMC218\b)/i','provenance'=>'verified working V1 signature'],
 ['profile'=>'palm_rbrowser','status'=>'verified','pattern'=>'/^(?:RetroModemBrowser|RetroWEBBrowser)\/[0-9]+(?:\.[0-9]+)* \(PalmOS 4\)$/i','provenance'=>'physical Palm IIIc RBrowser submission, 2026-11-08'],
 ['profile'=>'palm','status'=>'verified','pattern'=>'/^(?!(?:RetroModemBrowser|RetroWEBBrowser)\/).*?(?:PalmSource|Blazer[ \/]|Xiino|PalmOS|Palm OS)/i','provenance'=>'verified working V1 signature; RBrowser has its own profile'],
 ['profile'=>'nokia9210','status'=>'verified','pattern'=>'/(?:Nokia[\s\/-]?9210i?|Crystal\/6\.0)/i','provenance'=>'existing representative physical-device test'],
 ['profile'=>'nokia9210','status'=>'verified','pattern'=>'/EPOC\).*Opera\s+6\.0.*Nokia\/Series-9200\b/i','provenance'=>'physical Nokia 9210i submission, 2026-11-08'],
 ['profile'=>'ie_win98','status'=>'unverified','pattern'=>'/MSIE\s+[3-6](?:\.\d+)?.*(?:Windows 98|Win98)/i','provenance'=>'catalogued; pending physical verification'],
];
