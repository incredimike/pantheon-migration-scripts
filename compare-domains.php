<?php

$site_env = $argv[1];
$file = $argv[2];
//$site_env = 'ucf-osi.live';
//$file = 'osi-domains.txt';


printf("Comparing Pantheon (%s) with local list (%s)... \n\n", $site_env, $file);

// Local Domains
$file_domains = file($file);
$file_domains = array_map('trim', $file_domains);
sort($file_domains);

// Terminus Domains
$command = sprintf('terminus domain:list %s --format=list', $site_env);
$results = shell_exec($command);
if (!is_string($results)) {
    exit('Reading domains failed!');
}
$pantheon_domains = explode("\n", trim($results));
sort($pantheon_domains);

// Compare Pantheon Domains to local list
$pantheon_vs_local = array_diff($pantheon_domains, $file_domains);
if (!empty($pantheon_vs_local)) {
    printf("Pantheon (%s) contains domains are not in the local list:\n", $site_env);
    foreach ($pantheon_vs_local as $domain) {
        // @TODO filter out the live-xxxxx.pantheonsite.io domains
        echo "$domain\n";
    }
    echo "\n";
}

// Compare Pantheon Domains to local list
$local_vs_pantheon = array_diff($file_domains, $pantheon_domains);
if (!empty($local_vs_pantheon)) {
    printf("Local list contains domains which do not exist in Pantheon (%s):\n", $site_env);
    foreach ($local_vs_pantheon as $domain) {
        echo "$domain\n";
    }
}

if (empty($pantheon_vs_local) && empty($local_vs_pantheon)) {
    printf("Domains from Pantheon %s matched domains in the local list.", $site_env);
}
