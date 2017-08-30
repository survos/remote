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


class XferDataCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('xfer:data')
            ->setDescription('Transfer members from source server')
            ->addOption(
                'source-project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Source project code'
            )
            ->addOption(
                'target-project-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Target project code'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
//        $output->writeln("<error>Command not implemented yet</error>");
//        die();
        $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
        $sourceProject = $input->getOption('source-project-code');
        $targetProject = $input->getOption('target-project-code');

        $fromData = new DataImageResource($this->sourceClient);
        $toData = new DataImageResource($this->client);
        $projectResource = new ProjectResource($this->client);
        $userResource = new UserResource($this->client);

        $project = $projectResource->getOneBy(['code' => $targetProject]);
        if (!$project) {
            $output->writeln(
                "<error>Target project {$targetProject} not found</error>"
            );
            die();
        }

        $page = 0;
        $perPage = 10;
        $maxPages = 1;
        while ($page < $maxPages) {
            $dataImages = $fromData->getList(++$page, $perPage, [], [], [], ['project_code' => $sourceProject]);
            $maxPages = $dataImages['pages'];
            // if no items, return
            if (!count($dataImages['items']) || !$dataImages['total']) {
                break;
            }

            foreach ($dataImages['items'] as $key => $dataImage) {
                if ($isVerbose) {
                    $no = ($page - 1) * $perPage + $key + 1;
                    $output->writeln("{$no} - Reading member #{$dataImage['id']}");
                }

                $this->processDataFields($dataImage, $project);

                try {
                    $res = $toData->save($dataImage);
                    if ($isVerbose) {
                        $output->writeln("New member  #{$res['id']} saved");
                    }
                } catch (\Exception $e) {
                    $output->writeln(
                        "<error>Problem saving data: {$e->getMessage()} </error>"
                    );
                }
            }


        }

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
        $dataImage['project_id'] = $newProject['id'];
    }
}
