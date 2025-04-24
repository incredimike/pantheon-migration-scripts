<?php


$site_env = $argv[1];
$file     = $argv[2];
//$site_env = 'ucf-osi.live';
//$file = 'osi-domains.txt';


printf("Adding Domains to Pantheon (%s) from domain list list (%s)... \n\n", $site_env, $file);

// Local Domains
$file_domains = file($file);
$file_domains = array_map('trim', $file_domains);
sort($file_domains);

// Terminus Domains
foreach ( $file_domains as $domain ) {
    $command = sprintf('terminus domain:add %s %s', $site_env, $domain);
    echo $command . "\n";
    $results = shell_exec($command);
}
