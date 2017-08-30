<?php

namespace AppBundle\Command;

use AppBundle\Command\Base\BaseCommand;
use Survos\Client\Resource\DataImageResource;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Survos\Client\SurvosClient;
use Survos\Client\Resource\MemberResource;
use Survos\Client\Resource\ProjectResource;
use Survos\Client\Resource\UserResource;


class ExportDataCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('export:data')
            ->setDescription('Export data from source server as text')
            ->addOption(
                'source-project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Source project code'
            )
            ->addOption(
                'filename',
                null,
                InputOption::VALUE_REQUIRED,
                'Output File Name'
            )
            ->addOption(
                'url-only',
                null,
                InputOption::VALUE_NONE,
                'list of URLs only'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of records to read',
                10
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
//        $output->writeln("<error>Command not implemented yet</error>");
//        die();
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $sourceProject = $input->getOption('source-project-code');
        $urlOnly = $input->getOption('url-only');

        $fromData = new DataImageResource($this->sourceClient);
        $projectResource = new ProjectResource($this->client);
        $userResource = new UserResource($this->client);

        if (!$outFile = $input->getOption('filename'))
        {
            $outFile = $sourceProject . ($urlOnly ? '_urls.txt' : "_data.csv");
        }

        $fp = fopen($outFile, 'w');

        $page = 0;
        $perPage = 50;
        $maxPages = 1;
        while ($page < $maxPages) {
            $dataImages = $fromData->getList(++$page, $perPage, [], [], [], ['project_code' => $sourceProject]);
            $maxPages = $dataImages['pages'];
            // if no items, return
            if (!count($dataImages['items']) || !$dataImages['total']) {
                break;
            }
            foreach ($dataImages['items'] as $key => $dataImage) {
                $no = ($page - 1) * $perPage + $key + 1;
                if ($isVerbose) {
                    $output->writeln("{$no} - Reading data #{$dataImage['id']}");
                }
                if (!$urlOnly && ($no == 1) )
                {
                    fputcsv($fp, array_keys($dataImage));
                }

                // $this->processDataFields($dataImage);
                fputcsv($fp, $urlOnly ? [$dataImage['image_url']]: array_values($dataImage));
            }

            if ($no==$input->getOption('limit')) {
                break;
            }


        }
        fclose($fp);
        $output->writeln(sprintf("$outFile written with %d %s", $no, $urlOnly ? 'urls' : 'records'));

    }

    /**
     * prepare member array to be imported
     *
     * @param $dataImage
     * @param $newProject
     */
    private function processDataFields(&$dataImage, $newProject)
    {
        unset($dataImage['id']);
        unset($dataImage['created_at']);
        unset($dataImage['updated_at']);
    }
}
