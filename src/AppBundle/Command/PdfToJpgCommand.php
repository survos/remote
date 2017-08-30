<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;


class PdfToJpgCommand extends Command
{

    /**
     * @type OutputInterface
     */
    private $output;
    private $inPath;
    private $outPath;
    private $outCsv = [];

    protected function configure()
    {
        $this
            ->setName('convert:pdf-jpg')
            ->setDescription('Show basic summary for waves')
            ->addOption(
                'root-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Root folder where we should search for PDFs. Root folder should contain directly list of restaurants'
            )
            ->addOption(
                'out-path',
                null,
                InputOption::VALUE_REQUIRED,
                'folder where we should export images'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $root = $input->getOption('root-path');
        $this->inPath = $input->getOption('root-path');
        $this->outPath = $input->getOption('out-path').'/images';
        if (!is_dir($this->outPath)) {
            mkdir($this->outPath, 0777, true);
        }
        $this->parseDirectory($root);

        foreach ($this->outCsv as $name => $fileData) {
            $fp = fopen($this->outPath.'/'.$name.'.csv', 'w');
            foreach ($fileData as $index => $line) {
                if ($index == 0) {
                    fputcsv($fp, array_keys($line));
                }
                fputcsv($fp, array_values($line));
            }
            fclose($fp);
        }


        // zip folder
        exec("cd \"{$this->outPath}\" && zip -rm ../images.zip *");
    }

    private function parseDirectory($directoryPath)
    {
        $path = ($directoryPath instanceof SplFileInfo) ? $directoryPath->getPathname() : $directoryPath;

        $finder = new Finder();
//        $finder->directories()->in($path);
//        /** @type SplFileInfo $directory */
//        foreach ($finder as $directory) {
//            $this->parseDirectory($directory);
//        }

        $finder->files()->name('*.pdf')->in($path);
        /** @type SplFileInfo $file */
        foreach ($finder as $file) {
            $this->processFile($file);
        }
    }

    private function processFile(SplFileInfo $path)
    {
        $relativeDirName = dirname(str_replace($this->inPath, '', $path->getPathname()));
        $filenameParts = explode('/', $relativeDirName);
        $pdfDir = array_pop($filenameParts);
        $restaurant = array_pop($filenameParts);

        $outDir = $this->outPath.'/'.$relativeDirName;
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        $outFilename = $outDir.'/'.str_replace('.pdf', '-%03d.png', $path->getFilename());
        $convertCommand = "gm convert -density 300 \"{$path->getPathname()}\" -resize '1280x1280>' +profile '*' +adjoin \"{$outFilename}\"";
        $this->output->writeln("Processing {$path->getPathname()}");
        // convert directory
        exec($convertCommand);
        // create csv file
        $finder = new Finder();
        $finder->files()->name('*.png')->in($outDir);

        /** @type SplFileInfo $file */
        $index = 1;
        $this->output->writeln("-- ".$finder->count()." images");

        foreach ($finder as $file) {
            if (!isset($this->outCsv[$restaurant])) {
                $this->outCsv[$restaurant] = [];
            }

            $this->outCsv[$restaurant][] = [
                'restaurant' => $restaurant,
                'pdf'        => $path->getRelativePathname(),
                'page'       => $index,
                'image'      => $relativeDirName.'/'.$file->getFilename(),
                'folder'     => $relativeDirName,

            ];
            $index++;
        }

    }

}
