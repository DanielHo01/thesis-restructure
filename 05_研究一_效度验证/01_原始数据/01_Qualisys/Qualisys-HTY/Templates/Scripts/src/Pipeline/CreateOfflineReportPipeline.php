<?php
namespace Qualisys\Gait\Pipeline;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

use Qualisys\PafToolbelt\Commands\CreateOfflineReportCommand;

class CreateOfflineReportPipeline extends Pipeline {
	public function run() {
        $userSettings = $this->options['userSettings'];
        $output  = new ConsoleOutput();
        $command = new CreateOfflineReportCommand();
        $embedFiles = [];
        
        foreach ($this->qtmData['measurements'] as $m) {
            $embedFiles[basename($m->name, '.qtm') . '-3d-data.json'] = $this->workingDirectory . basename($m->name, '.qtm') . '-3d-data.json';
        }
        
        $objDirectory = __DIR__ . '/../../vendor/qualisys/paf-gait-web/build/obj/';
        $objFiles = array_diff(scandir($objDirectory), ['..', '.']);
        
        foreach ($objFiles as $file) {
            $embedFiles['obj/' . $file] = $objDirectory . $file;
        }
        
        $videoDirectory = $this->workingDirectory;
        $videoPaths = preg_grep('~\.(mp4)$~', scandir($videoDirectory));
        $embedArg = [];
        
        foreach ($videoPaths as $file) {
            array_push($embedArg, $videoDirectory . $file);
        }

        // Look for attachments folder(s)
        $attachmentDirectories = array('Attachments');
        
        foreach ($attachmentDirectories as $attachmentDirectoryName) {
            $attachmentDirectory = $this->workingDirectory . $attachmentDirectoryName . '\\';
            
            if (file_exists($attachmentDirectory)) {
                $attachmentFiles = array_diff(scandir($attachmentDirectory), ['..', '.']);
                
                foreach ($attachmentFiles as $file) {
                    $embedFiles['attachments/' . $file] = $attachmentDirectory . $file;
                }
            }
        }
            
        $embedArg = implode(', ', $embedArg);
        
        // Create comma-separated list of key:value pairs,
        // ie "foo/file1:/path/to/file1,foo/file2:/path/to/file2".
        $embedObjArg = array_map(null, array_keys($embedFiles), array_values($embedFiles));
        $embedObjArg = join(',', array_map(function($pair) { return join(':', $pair); }, $embedObjArg));
        
        $embedArg .= ', ' . $embedObjArg;
        
        // Create a default config object to use in the web report.
        $exposeProjectSettings = function() {
            include $this->templateDirectory . 'settings.php';

            return [
                'line_color_left' => $line_color_left,
                'line_color_right' => $line_color_right,
                'line_color_EMG_left' => $line_color_EMG_left,
                'line_color_EMG_right' => $line_color_EMG_right,
                'line_color_EMG_raw_left' => $line_color_EMG_raw_left,
                'line_color_EMG_raw_right' => $line_color_EMG_raw_right,
                'EMG_units_pct' => $EMG_units_pct,
                'logo_height' => $logo_height,
            ];
        };

        $projectSettings = $exposeProjectSettings();

        $defaultConfig = [
            'displaySettings' => [
                'lineColors' => [
                    'left' => $projectSettings['line_color_left'],
                    'right' => $projectSettings['line_color_right'],

                    'emg' => [
                        'left' => $projectSettings['line_color_EMG_left'],
                        'right' => $projectSettings['line_color_EMG_right'],

                        'raw' => [
                            'left' => $projectSettings['line_color_EMG_raw_left'],
                            'right' => $projectSettings['line_color_EMG_raw_right']
                        ]
                    ]
                ],
                'units' => [
                    'emgUnitsPercent' => $projectSettings['EMG_units_pct']
                ],
                'logoHeight' => $projectSettings['logo_height']
            ]
        ];

        $availableLanguages = ['de', 'en', 'es', 'fr', 'sv'];
        $reportLanguage = $userSettings->get('Web report language');

        if (empty($reportLanguage) || !in_array($reportLanguage, $availableLanguages)) {
            $reportLanguage = 'en';
        }

        $command->run(new ArrayInput([
            '<template>'          => $this->templateDirectory . 'Scripts/vendor/qualisys/paf-gait-web/build/index.html',
            '<output-file>'       => $this->workingDirectory . 'Report_' . $this->session->Patient_ID . '_' . preg_replace('/\s+/', '_', $this->session->First_name) . '_' . preg_replace('/\s+/', '_', $this->session->Last_name) . '_' . $this->session->Creation_date . '.html',
            '--json'              => $this->workingDirectory . 'data.json',
            '--open'              => true,
            '--language'          => $reportLanguage,
            '--theme'             => $userSettings->get['Report theme'],
            '--embed'             => $embedArg,
            '--config'            => json_encode($defaultConfig)
//					'--report-template'   => 'Gait'
        ]), $output);
    }
}
?>