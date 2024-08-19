<?php

require 'vendor/autoload.php';

use jc21\CliTable;
use jc21\CliTableManipulator;

//$site_env = 'ucf-coscom.dev';
//$site_env = 'ucf-cosmain.dev';
$site_env = 'ucf-cosphy1.dev';
//$site_env = 'ucf-scsant.dev';
//$site_env = 'ucf-scsbio.dev';
//$site_env = 'ucf-scschm.dev';
//$site_env = 'ucf-scsmth.dev';
//$site_env = 'ucf-scsphy.dev';
//$site_env = 'ucf-scspol.dev';
//$site_env = 'ucf-scspsy.dev';
//$site_env = 'ucf-scssoc.dev';
//$site_env = 'ucf-scsstt.dev';
$file_path = 'known-issue-plugins.txt';
$plugin_list = $file_array = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);;


class Pantheon_WPMS_Active_Plugin_Scan {

    protected string $site_env;
    protected array $problem_plugin_list;
    private array $plugins_network_active;
    private array $plugins_blog_active;

    public function run($env, $display_report = true): void
    {
        $this->site_env = $env;
        $this->plugins_network_active = $this->get_network_active_plugins();
        if ($display_report) {
            $this->displayNetworkPluginsReport($this->plugins_network_active);
        }
        $this->plugins_blog_active = $this->get_blog_active_plugins();
        if ($display_report) {
            $this->displayBlogPluginsReport($this->plugins_blog_active);
        }

    }

    protected function get_network_active_plugins(): array
    {
        // Get plugins for blog_id=1
        $command = sprintf(
            'terminus wp %s -- plugin list \
            --skip-packages --skip-themes --skip-plugins \
            --status=active-network \
            --fields=name,status,version \
            --format=json 2> /dev/null',
            $this->site_env
        );

        echo $command . "\n";

        $results = shell_exec($command);
        $results = json_decode($results, true);
//        var_dump($results);
        return array_column($results, 'name');
    }

    protected function get_blog_active_plugins(): array
    {
        $command = sprintf(
            'terminus wp %s -- site list --fields=url --skip-packages --skip-themes --skip-plugins --format=json 2> /dev/null',
            $this->site_env
        );

        $results = shell_exec($command);
        $site_urls = json_decode($results, true);
        $site_urls = array_column($site_urls, 'url');

        $plugins = [];
        //var_dump($site_urls);
        foreach ($site_urls as $url) {

            $command = sprintf(
                'terminus wp %s -- plugin list --url="%s" \
                --skip-packages --skip-themes --skip-plugins \
                --status=active --fields=name,status,version \
                --format=json 2> /dev/null',
                $this->site_env,
                $url
            );
            echo "SITE: " . $command . "\n";
            $results = shell_exec($command);
            $results = json_decode($results, true);
            //var_dump($results);
            $plugin_list = array_column($results, 'name');

            //var_dump($plugin_list);

            foreach ($plugin_list as $name) {
                $plugins[ $name ][] = $url;
            }
        }

        ksort($plugins);

        //var_Dump(         $plugins        );

        return $plugins;
    }

    public function setProblemPluginList(array $problem_plugin_list): void
    {
        $this->problem_plugin_list = $problem_plugin_list;
    }

    public function getReport() {
        $report = [];
        foreach ($this->problem_plugin_list as $plugin) {
            $report[] = [
                'plugin' => $plugin,
                'network_active' => in_array($plugin, $this->plugins_network_active),
                'blog_active' => in_array($plugin, $this->plugins_blog_active),
            ];
        }

        return $report;
    }

    private function displayNetworkPluginsReport(array $plugins_network_active)
    {
        $problem_plugin_list = $this->problem_plugin_list;
        $data = array_map(function($plugin_name) use ($problem_plugin_list) {
            return [
                'plugin_name' => $plugin_name,
                'is_problem' => in_array($plugin_name, $problem_plugin_list) ? 'Yes' : '-'
            ];
        }, $plugins_network_active);

        $table = new CliTable;
        $table->setTableColor('blue');
        $table->setHeaderColor('cyan');
        $table->addField('Network Activated Plugins','plugin_name', false,'white');
        $table->addField('Problem Plugin', 'is_problem', false,'red');
        $table->injectData($data);
        $table->display();

    }

    private function displayBlogPluginsReport(array $plugins_blog_active)
    {
        $problem_plugin_list = $this->problem_plugin_list;
        //var_dump('plugins_blog_active');
        //var_dump($plugins_blog_active); die;

        $data = [];
        foreach ($plugins_blog_active as $plugin_name => $blog_urls) {
            foreach ($blog_urls as $url) {
                $data[] = [
                    'plugin_name' => $plugin_name,
                    'blog_url' => $url,
                    'is_problem' => in_array($plugin_name, $problem_plugin_list) ? 'Yes' : '-'
                ];
            }
        }

        usort($data, function($a, $b) {
            return $a['plugin_name'] <=> $b['plugin_name'];
        });


        $table = new CliTable;
        $table->setTableColor('blue');
        $table->setHeaderColor('cyan');
        $table->addField('Blog Activated Plugins','plugin_name', false,'white');
        $table->addField('Blog URL', 'blog_url', false,'white');
        $table->addField('Problem Plugin', 'is_problem', false,'red');
        $table->injectData($data);
        $table->display();

    }
}



$scanner = new Pantheon_WPMS_Active_Plugin_Scan();

$scanner->setProblemPluginList($plugin_list);
$scanner->run($site_env);





