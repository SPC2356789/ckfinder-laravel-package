<?php

namespace CKSource\CKFinderBridge\Command;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class CKFinderDownloadCommand extends Command
{
    const LATEST_VERSION = '3.7.0';

    protected $name = 'ckfinder:download';

    protected $description = 'Downloads the CKFinder distribution package and extracts assets.';

    /**
     * Creates URL to CKFinder distribution package.
     *
     * @return string
     */
    protected function buildPackageUrl()
    {
        return "http://download.cksource.com/CKFinder/CKFinder%20for%20PHP/" . self::LATEST_VERSION . "/ckfinder_php_" . self::LATEST_VERSION . ".zip";
    }

    /**
     * Handles command execution.
     */
    public function handle()
    {
        $targetPublicPath = realpath(__DIR__ . '/../../public/');
        if (!is_writable($targetPublicPath)) {
            $this->error('The target public directory is not writable (used path: ' . $targetPublicPath . ').');
            return;
        }

        $targetConnectorPath = __DIR__ . '/../../_connector'; // 不用 realpath 以便自動建立
        if (!file_exists($targetConnectorPath)) {
            mkdir($targetConnectorPath, 0755, true);
            $this->info('Created _connector directory at: ' . $targetConnectorPath);
        }
        if (!is_writable($targetConnectorPath)) {
            $this->error('The connector directory is not writable (used path: ' . $targetConnectorPath . ').');
            return;
        }

        if (file_exists($targetPublicPath.'/ckfinder/ckfinder.js')) {
            $questionText = 'CKFinder seems already installed. This will overwrite existing files. Proceed? [y/n]: ';
            if (!$this->confirm($questionText)) return;
        }

        // 指向本地 ZIP 檔
        $zipPath = base_path('vendor/spcdesign/ckfinder-laravel-package/ckfinder_php_' . self::LATEST_VERSION . '.zip');
        if (!file_exists($zipPath)) {
            $this->error('CKFinder ZIP file not found at: ' . $zipPath);
            return;
        }

        $this->info('Using local CKFinder ZIP: ' . $zipPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->error('Failed to open ZIP archive.');
            return;
        }

        $this->info('Extracting CKFinder files...');
        $filesToKeep = ['ckfinder/config.js', 'ckfinder/ckfinder.html'];
        $zipEntries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (in_array($entry, $filesToKeep) && file_exists($targetPublicPath . '/' . $entry)) {
                continue;
            }
            $zipEntries[] = $entry;
        }

        $zip->extractTo($targetPublicPath, $zipEntries);
        $zip->close();

        $this->info('Moving CKFinder connector files to _connector...');
        $fs = new \Illuminate\Filesystem\Filesystem();
        $sourcePath = $targetPublicPath . '/ckfinder/core/connector/php/vendor/cksource/ckfinder/src/CKSource/CKFinder';

        // 使用 copyDirectory 避免刪掉 _connector
        $fs->copyDirectory($sourcePath, $targetConnectorPath);

        $this->info('Cleaning up temporary files...');
        $fs->delete([
            $targetPublicPath . '/ckfinder/config.php',
            $targetPublicPath . '/ckfinder/README.md',
        ]);
        $fs->deleteDirectory($targetPublicPath . '/ckfinder/core');
        $fs->deleteDirectory($targetPublicPath . '/ckfinder/userfiles');

        $this->info('Done. CKFinder is ready!');
    }
}
